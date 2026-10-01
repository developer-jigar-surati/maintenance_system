<?php

namespace App\Livewire\Billing;

use App\Enums\Permission;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Payment;
use App\Services\Payments\PaymentRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class PaymentIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $mode = '';

    public ?int $rejecting = null;

    public string $rejectionReason = '';

    protected function sortableColumns(): array
    {
        return ['payment_number', 'paid_at', 'amount', 'status'];
    }

    protected function defaultSort(): array
    {
        return ['paid_at', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['status', 'mode'];
    }

    public function approve(int $paymentId, PaymentRecorder $recorder): void
    {
        Gate::authorize(Permission::PAYMENT_APPROVE);

        $payment = Payment::findOrFail($paymentId);

        try {
            $recorder->approve($payment, auth()->user());
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage(), tone: 'critical');

            return;
        }

        $this->dispatch('notify',
            message: "Payment {$payment->payment_number} approved and receipt issued.",
            tone: 'positive');
    }

    public function reject(PaymentRecorder $recorder): void
    {
        Gate::authorize(Permission::PAYMENT_APPROVE);

        $this->validate(['rejectionReason' => 'required|string|min:3|max:255']);

        $payment = Payment::findOrFail($this->rejecting);
        $recorder->reject($payment, auth()->user(), $this->rejectionReason);

        $this->reset(['rejecting', 'rejectionReason']);
        $this->dispatch('close-modal', 'reject-payment');
        $this->dispatch('notify', message: 'Payment rejected.', tone: 'positive');
    }

    public function startReject(int $paymentId): void
    {
        $this->rejecting = $paymentId;
        $this->rejectionReason = '';
        $this->dispatch('open-modal', 'reject-payment');
    }

    public function render()
    {
        $user = auth()->user();
        $canSeeAll = $user->isSuperAdmin() || $user->can(Permission::PAYMENT_RECORD);

        $query = Payment::query()
            ->with(['unit.block', 'payer', 'receipt', 'approvedBy'])
            ->when(! $canSeeAll, fn (Builder $q) => $q->whereIn('unit_id', $user->units()->pluck('units.id')))
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $i) {
                $i->where('payment_number', 'like', "%{$this->search}%")
                    ->orWhere('reference_number', 'like', "%{$this->search}%")
                    ->orWhereHas('unit', fn (Builder $u) => $u->where('unit_number', 'like', "%{$this->search}%"));
            }))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->mode !== '', fn (Builder $q) => $q->where('mode', $this->mode));

        return view('livewire.billing.payment-index', [
            'payments' => $this->applySort($query)->paginate($this->perPage),
            'canApprove' => $user->can(Permission::PAYMENT_APPROVE),
            'pendingCount' => Payment::query()->pendingApproval()->count(),
        ])->title('Payments');
    }
}
