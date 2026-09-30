<?php

namespace App\Services\Property;

use App\Models\Block;
use App\Models\Society;
use App\Models\Unit;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The society seen from above, and each building seen from the side.
 *
 * Two views, because they answer different questions. The site view says
 * where a building is; the floor stack says where a flat is inside it. A
 * committee that has never opened the arrange screen still gets a usable
 * plan, because an unpositioned block is laid out on a grid rather than
 * piled at the origin.
 *
 * This is deliberately a 2D plan. A 3D model would need per-society survey
 * data and elevations that no society has to hand, and would answer no
 * question this does not.
 */
class SitePlan
{
    /** How the plan can be coloured, and what each choice is asking. */
    public const VIEWS = [
        'occupancy' => 'Who lives there',
        'dues' => 'What is owed',
        'complaints' => 'Open complaints',
    ];

    /**
     * Every block with a position, filling in a grid for those without one.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function blocks(Society $society): Collection
    {
        $blocks = Block::query()
            ->forSociety($society)
            ->where('is_active', true)
            ->withCount('units')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $unplaced = $blocks->filter(fn (Block $b) => $b->plan_x === null || $b->plan_y === null)->values();
        $fallback = $this->autoLayout($unplaced->count());

        $index = 0;

        return $blocks->map(function (Block $block) use ($fallback, &$index) {
            $placed = $block->plan_x !== null && $block->plan_y !== null;

            if ($placed) {
                return $this->shape($block, $block->plan_x, $block->plan_y,
                    $block->plan_width ?: 20, $block->plan_height ?: 24);
            }

            $slot = $fallback[$index++] ?? ['x' => 5, 'y' => 5, 'w' => 20, 'h' => 24];

            return $this->shape($block, $slot['x'], $slot['y'], $slot['w'], $slot['h'], arranged: false);
        });
    }

    /** @return array<string, mixed> */
    private function shape(Block $block, int $x, int $y, int $w, int $h, bool $arranged = true): array
    {
        return [
            'id' => $block->id,
            'name' => $block->name,
            'kind' => $block->kind,
            'floors' => (int) ($block->floor_count ?: 1),
            'units' => $block->units_count,
            'x' => $x,
            'y' => $y,
            'width' => $w,
            'height' => $h,
            'arranged' => $arranged,
        ];
    }

    /**
     * Places n blocks on a tidy grid.
     *
     * Not a guess at the real site -- it cannot be -- but a legible default
     * that a committee can then drag into shape, and that is never wrong in
     * the way a pile of overlapping rectangles is.
     *
     * @return array<int, array{x: int, y: int, w: int, h: int}>
     */
    public function autoLayout(int $count): array
    {
        if ($count === 0) {
            return [];
        }

        $columns = (int) ceil(sqrt($count));
        $rows = (int) ceil($count / $columns);

        $gap = 4;

        // The bottom band is left for the gate, parking and garden, which are
        // seeded there. Buildings drawn over them would hide the landmarks
        // that make the plan readable in the first place.
        $usableHeight = 78;

        $width = (int) max(8, floor((100 - $gap * ($columns + 1)) / $columns));
        $height = (int) max(8, floor(($usableHeight - $gap * ($rows + 1)) / $rows));

        $slots = [];

        for ($i = 0; $i < $count; $i++) {
            $column = $i % $columns;
            $row = intdiv($i, $columns);

            $slots[] = [
                'x' => $gap + $column * ($width + $gap),
                'y' => $gap + $row * ($height + $gap),
                'w' => $width,
                'h' => $height,
            ];
        }

        return $slots;
    }

    /**
     * One building's units, stacked by floor, top floor first.
     *
     * @return Collection<int, array{floor: int, label: string, units: Collection<int, Unit>}>
     */
    public function floorStack(Block $block): Collection
    {
        $units = Unit::query()
            ->where('block_id', $block->id)
            ->with('block')
            ->withCount(['complaints as open_complaints_count' => fn ($q) => $q->open()])
            ->withSum(['invoices as balance_due' => fn ($q) => $q->open()], 'balance')
            ->orderByDesc('floor')
            ->orderByRaw('COALESCE(plan_position, 9999)')
            ->orderBy('unit_number')
            ->get();

        return $units
            ->groupBy(fn (Unit $unit) => (int) ($unit->floor ?? 0))
            ->sortKeysDesc()
            ->map(fn (Collection $group, int $floor) => [
                'floor' => $floor,
                'label' => $this->floorLabel($floor),
                'units' => $group->values(),
            ])
            ->values();
    }

    /** Indian buildings number the entry level "ground", not 0 or 1. */
    public function floorLabel(int $floor): string
    {
        return match (true) {
            $floor < 0 => 'Basement '.abs($floor),
            $floor === 0 => 'Ground',
            default => $this->ordinal($floor).' floor',
        };
    }

    private function ordinal(int $n): string
    {
        $suffix = match (true) {
            in_array($n % 100, [11, 12, 13], true) => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };

        return $n.$suffix;
    }

    /**
     * The tone a unit is drawn in, for the chosen view.
     *
     * Tones are the interface's own status colours, which already carry a
     * text label wherever they are used, so the plan never asks anyone to
     * tell meaning from hue alone.
     */
    public function toneFor(Unit $unit, string $view): string
    {
        return match ($view) {
            'dues' => match (true) {
                (float) ($unit->balance_due ?? 0) <= 0 => 'positive',
                (float) $unit->balance_due < 5000 => 'caution',
                default => 'critical',
            },
            'complaints' => ($unit->open_complaints_count ?? 0) > 0 ? 'caution' : 'neutral',
            default => match ($unit->occupancy_status) {
                'owner_occupied' => 'positive',
                'rented' => 'info',
                'vacant' => 'caution',
                'under_construction', 'locked' => 'neutral',
                default => 'neutral',
            },
        };
    }

    /**
     * What a unit's colour means in the chosen view, in words.
     *
     * Read out by screen readers and shown on hover, so the plan is not a
     * colour puzzle.
     */
    public function meaningFor(Unit $unit, string $view): string
    {
        return match ($view) {
            'dues' => (float) ($unit->balance_due ?? 0) > 0
                ? Money::format((float) $unit->balance_due).' outstanding'
                : 'Nothing outstanding',
            'complaints' => ($unit->open_complaints_count ?? 0) > 0
                ? $unit->open_complaints_count.' open '.Str::plural('complaint', $unit->open_complaints_count)
                : 'No open complaints',
            default => ucwords(str_replace('_', ' ', (string) $unit->occupancy_status)),
        };
    }

    /**
     * The legend for a view: every tone, and what it stands for.
     *
     * @return array<int, array{tone: string, label: string}>
     */
    public function legendFor(string $view): array
    {
        return match ($view) {
            'dues' => [
                ['tone' => 'positive', 'label' => 'Paid up'],
                ['tone' => 'caution', 'label' => 'Under ₹5,000 due'],
                ['tone' => 'critical', 'label' => '₹5,000 or more due'],
            ],
            'complaints' => [
                ['tone' => 'caution', 'label' => 'Has an open complaint'],
                ['tone' => 'neutral', 'label' => 'Nothing open'],
            ],
            default => [
                ['tone' => 'positive', 'label' => 'Owner occupied'],
                ['tone' => 'info', 'label' => 'Rented'],
                ['tone' => 'caution', 'label' => 'Vacant'],
                ['tone' => 'neutral', 'label' => 'Locked or under construction'],
            ],
        };
    }
}
