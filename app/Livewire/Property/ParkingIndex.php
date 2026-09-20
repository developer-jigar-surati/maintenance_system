<?php

namespace App\Livewire\Property;

use App\Livewire\Concerns\WithDataTable;
use App\Models\ParkingSlot;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Parking slots and which unit each one is allotted to.
 */
#[Layout('components.layouts.app')]
class ParkingIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    protected function sortableColumns(): array
    {
        return ['code', 'status'];
    }

    protected function defaultSort(): array
    {
        return ['code', 'asc'];
    }

    protected function filterProperties(): array
    {
        return ['status'];
    }

    public function render()
    {
        $query = ParkingSlot::query()
            ->with(['unit.block', 'block'])
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $i) {
                $i->where('code', 'like', "%{$this->search}%");
            }))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status));

        return view('livewire.property.parking-index', [
            'slots' => $this->applySort($query)->paginate($this->perPage),
        ])->title('Parking');
    }
}
