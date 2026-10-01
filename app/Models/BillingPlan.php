<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * How often a society bills, and which charge heads each bill carries.
 */
class BillingPlan extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'next_run_on' => 'date',
            'last_run_at' => 'datetime',
            'generate_on_day' => 'integer',
            'due_after_days' => 'integer',
            'auto_generate' => 'boolean',
            'auto_issue' => 'boolean',
            'is_active' => 'boolean',
            'advance_periods' => 'integer',
            'advance_discount_percent' => 'decimal:2',
        ];
    }

    public function chargeHeads(): BelongsToMany
    {
        return $this->belongsToMany(ChargeHead::class)
            ->withPivot(['rate', 'basis', 'sort_order'])
            ->withTimestamps()
            ->orderBy('billing_plan_charge_head.sort_order');
    }

    public function advanceDiscounts(): HasMany
    {
        return $this->hasMany(AdvanceDiscount::class);
    }

    public function lateFeeRule(): BelongsTo
    {
        return $this->belongsTo(LateFeeRule::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Months covered by one bill under this plan. */
    public function cycleMonths(): int
    {
        return match ($this->cycle) {
            'monthly' => 1,
            'bi_monthly' => 2,
            'quarterly' => 3,
            'half_yearly' => 6,
            'yearly' => 12,
            default => 0,
        };
    }

    /** Start and end of the period a run dated $on should bill for. */
    public function periodFor(Carbon $on): array
    {
        $months = $this->cycleMonths();

        if ($months === 0) {
            return [$on->copy()->startOfDay(), $on->copy()->endOfDay()];
        }

        // Align the period to whole cycles counted from the plan's start date,
        // so a quarterly plan starting in April bills Apr-Jun, Jul-Sep, etc.
        $anchor = $this->starts_on->copy()->startOfMonth();
        $elapsed = $anchor->diffInMonths($on->copy()->startOfMonth());
        $cyclesDone = intdiv((int) $elapsed, $months);

        $start = $anchor->copy()->addMonths($cyclesDone * $months);
        $end = $start->copy()->addMonths($months)->subDay()->endOfDay();

        return [$start, $end];
    }

    public function advanceSchedule(Carbon $from): void
    {
        $months = $this->cycleMonths();

        $this->forceFill([
            'last_run_at' => now(),
            'next_run_on' => $months === 0 ? null : $from->copy()->addMonths($months)->startOfDay(),
        ])->save();
    }

    public function isDue(?Carbon $on = null): bool
    {
        $on ??= now();

        return $this->is_active
            && $this->auto_generate
            && $this->next_run_on !== null
            && $this->next_run_on->lte($on)
            && ($this->ends_on === null || $this->ends_on->gte($on));
    }

    public function cycleLabel(): string
    {
        return ucwords(str_replace('_', '-', $this->cycle));
    }

    /**
     * The stretch of time one bill covers, as a noun.
     *
     * "Every monthly" is not English. A total under a list of charges needs to
     * say "every month", and that is not the same word as the cycle's name.
     */
    public function periodNoun(): string
    {
        return match ($this->cycle) {
            'monthly' => 'month',
            'bi_monthly' => 'two months',
            'quarterly' => 'quarter',
            'half_yearly' => 'half year',
            'yearly' => 'year',
            default => 'bill',
        };
    }

    /** How many bills under this plan add up to a year. */
    public function periodsPerYear(): int
    {
        $months = $this->cycleMonths();

        return $months === 0 ? 0 : intdiv(12, $months);
    }

    /** Whether paying up front gets anybody anything. */
    public function offersAdvance(): bool
    {
        return (int) $this->advance_periods > 1
            && ((float) $this->advance_discount_percent > 0 || $this->advanceDiscounts()->exists());
    }

    /**
     * The prepayment discount that applies to a building.
     *
     * A building's own figure wins, including a deliberate zero: a wing told
     * in a meeting that it gets no discount should not quietly inherit the
     * society's.
     */
    public function advanceDiscountFor(?int $blockId): ?float
    {
        if ((int) $this->advance_periods < 2) {
            return null;
        }

        // Works whether or not the relation was eager loaded, because this is
        // called from a page that lists plans and from one that shows a flat.
        $forBlock = match (true) {
            $blockId === null => null,
            $this->relationLoaded('advanceDiscounts') => $this->advanceDiscounts->firstWhere('block_id', $blockId),
            default => $this->advanceDiscounts()->where('block_id', $blockId)->first(),
        };

        $percent = $forBlock !== null
            ? (float) $forBlock->discount_percent
            : ($this->advance_discount_percent === null ? null : (float) $this->advance_discount_percent);

        return $percent === null ? null : max(0.0, min(90.0, $percent));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
