<?php

namespace App\Livewire\Billing;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Payments\PaymentRecorder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class InvoiceShow extends Component
{
    public Invoice $invoice;

    /** Offline payment form state. */
    public string $amount = '';

    public string $method = 'cash';

    public string $reference = '';

    public string $paidAt = '';

    public string $notes = '';

    public function mount(Invoice $invoice): void
    {
        $this->authorizeView($invoice);

        $this->invoice = $invoice->load(['lines.chargeHead', 'unit.block', 'unit.activeResidents.user', 'allocations.payment']);
        $this->amount = (string) $invoice->balance;
        $this->paidAt = now()->toDateString();
    }

    /** A resident may open their own unit's bill; management may open any. */
    private function authorizeView(Invoice $invoice): void
    {
        $user = auth()->user();

        if ($user->isSuperAdmin() || $user->can(Permission::BILLING_MANAGE) || $user->can(Permission::PAYMENT_RECORD)) {
            return;
        }

        abort_unless($user->units()->whereKey($invoice->unit_id)->exists(), 403);
    }

    public function recordPayment(PaymentRecorder $recorder): void
    {
        Gate::authorize(Permission::PAYMENT_RECORD);

        $validated = $this->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,cheque,demand_draft,neft,rtgs,imps,upi,card,netbanking,other',
            'reference' => 'nullable|string|max:120',
            'paidAt' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:500',
        ]);

        $payment = $recorder->recordOffline($this->invoice->unit, [
            'amount' => $validated['amount'],
            'method' => $validated['method'],
            'reference_number' => $validated['reference'] ?: null,
            'paid_at' => $validated['paidAt'],
            'notes' => $validated['notes'] ?: null,
            'invoice_ids' => [$this->invoice->id],
        ], auth()->user());

        $this->invoice->refresh()->load('lines.chargeHead', 'allocations.payment');
        $this->reset(['reference', 'notes']);
        $this->amount = (string) $this->invoice->balance;

        $this->dispatch('close-modal', 'record-payment');
        $this->dispatch('notify',
            message: $payment->needsApproval()
                ? "Payment {$payment->payment_number} recorded and sent for approval."
                : "Payment {$payment->payment_number} recorded.",
            tone: 'positive',
        );
    }

    /** Opens a gateway checkout, when the society has one configured. */
    public function payOnline(OnlinePaymentService $online): void
    {
        $society = $this->invoice->society;

        if (! $society->acceptsOnlinePayments()) {
            $this->dispatch('notify', message: 'Online payment is not enabled for this society.', tone: 'critical');

            return;
        }

        try {
            $result = $online->startCheckout(
                $this->invoice->unit,
                (float) $this->invoice->balance,
                [$this->invoice->id],
                auth()->user(),
            );
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage(), tone: 'critical');

            return;
        }

        $this->dispatch('open-checkout', checkout: $result['checkout'], paymentId: $result['payment']->id);
    }

    public function cancel(): void
    {
        Gate::authorize(Permission::INVOICE_CANCEL);

        if ($this->invoice->amount_paid > 0) {
            $this->dispatch('notify',
                message: 'This invoice has payments against it and cannot be cancelled.',
                tone: 'critical');

            return;
        }

        $this->invoice->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cancelled by '.auth()->user()->name,
        ])->save();

        $this->invoice->refresh();
        $this->dispatch('notify', message: 'Invoice cancelled.', tone: 'positive');
    }

    public function render()
    {
        return view('livewire.billing.invoice-show', [
            'canRecord' => auth()->user()->can(Permission::PAYMENT_RECORD),
            'canPayOnline' => $this->invoice->society->acceptsOnlinePayments() && $this->invoice->balance > 0,
        ])->title($this->invoice->invoice_number);
    }
}
