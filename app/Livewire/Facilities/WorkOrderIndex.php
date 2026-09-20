<?php

namespace App\Livewire\Facilities;

use App\Livewire\Concerns\WithDataTable;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Work in progress across the society, whatever raised it.
 */
#[Layout('components.layouts.app')]
class WorkOrderIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $priority = '';

    protected function sortableColumns(): array
    {
        return ['work_order_number', 'scheduled_for', 'status', 'priority'];
    }

    protected function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['status', 'priority'];
    }

    public function render()
    {
        $query = WorkOrder::query()
            ->with(['asset', 'vendor', 'assignee', 'complaint'])
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $i) {
                $i->where('work_order_number', 'like', "%{$this->search}%")
                    ->orWhere('title', 'like', "%{$this->search}%");
            }))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->priority !== '', fn (Builder $q) => $q->where('priority', $this->priority));

        return view('livewire.facilities.work-order-index', [
            'orders' => $this->applySort($query)->paginate($this->perPage),
        ])->title('Work orders');
    }
}
