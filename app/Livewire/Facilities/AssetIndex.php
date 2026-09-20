<?php

namespace App\Livewire\Facilities;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Physical assets with their condition, warranty and AMC coverage.
 */
#[Layout('components.layouts.app')]
class AssetIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $status = '';

    protected function sortableColumns(): array
    {
        return ['name', 'category', 'status', 'purchase_date'];
    }

    protected function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    protected function filterProperties(): array
    {
        return ['category', 'status'];
    }

    public function render()
    {
        $query = Asset::query()
            ->with(['block', 'vendor', 'amcContracts'])
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $i) {
                $i->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%")
                    ->orWhere('serial_number', 'like', "%{$this->search}%");
            }))
            ->when($this->category !== '', fn (Builder $q) => $q->where('category', $this->category))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status));

        return view('livewire.facilities.asset-index', [
            'assets' => $this->applySort($query)->paginate($this->perPage),
        ])->title('Assets & AMC');
    }
}
