<?php

namespace App\Services\Billing;

use App\Models\BillingPlan;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What a home saves by paying the whole year in one go.
 *
 * Societies have always offered this and never recorded it: "12,000 a month,
 * or 10,000 if you pay the year together" lives in the treasurer's head, so
 * residents hear a different figure depending on who they ask. The discount
 * is stored as a percentage against the plan, with a row per building where a
 * wing was promised something different, and the money is worked out here
 * from whatever that home actually pays.
 */
class AdvanceOffer
{
    public function __construct(private ChargeCalculator $charges) {}

    /**
     * Returns null when this plan does not offer anything to this home.
     *
     * @return array{periods: int, percent: float, period_amount: float, full: float, payable: float, saving: float}|null
     */
    public function for(Unit $unit, BillingPlan $plan, ?Carbon $on = null): ?array
    {
        $on ??= now();

        $periods = (int) $plan->advance_periods;
        $percent = $plan->advanceDiscountFor($unit->block_id);

        if ($periods < 2 || $percent === null || $percent <= 0) {
            return null;
        }

        $periodAmount = $this->periodAmountFor($unit, $plan, $on);

        if ($periodAmount <= 0) {
            return null;
        }

        $full = round($periodAmount * $periods, 2);
        $payable = round($full * (1 - ($percent / 100)), 2);

        return [
            'periods' => $periods,
            'percent' => $percent,
            'period_amount' => $periodAmount,
            'full' => $full,
            'payable' => $payable,
            'saving' => round($full - $payable, 2),
        ];
    }

    /**
     * What a year normally costs, for the society and for each building.
     *
     * The committee types what a year costs if paid up front, and that only
     * means anything next to what it costs otherwise. Working it out from
     * every flat would be hundreds of queries for one dialog, so one flat
     * stands in for each building and size combination and the answer is
     * weighted by how many flats that combination holds. The figures are a
     * reference for the dialog, never something a bill is raised from.
     *
     * @return array{society: float, blocks: array<int, float>}
     */
    public function typicalYearly(BillingPlan $plan): array
    {
        $periods = $plan->periodsPerYear();

        if ($periods < 1) {
            return ['society' => 0.0, 'blocks' => []];
        }

        $groups = Unit::query()
            ->where('society_id', $plan->society_id)
            ->billable()
            ->get(['id', 'block_id', 'configuration'])
            ->groupBy(fn ($unit) => $unit->block_id.'|'.$unit->configuration);

        $samples = $this->sample($plan, $groups);

        $weighted = function (Collection $rows) use ($periods): float {
            $flats = (float) $rows->sum('flats');

            if ($flats <= 0) {
                return 0.0;
            }

            return round($rows->sum(fn ($r) => $r['period'] * $r['flats']) / $flats * $periods, 2);
        };

        return [
            'society' => $weighted($samples),
            'blocks' => $samples
                ->filter(fn ($r) => $r['block_id'] !== null)
                ->groupBy('block_id')
                ->map($weighted)
                ->all(),
        ];
    }

    /**
     * One real flat per building and size, with the amount it pays.
     *
     * @param  Collection<string, Collection<int, Unit>>  $groups
     * @return Collection<int, array{block_id: int|null, flats: int, period: float}>
     */
    private function sample(BillingPlan $plan, Collection $groups): Collection
    {
        $ids = $groups->map(fn ($group) => $group->first()->id)->values();

        $representatives = Unit::query()
            ->whereIn('id', $ids)
            ->with(['block', 'society'])
            ->get()
            ->keyBy('id');

        return $groups
            ->map(function ($group) use ($representatives, $plan) {
                $unit = $representatives->get($group->first()->id);

                return $unit === null ? null : [
                    'block_id' => $unit->block_id,
                    'flats' => $group->count(),
                    'period' => $this->periodAmountFor($unit, $plan),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * A yearly amount expressed as a discount off what the year normally costs.
     *
     * Static, because the committee types the amount in two different places
     * (the setup wizard and the billing plan screen) and both must turn it
     * into the same percentage. Clamped, so a typo cannot store a discount
     * larger than the bill or a negative one.
     */
    public static function percentOff(float $normalYear, float $paidUpFront): float
    {
        if ($normalYear <= 0) {
            return 0.0;
        }

        return max(0.0, min(90.0, round((1 - ($paidUpFront / $normalYear)) * 100, 2)));
    }

    /** The same sum backwards, for showing a stored percentage as money. */
    public static function amountAfter(float $normalYear, float $percent): ?float
    {
        return $normalYear <= 0 ? null : round($normalYear * (1 - ($percent / 100)), 2);
    }

    /** What one bill under this plan comes to for this home, tax included. */
    public function periodAmountFor(Unit $unit, BillingPlan $plan, ?Carbon $on = null): float
    {
        $on ??= now();

        return round($plan->chargeHeads->sum(function ($head) use ($unit, $plan, $on) {
            $line = $this->charges->explain($unit, $head, $plan, $on);

            return $line['applies'] && ! $line['exempt'] ? $line['gross'] : 0.0;
        }), 2);
    }
}
