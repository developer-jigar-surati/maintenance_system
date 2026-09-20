<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A helpdesk ticket raised by a resident or by management.
 *
 * Carries two SLA clocks -- one for first response, one for resolution --
 * both set from the category when the ticket is created.
 */
class Complaint extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /** Statuses in which the ticket is still someone's problem. */
    public const OPEN_STATUSES = ['open', 'assigned', 'in_progress', 'on_hold', 'reopened'];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'escalated_at' => 'datetime',
            'is_sla_breached' => 'boolean',
            'is_public' => 'boolean',
            'attachments' => 'array',
            'rating' => 'integer',
            'reopen_count' => 'integer',
            'escalation_level' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $c) => $c->uuid ??= (string) Str::uuid());
    }

    // Relationships -------------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class, 'complaint_category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ComplaintComment::class)->oldest();
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ComplaintStatusLog::class)->oldest();
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    // Behaviour -----------------------------------------------------------

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'closed'], true);
    }

    /** Past its resolution target and still not resolved. */
    public function hasBreachedSla(): bool
    {
        return $this->isOpen()
            && $this->resolution_due_at !== null
            && $this->resolution_due_at->isPast();
    }

    /** Overdue for a first reply, which is the earlier warning sign. */
    public function hasBreachedResponseSla(): bool
    {
        return $this->first_responded_at === null
            && $this->isOpen()
            && $this->response_due_at !== null
            && $this->response_due_at->isPast();
    }

    public function ageInHours(): int
    {
        return (int) $this->created_at->diffInHours($this->resolved_at ?? now());
    }

    public function priorityRank(): int
    {
        return match ($this->priority) {
            'urgent' => 4,
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }

    // Scopes --------------------------------------------------------------

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeBreached(Builder $query): Builder
    {
        return $query->open()->where('resolution_due_at', '<', now());
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can(\App\Enums\Permission::COMPLAINT_VIEW_ALL)) {
            return $query;
        }

        // A resident sees their own tickets, anything raised for their unit,
        // and society-wide issues the committee marked public.
        $unitIds = $user->units()->pluck('units.id');

        return $query->where(fn (Builder $q) => $q
            ->where('raised_by', $user->id)
            ->orWhereIn('unit_id', $unitIds)
            ->orWhere('is_public', true));
    }
}
