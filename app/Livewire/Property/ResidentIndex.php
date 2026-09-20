<?php

namespace App\Livewire\Property;

use App\Livewire\Concerns\WithDataTable;
use App\Models\UnitResident;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ResidentIndex extends Component
{
    use WithDataTable;

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $relation = '';

    protected function sortableColumns(): array
    {
        return ['relation', 'start_date', 'agreement_end_date'];
    }

    protected function defaultSort(): array
    {
        return ['id', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['status', 'relation'];
    }

    public function render()
    {
        $query = UnitResident::query()
            ->with(['user', 'unit.block'])
            ->when($this->search !== '', fn (Builder $q) => $q
                ->whereHas('user', fn (Builder $u) => $u
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%"))
                ->orWhereHas('unit', fn (Builder $x) => $x->where('unit_number', 'like', "%{$this->search}%")))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->relation !== '', fn (Builder $q) => $q->where('relation', $this->relation));

        return view('livewire.property.resident-index', [
            'residents' => $this->applySort($query)->paginate($this->perPage),
            // Tenancies running out soon, so the committee can chase renewals.
            'expiringSoon' => UnitResident::query()
                ->active()
                ->tenants()
                ->whereNotNull('agreement_end_date')
                ->whereBetween('agreement_end_date', [now(), now()->addDays(60)])
                ->count(),
        ])->title('Residents');
    }
}
