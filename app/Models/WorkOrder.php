<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A job to be carried out, raised from a ticket, a preventive-maintenance
 * schedule, or by hand.
 */
class WorkOrder extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'estimated_cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'checklist' => 'array',
            'attachments' => 'array',
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function maintenanceSchedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class);
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

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'assigned', 'in_progress', 'on_hold'], true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen()
            && $this->scheduled_for !== null
            && $this->scheduled_for->endOfDay()->isPast();
    }

    /** Portion of the checklist ticked off, as a percentage. */
    public function checklistProgress(): int
    {
        $items = $this->checklist ?? [];

        if ($items === []) {
            return $this->status === 'completed' || $this->status === 'verified' ? 100 : 0;
        }

        $done = count(array_filter($items, fn ($i) => (bool) ($i['done'] ?? false)));

        return (int) round($done / count($items) * 100);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'assigned', 'in_progress', 'on_hold']);
    }
}
