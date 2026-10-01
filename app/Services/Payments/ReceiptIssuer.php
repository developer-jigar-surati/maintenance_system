<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Receipt;
use App\Services\Messaging\Announcer;
use App\Services\NumberGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Issues the digital receipt for a completed payment.
 *
 * This is the replacement for the hand-written rasid: numbered in a gap-free
 * per-society series, tied to the payment, and independently verifiable
 * through the UUID encoded in its QR code.
 */
class ReceiptIssuer
{
    public function __construct(
        private NumberGenerator $numbers,
        private Announcer $announcer,
    ) {}

    /** Idempotent: a payment has exactly one receipt, however often this runs. */
    public function issueFor(Payment $payment, ?int $issuedBy = null): Receipt
    {
        if ($payment->relationLoaded('receipt') && $payment->receipt) {
            return $payment->receipt;
        }

        if ($existing = $payment->receipt()->first()) {
            return $existing;
        }

        if (! $payment->isCompleted()) {
            throw new \DomainException(
                "Cannot issue a receipt for payment {$payment->payment_number}: it is {$payment->status}."
            );
        }

        $receipt = DB::transaction(function () use ($payment, $issuedBy) {
            $society = $payment->society;
            $unit = $payment->unit;

            return Receipt::create([
                'society_id' => $payment->society_id,
                'payment_id' => $payment->id,
                'unit_id' => $payment->unit_id,
                'receipt_number' => $this->numbers->next(NumberGenerator::RECEIPT, $society, $payment->paid_at),
                'issued_on' => $payment->paid_at->toDateString(),
                'amount' => $payment->amount,
                'received_from' => $payment->payer?->name
                    ?? $unit?->billingContact()?->user?->name
                    ?? 'Member',
                'towards' => $this->describeAllocation($payment),
                'issued_by' => $issuedBy ?? $payment->approved_by ?? $payment->recorded_by,
            ]);
        });

        // The receipt reaching the resident is the whole point of replacing a
        // paper rasid, so it is sent here rather than left to each caller.
        // Sending is outside the transaction: a mail failure must not undo a
        // receipt that has already been numbered from a gap-free series.
        $this->announcer->receiptIssued($receipt->load(['unit.block', 'payment']));

        return $receipt;
    }

    /**
     * A human-readable summary of what the money settled, which is what a
     * resident actually wants to read on the receipt.
     */
    private function describeAllocation(Payment $payment): string
    {
        $allocations = $payment->allocations()->with('invoice')->get();

        if ($allocations->isEmpty()) {
            return 'Advance payment towards future dues';
        }

        $parts = $allocations
            ->map(fn ($a) => $a->invoice?->invoice_number)
            ->filter()
            ->take(5)
            ->implode(', ');

        $summary = 'Against invoice(s): '.$parts;

        if ($allocations->count() > 5) {
            $summary .= sprintf(' and %d more', $allocations->count() - 5);
        }

        if ((float) $payment->unallocated_amount > 0) {
            $summary .= sprintf('; %s retained as advance', number_format((float) $payment->unallocated_amount, 2));
        }

        return $summary;
    }

    public function cancel(Receipt $receipt, string $reason): Receipt
    {
        $receipt->forceFill([
            'is_cancelled' => true,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();

        return $receipt;
    }
}
