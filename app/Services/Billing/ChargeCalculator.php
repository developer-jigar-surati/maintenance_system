<?php

namespace App\Services\Billing;

use App\Models\BillingPlan;
use App\Models\ChargeHead;
use App\Models\ChargeRate;
use App\Models\Unit;
use App\Models\UnitChargeOverride;
use Illuminate\Support\Carbon;

/**
 * Works out what a single charge head costs a single unit.
 *
 * The rate is resolved from the most specific thing that says something about
 * this unit down to the most general:
 *
 *     this flat
 *       ->  its building and size together
 *       ->  its size of home
 *       ->  its building
 *       ->  the plan
 *       ->  the head
 *
 * The pair matters because real societies use it. One has A at 12,000, B at
 * 11,000 and the GHI block at 8,000, and inside each of those the 2BHK and
 * the 3BHK differ again. Treating building and size as alternatives loses
 * whichever one you did not pick.
 *
 * which is how a committee actually decides: one amount for the society, a
 * different one for the wing with the lift, and a handful of flats settled
 * individually. The quantity comes from whatever the head is billed on
 * (area, bedrooms, members, vehicles).
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
        $rate = $this->resolveRate($unit, $head, $plan, $override, $on);
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

    private function resolveRate(
        Unit $unit,
        ChargeHead $head,
        ?BillingPlan $plan,
        ?UnitChargeOverride $override,
        Carbon $on,
    ): float {
        // This flat specifically.
        if ($override && $override->rate !== null) {
            return (float) $override->rate;
        }

        // Its size of home, then its building.
        $scoped = $this->scopedRateFor($unit, $head, $on);

        if ($scoped !== null) {
            return $scoped;
        }

        $planRate = $plan?->chargeHeads->firstWhere('id', $head->id)?->pivot?->rate;

        return (float) ($planRate ?? $head->default_rate);
    }

    /**
     * A rate set for the slice of the society this unit falls in.
     *
     * Taken in order of how much each one is saying about this particular
     * flat. "A wing, 3BHK" is the most deliberate statement anyone can make
     * short of naming the flat itself, so it is preferred over "3BHK
     * anywhere", which in turn is preferred over "anything in A wing".
     */
    private function scopedRateFor(Unit $unit, ChargeHead $head, Carbon $on): ?float
    {
        $block = $unit->block_id;
        $size = $unit->configuration;

        if ($block === null && $size === null) {
            return null;
        }

        $rates = ChargeRate::query()
            ->where('charge_head_id', $head->id)
            ->inForceOn($on)
            ->where(function ($q) use ($block, $size) {
                $q->where(function ($i) use ($block, $size) {
                    $i->where('scope', 'block_configuration')
                        ->where('block_id', $block)
                        ->where('configuration', $size);
                })
                    ->orWhere(fn ($i) => $i->where('scope', 'configuration')->where('configuration', $size))
                    ->orWhere(fn ($i) => $i->where('scope', 'block')->where('block_id', $block));
            })
            ->get();

        $preference = [
            'block_configuration' => $block !== null && $size !== null,
            'configuration' => $size !== null,
            'block' => $block !== null,
        ];

        foreach ($preference as $scope => $applies) {
            $match = $applies ? $rates->firstWhere('scope', $scope) : null;

            if ($match !== null) {
                return (float) $match->rate;
            }
        }

        return null;
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
