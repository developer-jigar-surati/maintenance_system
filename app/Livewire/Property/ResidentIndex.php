<?php

namespace App\Livewire\Property;

use App\Livewire\Concerns\WithDataTable;
use App\Models\UnitResident;
use App\Models\User;
use App\Services\Property\Occupancy;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Everyone who lives, or has lived, in the society.
 *
 * Past residencies are kept rather than deleted, so this is also the record
 * of who was in a flat when -- which is what a committee needs when an old
 * bill, a deposit or a police verification comes back up.
 */
#[Layout('components.layouts.app')]
class ResidentIndex extends Component
{
    use WithDataTable;

    /** Whose occupancy history is open. */
    public ?int $historyForUserId = null;

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

    /** Opens one person's whole history, across every unit they have had. */
    public function showHistory(int $userId): void
    {
        $this->historyForUserId = $userId;

        $this->dispatch('open-modal', 'resident-history');
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

        $person = $this->historyForUserId ? User::find($this->historyForUserId) : null;

        return view('livewire.property.resident-index', [
            'residents' => $this->applySort($query)->paginate($this->perPage),
            'person' => $person,
            'personHistory' => $person
                ? app(Occupancy::class)->historyForUser($person)
                : collect(),
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
