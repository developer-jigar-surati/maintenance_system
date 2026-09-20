<?php

namespace App\Livewire\Accounting;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The society's supplier directory, with contract dates and tax details.
 */
#[Layout('components.layouts.app')]
class VendorIndex extends Component
{
    use WithDataTable;

    protected function sortableColumns(): array
    {
        return ['name', 'category', 'contract_end'];
    }

    protected function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    protected function filterProperties(): array
    {
        return [];
    }

    public function render()
    {
        $query = Vendor::query()
            ->with([])
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $i) {
                $i->where('name', 'like', "%{$this->search}%")
                ->orWhere('category', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%");
            }));

        return view('livewire.accounting.vendor-index', [
            'vendors' => $this->applySort($query)->paginate($this->perPage),
        ])->title('Vendors');
    }
}
