<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use App\Models\Society;
use App\Models\Unit;
use App\Models\User;
use App\Services\Accounting\LedgerPoster;
use App\Services\NumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records money received and settles it against outstanding bills.
 *
 * Offline payments (cash or cheque handed to a committee member) are held for
 * approval; online payments confirmed by a gateway are complete on arrival.
 * Either way, completing a payment allocates it to invoices oldest-first and
 * issues a numbered receipt.
 */
class PaymentRecorder
{
    public function __construct(
        private NumberGenerator $numbers,
        private LedgerPoster $ledger,
        private ReceiptIssuer $receipts,
    ) {}

    /**
     * Records an offline payment. Whether it needs approval is the society's
     * choice; when it does, no ledger entry is written until someone signs off.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordOffline(Unit $unit, array $attributes, ?User $recordedBy = null): Payment
    {
        $society = $unit->society;
        $requiresApproval = (bool) $society->setting('payments.offline_requires_approval', true);
        $paidAt = isset($attributes['paid_at']) ? Carbon::parse($attributes['paid_at']) : now();

        return DB::transaction(function () use ($unit, $attributes, $recordedBy, $society, $requiresApproval, $paidAt) {
            $payment = Payment::create([
                'society_id' => $society->id,
                'unit_id' => $unit->id,
                'payer_user_id' => $attributes['payer_user_id'] ?? $unit->billingContact()?->user_id,
                'payment_number' => $this->numbers->next(NumberGenerator::PAYMENT, $society, $paidAt),
                'amount' => round((float) $attributes['amount'], 2),
                'unallocated_amount' => round((float) $attributes['amount'], 2),
                'paid_at' => $paidAt,
                'method' => $attributes['method'] ?? 'cash',
                'mode' => 'offline',
                'status' => $requiresApproval ? 'awaiting_approval' : 'completed',
                'reference_number' => $attributes['reference_number'] ?? null,
                'bank_name' => $attributes['bank_name'] ?? null,
                'instrument_date' => $attributes['instrument_date'] ?? null,
                'attachment_path' => $attributes['attachment_path'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'recorded_by' => $recordedBy?->id,
            ]);

            if (! $requiresApproval) {
                $this->complete($payment, $attributes['invoice_ids'] ?? null);
            }

            return $payment->refresh();
        });
    }

    /**
     * Approves a pending offline payment, which then allocates and receipts
     * exactly as an online one would.
     *
     * @param  array<int>|null  $invoiceIds  specific bills to settle, oldest-first if null
     */
    public function approve(Payment $payment, User $approver, ?array $invoiceIds = null): Payment
    {
        if (! $payment->needsApproval()) {
            throw new \DomainException("Payment {$payment->payment_number} is not awaiting approval.");
        }

        return DB::transaction(function () use ($payment, $approver, $invoiceIds) {
            $payment->forceFill([
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ])->save();

            $this->complete($payment, $invoiceIds);

            return $payment->refresh();
        });
    }

    public function reject(Payment $payment, User $approver, string $reason): Payment
    {
        $payment->forceFill([
            'status' => 'cancelled',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        return $payment;
    }

    /**
     * Marks a payment complete, allocates it, posts it to the ledger and
     * issues its receipt. Shared by the offline and online paths.
     *
     * @param  array<int>|null  $invoiceIds
     */
    public function complete(Payment $payment, ?array $invoiceIds = null): Receipt
    {
        return DB::transaction(function () use ($payment, $invoiceIds) {
            $payment->forceFill(['status' => 'completed'])->save();

            $this->allocate($payment, $invoiceIds);

            $payment->refresh();
            $this->ledger->postPayment($payment);

            return $this->receipts->issueFor($payment);
        });
    }

    /**
     * Applies a payment to invoices, oldest due date first, so the longest
     * outstanding dues clear before recent ones. Anything left over stays on
     * the payment as unallocated credit against the unit.
     *
     * @param  array<int>|null  $invoiceIds
     * @return Collection<int, PaymentAllocation>
     */
    public function allocate(Payment $payment, ?array $invoiceIds = null): Collection
    {
        $remaining = round((float) $payment->unallocated_amount, 2);

        if ($remaining <= 0 || $payment->unit_id === null) {
            return new Collection;
        }

        $invoices = Invoice::query()
            ->where('society_id', $payment->society_id)
            ->where('unit_id', $payment->unit_id)
            ->open()
            ->when($invoiceIds, fn ($q) => $q->whereIn('id', $invoiceIds))
            ->orderBy('due_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $allocations = new Collection;

        foreach ($invoices as $invoice) {
            if ($remaining <= 0.004) {
                break;
            }

            $applied = min($remaining, (float) $invoice->balance);

            if ($applied <= 0.004) {
                continue;
            }

            $allocations->push(PaymentAllocation::create([
                'society_id' => $payment->society_id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => round($applied, 2),
            ]));

            $invoice->load('lines')->recalculate();

            $remaining = round($remaining - $applied, 2);
        }

        $payment->forceFill(['unallocated_amount' => max(0, $remaining)])->save();

        return $allocations;
    }

    /**
     * Spends a unit's accumulated credit on a newly raised bill. Called after
     * a bill run so residents who paid in advance are not shown as due.
     */
    public function applyCreditTo(Invoice $invoice): float
    {
        $credits = Payment::query()
            ->where('society_id', $invoice->society_id)
            ->where('unit_id', $invoice->unit_id)
            ->withCredit()
            ->orderBy('paid_at')
            ->get();

        $applied = 0.0;

        foreach ($credits as $payment) {
            $invoice->refresh();

            if ((float) $invoice->balance <= 0.004) {
                break;
            }

            $applied += (float) $this->allocate($payment, [$invoice->id])->sum('amount');
        }

        return round($applied, 2);
    }

    /** Total credit a unit is holding, across all its payments. */
    public function creditBalanceFor(Unit $unit): float
    {
        return round((float) Payment::query()
            ->where('unit_id', $unit->id)
            ->withCredit()
            ->sum('unallocated_amount'), 2);
    }
}
