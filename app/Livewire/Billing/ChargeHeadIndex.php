<?php

namespace App\Livewire\Billing;

use App\Livewire\Concerns\WithDataTable;
use App\Models\ChargeHead;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The heads a society bills or spends under, and the basis each one uses.
 */
#[Layout('components.layouts.app')]
class ChargeHeadIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $fund = '';

    protected function sortableColumns(): array
    {
        return ['name', 'code', 'type', 'default_rate', 'sort_order'];
    }

    protected function defaultSort(): array
    {
        return ['sort_order', 'asc'];
    }

    protected function filterProperties(): array
    {
        return ['type', 'fund'];
    }

    public function render()
    {
        $query = ChargeHead::query()
            ->with(['ledgerAccount'])
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $i) {
                $i->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%");
            }))
            ->when($this->type !== '', fn (Builder $q) => $q->where('type', $this->type))
            ->when($this->fund !== '', fn (Builder $q) => $q->where('fund', $this->fund));

        return view('livewire.billing.charge-head-index', [
            'heads' => $this->applySort($query)->paginate($this->perPage),
        ])->title('Charge heads');
    }
}
