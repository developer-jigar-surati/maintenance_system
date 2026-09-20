<?php

namespace App\Livewire\Property;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Block;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class UnitIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $blockId = '';

    #[Url(except: '')]
    public string $occupancy = '';

    #[Url(except: '')]
    public string $type = '';

    protected function sortableColumns(): array
    {
        return ['unit_number', 'floor', 'carpet_area', 'occupancy_status'];
    }

    protected function defaultSort(): array
    {
        return ['unit_number', 'asc'];
    }

    protected function filterProperties(): array
    {
        return ['blockId', 'occupancy', 'type'];
    }

    public function render()
    {
        $query = Unit::query()
            ->with(['block', 'activeResidents.user'])
            // Outstanding is aggregated in SQL rather than per row, so a
            // thousand-unit society still renders in one extra query.
            ->withSum(['invoices as outstanding' => fn ($q) => $q->open()], 'balance')
            ->search($this->search)
            ->when($this->blockId !== '', fn (Builder $q) => $q->where('block_id', $this->blockId))
            ->when($this->occupancy !== '', fn (Builder $q) => $q->where('occupancy_status', $this->occupancy))
            ->when($this->type !== '', fn (Builder $q) => $q->where('type', $this->type));

        return view('livewire.property.unit-index', [
            'units' => $this->applySort($query)->paginate($this->perPage),
            'blocks' => Block::orderBy('name')->get(),
            'society' => app(\App\Support\SocietyContext::class)->check(),
        ])->title('Units');
    }
}
