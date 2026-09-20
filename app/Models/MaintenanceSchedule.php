<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A recurring preventive-maintenance task. Rolls forward on completion and
 * raises work orders shortly before each occurrence falls due.
 */
class MaintenanceSchedule extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'next_due_on' => 'date',
            'last_completed_on' => 'date',
            'checklist' => 'array',
            'estimated_cost' => 'decimal:2',
            'interval_days' => 'integer',
            'create_days_before' => 'integer',
            'auto_create_work_order' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /** Days between occurrences for the chosen frequency. */
    public function intervalInDays(): int
    {
        return match ($this->frequency) {
            'daily' => 1,
            'weekly' => 7,
            'fortnightly' => 14,
            'monthly' => 30,
            'quarterly' => 91,
            'half_yearly' => 182,
            'yearly' => 365,
            default => max(1, (int) $this->interval_days),
        };
    }

    public function advance(?Carbon $from = null): void
    {
        $from ??= $this->next_due_on ?? now();

        $this->forceFill([
            'last_completed_on' => now()->toDateString(),
            'next_due_on' => $from->copy()->addDays($this->intervalInDays())->toDateString(),
        ])->save();
    }

    /** True when the occurrence is close enough to raise its work order. */
    public function isDueForWorkOrder(?Carbon $on = null): bool
    {
        $on ??= now();

        return $this->is_active
            && $this->auto_create_work_order
            && $this->next_due_on !== null
            && $this->next_due_on->lte($on->copy()->addDays((int) $this->create_days_before));
    }

    public function isOverdue(): bool
    {
        return $this->is_active
            && $this->next_due_on !== null
            && $this->next_due_on->endOfDay()->isPast();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
