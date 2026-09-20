<?php

namespace App\Services\Billing;

use App\Models\BillingPlan;
use App\Models\ChargeHead;
use App\Models\Unit;
use App\Models\UnitChargeOverride;
use Illuminate\Support\Carbon;

/**
 * Works out what a single charge head costs a single unit.
 *
 * The rate is resolved in order of specificity -- a unit-level override beats
 * the plan's rate, which beats the head's own default -- and the quantity
 * comes from whatever the head is billed on (area, bedrooms, members).
 */
class ChargeCalculator
{
    /**
     * Returns null when the head does not apply to this unit at all, so the
     * caller can skip the line rather than write a zero.
     *
     * @return array{description: string, basis: string, quantity: float, rate: float, tax_rate: float}|null
     */
    public function for(Unit $unit, ChargeHead $head, ?BillingPlan $plan = null, ?Carbon $on = null): ?array
    {
        $on ??= now();

        if (! $head->appliesToUnit($unit)) {
            return null;
        }

        $override = $this->overrideFor($unit, $head, $on);

        if ($override?->is_exempt) {
            return null;
        }

        $basis = $this->resolveBasis($head, $plan);
        $rate = $this->resolveRate($head, $plan, $override);
        $quantity = $this->quantityFor($unit, $head, $basis);

        // A per-sqft head on a unit with no recorded area would silently bill
        // zero; skipping is safer than quietly under-charging.
        if ($quantity <= 0) {
            return null;
        }

        return [
            'description' => $this->describe($head, $basis, $quantity, $rate, $unit),
            'basis' => $basis,
            'quantity' => round($quantity, 4),
            'rate' => round($rate, 4),
            'tax_rate' => $head->is_taxable ? (float) $head->tax_rate : 0.0,
        ];
    }

    private function overrideFor(Unit $unit, ChargeHead $head, Carbon $on): ?UnitChargeOverride
    {
        return UnitChargeOverride::query()
            ->where('unit_id', $unit->id)
            ->where('charge_head_id', $head->id)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $on))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on))
            ->first();
    }

    private function resolveBasis(ChargeHead $head, ?BillingPlan $plan): string
    {
        return $plan?->chargeHeads->firstWhere('id', $head->id)?->pivot?->basis
            ?: $head->basis;
    }

    private function resolveRate(ChargeHead $head, ?BillingPlan $plan, ?UnitChargeOverride $override): float
    {
        if ($override && $override->rate !== null) {
            return (float) $override->rate;
        }

        $planRate = $plan?->chargeHeads->firstWhere('id', $head->id)?->pivot?->rate;

        return (float) ($planRate ?? $head->default_rate);
    }

    /** The multiplier the rate is applied to, per the head's billing basis. */
    private function quantityFor(Unit $unit, ChargeHead $head, string $basis): float
    {
        return match ($basis) {
            'per_sqft' => $unit->areaFor($head->area_basis),
            'per_bedroom' => (float) max(1, (int) $unit->bedrooms),
            'per_member' => (float) max(1, $unit->activeResidents()->count()),
            'per_vehicle' => (float) $unit->vehicles()->where('is_active', true)->count(),
            default => 1.0, // fixed_per_unit and manual
        };
    }

    private function describe(ChargeHead $head, string $basis, float $quantity, float $rate, Unit $unit): string
    {
        $trim = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

        return match ($basis) {
            'per_sqft' => sprintf(
                '%s (%s %s @ %s)',
                $head->name, $trim($quantity), $unit->society?->areaUnitLabel() ?? 'sq.ft.', $trim($rate)
            ),
            'per_bedroom' => sprintf('%s (%s bedrooms @ %s)', $head->name, $trim($quantity), $trim($rate)),
            'per_member' => sprintf('%s (%s members @ %s)', $head->name, $trim($quantity), $trim($rate)),
            'per_vehicle' => sprintf('%s (%s vehicles @ %s)', $head->name, $trim($quantity), $trim($rate)),
            default => $head->name,
        };
    }
}
