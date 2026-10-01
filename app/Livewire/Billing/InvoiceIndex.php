<?php

namespace App\Livewire\Billing;

use App\Enums\Permission;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Block;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class InvoiceIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $blockId = '';

    #[Url(except: '')]
    public string $period = '';

    protected function sortableColumns(): array
    {
        return ['invoice_number', 'issue_date', 'due_date', 'total', 'balance', 'status'];
    }

    protected function defaultSort(): array
    {
        return ['due_date', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['status', 'blockId', 'period'];
    }

    public function render()
    {
        $user = auth()->user();
        $canSeeAll = $user->isSuperAdmin() || $user->can(Permission::BILLING_MANAGE)
            || $user->can(Permission::PAYMENT_RECORD);

        $query = Invoice::query()
            ->with(['unit.block', 'unit.activeResidents.user'])
            // A resident sees only their own bills; management sees the society's.
            ->when(! $canSeeAll, fn (Builder $q) => $q->whereIn('unit_id', $user->units()->pluck('units.id')))
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $inner) {
                $inner->where('invoice_number', 'like', "%{$this->search}%")
                    ->orWhereHas('unit', fn (Builder $u) => $u->where('unit_number', 'like', "%{$this->search}%"));
            }))
            ->when($this->status === 'overdue', fn (Builder $q) => $q->overdue())
            ->when($this->status !== '' && $this->status !== 'overdue',
                fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->blockId !== '',
                fn (Builder $q) => $q->whereHas('unit', fn (Builder $u) => $u->where('block_id', $this->blockId)))
            // Compared as a date range rather than with a driver-specific date
            // function, so this works on SQLite, MySQL and Postgres alike.
            ->when($this->period !== '', function (Builder $q) {
                $month = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth();

                $q->whereBetween('period_start', [$month, $month->copy()->endOfMonth()]);
            });

        $summaryQuery = (clone $query);

        return view('livewire.billing.invoice-index', [
            'invoices' => $this->applySort($query)->paginate($this->perPage),
            'blocks' => Block::orderBy('name')->get(),
            'canSeeAll' => $canSeeAll,
            'summary' => [
                'count' => (clone $summaryQuery)->count(),
                'billed' => (float) (clone $summaryQuery)->sum('total'),
                'outstanding' => (float) (clone $summaryQuery)->sum('balance'),
            ],
        ])->title('Invoices');
    }
}
