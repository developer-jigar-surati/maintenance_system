<?php

namespace App\Livewire\Billing;

use App\Enums\Permission;
use App\Models\BillingPlan;
use App\Models\ChargeHead;
use App\Models\Unit;
use App\Models\UnitChargeOverride;
use App\Services\Billing\AdvanceOffer;
use App\Services\Billing\ChargeCalculator;
use App\Support\SocietyContext;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * What one home pays, and the amounts set for it by hand.
 *
 * A separate component rather than more of the unit page, because it carries
 * its own form and its own permission: anyone who may see a unit may see what
 * it pays, but only the committee may change it.
 *
 * Most homes pay their building's amount and nothing here needs touching. The
 * exceptions are the point: the flat whose owner was given a concession at an
 * AGM, the one left out of the lift charge because it is on the ground floor.
 * Those decisions are real and were previously kept in somebody's head, which
 * is why they were forgotten, applied inconsistently, and argued about.
 */
class UnitCharges extends Component
{
    public Unit $unit;

    /** The head whose amount for this home is open for editing. */
    public ?int $editingHeadId = null;

    public ?float $amount = null;

    public bool $isExempt = false;

    public string $reason = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public function edit(int $headId): void
    {
        Gate::authorize(Permission::BILLING_MANAGE);

        $head = ChargeHead::findOrFail($headId);
        $existing = UnitChargeOverride::query()
            ->where('unit_id', $this->unit->id)
            ->where('charge_head_id', $head->id)
            ->first();

        $this->editingHeadId = $head->id;

        // Opening with what this home pays today, rather than an empty box:
        // the usual edit is a nudge away from the building's amount, and
        // typing it again from scratch is how a digit goes missing.
        $this->amount = $existing?->rate !== null
            ? (float) $existing->rate
            : app(ChargeCalculator::class)->explain($this->unit, $head)['rate'];

        $this->isExempt = (bool) $existing?->is_exempt;
        $this->reason = (string) ($existing?->reason ?? '');
        $this->startsOn = $existing?->effective_from?->toDateString() ?? '';
        $this->endsOn = $existing?->effective_to?->toDateString() ?? '';

        $this->resetValidation();
        $this->dispatch('open-modal', 'home-amount');
    }

    public function save(): void
    {
        Gate::authorize(Permission::BILLING_MANAGE);

        $this->validate([
            'amount' => ($this->isExempt ? 'nullable' : 'required').'|numeric|min:0|max:10000000',
            'isExempt' => 'boolean',
            'reason' => 'nullable|string|max:160',
            'startsOn' => 'nullable|date',
            'endsOn' => 'nullable|date|after_or_equal:startsOn',
        ], [
            'amount.required' => 'Enter what this home pays, or mark it as not charged.',
            'endsOn.after_or_equal' => 'The end date cannot come before the start date.',
        ]);

        $head = ChargeHead::findOrFail($this->editingHeadId);

        UnitChargeOverride::updateOrCreate(
            ['unit_id' => $this->unit->id, 'charge_head_id' => $head->id],
            [
                'society_id' => app(SocietyContext::class)->check()->id,
                'rate' => $this->isExempt ? null : $this->amount,
                'is_exempt' => $this->isExempt,
                'reason' => $this->reason ?: null,
                'effective_from' => $this->startsOn ?: null,
                'effective_to' => $this->endsOn ?: null,
                'set_by' => auth()->id(),
            ],
        );

        $this->editingHeadId = null;
        $this->dispatch('close-modal', 'home-amount');
        $this->dispatch('notify',
            message: $head->name.' set for '.$this->unit->label.'.',
            detail: 'Bills raised from now on use it. Bills already issued are unchanged.',
            tone: 'positive');
    }

    /** Puts the home back on whatever its building or size says. */
    public function useSharedAmount(int $headId): void
    {
        Gate::authorize(Permission::BILLING_MANAGE);

        $head = ChargeHead::findOrFail($headId);

        UnitChargeOverride::query()
            ->where('unit_id', $this->unit->id)
            ->where('charge_head_id', $head->id)
            ->delete();

        $this->dispatch('notify',
            message: $this->unit->label.' is back on the shared amount for '.$head->name.'.',
            tone: 'positive');
    }

    /**
     * One line of the card: the amount, and what decided it.
     *
     * @return array<string, mixed>
     */
    private function lineFor(ChargeHead $head, ?BillingPlan $plan, ChargeCalculator $calculator): array
    {
        $resolved = $calculator->explain($this->unit, $head, $plan);

        return $resolved + [
            'head' => $head,
            'plan' => $plan,
            // A home left out of a charge has no rate to trace, so the source
            // falls back to the society's. Saying "every home in the society"
            // next to "Not charged" would read as a contradiction.
            'origin' => $resolved['exempt']
                ? 'Left out for this home'
                : $this->originLabel($resolved['source'], $plan),
        ];
    }

    /** Plain words for where an amount came from. */
    private function originLabel(string $source, ?BillingPlan $plan): string
    {
        $block = $this->unit->block?->name;
        $size = $this->unit->configuration;

        return match ($source) {
            'unit' => 'Set for this home',
            'block_configuration' => trim(($block ?? 'This building').' '.$size),
            'configuration' => 'Every '.$size.' in the society',
            'block' => 'All of '.($block ?? 'this building'),
            'plan' => $plan?->name ?? 'This plan',
            default => 'Every home in the society',
        };
    }

    public function render()
    {
        $calculator = app(ChargeCalculator::class);
        $plans = BillingPlan::with(['chargeHeads', 'advanceDiscounts'])->active()->orderBy('name')->get();

        $lines = [];

        foreach ($plans as $plan) {
            foreach ($plan->chargeHeads as $head) {
                // A head on two plans is one line: residents think about what
                // they pay for water, not which plan carries it.
                $lines[$head->id] ??= $this->lineFor($head, $plan, $calculator);
            }
        }

        // Anything already settled for this home that no plan bills any more,
        // so an exception cannot go on existing invisibly.
        $settled = UnitChargeOverride::query()
            ->where('unit_id', $this->unit->id)
            ->pluck('charge_head_id');

        foreach (ChargeHead::whereIn('id', $settled)->whereNotIn('id', array_keys($lines))->get() as $head) {
            $lines[$head->id] = $this->lineFor($head, null, $calculator);
        }

        $lines = collect($lines)
            ->sortBy(fn ($line) => [(int) $line['head']->sort_order, (string) $line['head']->name])
            ->values();

        $billed = $lines->where('applies', true)->where('exempt', false);
        $plan = $plans->first(fn ($p) => $p->chargeHeads->isNotEmpty());

        return view('livewire.billing.unit-charges', [
            'lines' => $lines,
            'periodTotal' => round((float) $billed->sum('gross'), 2),
            'plan' => $plan,
            'offer' => $plan ? app(AdvanceOffer::class)->for($this->unit, $plan) : null,
            'canManage' => auth()->user()->can(Permission::BILLING_MANAGE),
            'editing' => $this->editingHeadId ? ChargeHead::find($this->editingHeadId) : null,
            // The line being edited, so the dialog can say what the number it
            // is asking for means. A rate per square foot and an amount per
            // bill are not the same thing and must not look the same.
            'editingLine' => $this->editingHeadId ? $lines->firstWhere('head.id', $this->editingHeadId) : null,
            'areaUnit' => $this->unit->society?->areaUnitLabel() ?? 'sq.ft.',
        ]);
    }
}
