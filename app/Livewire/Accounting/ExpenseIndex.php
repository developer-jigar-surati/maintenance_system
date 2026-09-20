<?php

namespace App\Livewire\Accounting;

use App\Enums\Permission;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Expense;
use App\Services\Accounting\LedgerPoster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ExpenseIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    protected function sortableColumns(): array
    {
        return ['expense_number', 'bill_date', 'total', 'status'];
    }

    protected function defaultSort(): array
    {
        return ['bill_date', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['status'];
    }

    /** Approving recognises the expense and the liability in the books. */
    public function approve(int $expenseId, LedgerPoster $ledger): void
    {
        Gate::authorize(Permission::EXPENSE_APPROVE);

        $expense = Expense::with(['vendor', 'chargeHead.ledgerAccount', 'ledgerAccount'])->findOrFail($expenseId);

        if ($expense->isApproved()) {
            return;
        }

        $expense->forceFill([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ])->save();

        $expense->recalculate();
        $ledger->postExpense($expense);

        $this->dispatch('notify', message: "Expense {$expense->expense_number} approved.", tone: 'positive');
    }

    public function render()
    {
        $query = Expense::query()
            ->with(['vendor', 'chargeHead', 'createdBy'])
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $i) {
                $i->where('expense_number', 'like', "%{$this->search}%")
                    ->orWhere('bill_number', 'like', "%{$this->search}%")
                    ->orWhereHas('vendor', fn (Builder $v) => $v->where('name', 'like', "%{$this->search}%"));
            }))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status));

        $totals = (clone $query);

        return view('livewire.accounting.expense-index', [
            'expenses' => $this->applySort($query)->paginate($this->perPage),
            'canApprove' => auth()->user()->can(Permission::EXPENSE_APPROVE),
            'summary' => [
                'total' => (float) (clone $totals)->sum('total'),
                'unpaid' => (float) (clone $totals)->whereIn('status', ['approved', 'partially_paid'])->sum('balance'),
                'pending' => (clone $totals)->where('status', 'pending_approval')->count(),
            ],
        ])->title('Expenses');
    }
}
