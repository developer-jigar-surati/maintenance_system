<?php

namespace App\Livewire\Billing;

use App\Enums\Permission;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Block;
use App\Models\ChargeHead;
use App\Models\ChargeRate;
use App\Models\Unit;
use App\Services\Billing\RateWriter;
use App\Support\SocietyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
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

    /**
     * The head whose rates are open for editing.
     *
     * Rates get revised at every general body meeting, so setting them once
     * during setup and never again is not enough. This is the same question
     * the setup wizard asks, in the place a committee comes back to.
     */
    public ?int $editingHeadId = null;

    public string $rateBasis = 'flat';

    public ?float $flatAmount = null;

    public array $blockAmounts = [];

    public array $sizeAmounts = [];

    public array $gridAmounts = [];

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

    /** Opens a head's rates, reading back whatever shape they are in. */
    public function editRates(int $headId): void
    {
        Gate::authorize(Permission::BILLING_MANAGE);

        $head = ChargeHead::findOrFail($headId);

        $this->editingHeadId = $head->id;
        $this->reset(['flatAmount', 'blockAmounts', 'sizeAmounts', 'gridAmounts']);

        $rates = ChargeRate::where('charge_head_id', $head->id)->get();

        // The shape of what is stored tells us which question was answered.
        $this->rateBasis = match (true) {
            $head->basis === 'per_sqft' => 'by_area',
            $rates->contains('scope', 'block_configuration') => 'by_block_and_size',
            $rates->contains('scope', 'configuration') => 'by_size',
            $rates->contains('scope', 'block') => 'by_block',
            default => 'flat',
        };

        $this->flatAmount = (float) $head->default_rate;

        foreach ($rates as $rate) {
            match ($rate->scope) {
                'block' => $this->blockAmounts[$rate->block_id] = (float) $rate->rate,
                'configuration' => $this->sizeAmounts[$rate->configuration] = (float) $rate->rate,
                'block_configuration' => $this->gridAmounts[$rate->block_id.'|'.$rate->configuration] = (float) $rate->rate,
                default => null,
            };
        }

        $this->dispatch('open-modal', 'edit-rates');
    }

    public function saveRates(): void
    {
        Gate::authorize(Permission::BILLING_MANAGE);

        $this->validate([
            'rateBasis' => 'required|in:flat,by_block,by_size,by_block_and_size,by_area',
            'flatAmount' => 'nullable|numeric|min:0|max:10000000',
            'blockAmounts.*' => 'nullable|numeric|min:0|max:10000000',
            'sizeAmounts.*' => 'nullable|numeric|min:0|max:10000000',
            'gridAmounts.*' => 'nullable|numeric|min:0|max:10000000',
        ]);

        $society = app(SocietyContext::class)->check();
        $head = ChargeHead::findOrFail($this->editingHeadId);

        app(RateWriter::class)->write($society, $head, [
            'basis' => $this->rateBasis,
            'flat' => $this->flatAmount,
            'blocks' => $this->blockAmounts,
            'sizes' => $this->sizeAmounts,
            'grid' => $this->gridAmounts,
        ]);

        $this->editingHeadId = null;
        $this->dispatch('close-modal', 'edit-rates');
        $this->dispatch('notify',
            message: $head->name.' rates saved.',
            detail: 'Bills raised from now on use them. Bills already issued are unchanged.',
            tone: 'positive');
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
            'editing' => $this->editingHeadId ? ChargeHead::find($this->editingHeadId) : null,
            'canManage' => auth()->user()->can(Permission::BILLING_MANAGE),
            'blocks' => Block::orderBy('sort_order')->orderBy('name')->get(),
            'sizes' => Unit::query()
                ->whereNotNull('configuration')->where('configuration', '!=', '')
                ->distinct()->orderBy('configuration')->pluck('configuration'),
            // How many slices each head is split into, so the list says at a
            // glance which ones are not a single number.
            'sliceCounts' => ChargeRate::query()
                ->selectRaw('charge_head_id, count(*) as total')
                ->groupBy('charge_head_id')
                ->pluck('total', 'charge_head_id'),
        ])->title('Charge heads');
    }
}
