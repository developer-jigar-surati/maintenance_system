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
        ];
    }

    public function chargeHeads(): BelongsToMany
    {
        return $this->belongsToMany(ChargeHead::class)
            ->withPivot(['rate', 'basis', 'sort_order'])
            ->withTimestamps()
            ->orderBy('billing_plan_charge_head.sort_order');
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
