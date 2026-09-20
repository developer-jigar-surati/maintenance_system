<?php

namespace App\Livewire\People;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Society staff, their department and verification status.
 */
#[Layout('components.layouts.app')]
class StaffIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $department = '';

    #[Url(except: '')]
    public string $status = '';

    protected function sortableColumns(): array
    {
        return ['name', 'department', 'joined_on', 'status'];
    }

    protected function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    protected function filterProperties(): array
    {
        return ['department', 'status'];
    }

    public function render()
    {
        $query = Staff::query()
            ->with(['vendor'])
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $i) {
                $i->where('name', 'like', "%{$this->search}%")
                ->orWhere('employee_code', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%");
            }))
            ->when($this->department !== '', fn (Builder $q) => $q->where('department', $this->department))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status));

        return view('livewire.people.staff-index', [
            'staff' => $this->applySort($query)->paginate($this->perPage),
        ])->title('Staff');
    }
}
