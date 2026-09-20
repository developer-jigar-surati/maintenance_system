<?php

namespace App\Livewire\Billing;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Issued receipts. The number series is gap-free per society, so this
 * list doubles as the audit trail an auditor will ask for.
 */
#[Layout('components.layouts.app')]
class ReceiptIndex extends Component
{
    use WithDataTable;

    protected function sortableColumns(): array
    {
        return ['receipt_number', 'issued_on', 'amount'];
    }

    protected function defaultSort(): array
    {
        return ['issued_on', 'desc'];
    }

    protected function filterProperties(): array
    {
        return [];
    }

    public function render()
    {
        $query = Receipt::query()
            ->with(['unit.block', 'payment', 'issuedBy'])
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $i) {
                $i->where('receipt_number', 'like', "%{$this->search}%")
                ->orWhereHas('unit', fn (Builder $u) => $u->where('unit_number', 'like', "%{$this->search}%"));
            }));

        return view('livewire.billing.receipt-index', [
            'receipts' => $this->applySort($query)->paginate($this->perPage),
        ])->title('Receipts');
    }
}
