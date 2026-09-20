<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A society meeting: AGM, special general body, or committee sitting.
 *
 * Tracks the statutory bits that matter later -- notice period, quorum,
 * attendance including proxies, resolutions and their outcomes.
 */
class Meeting extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'ends_at' => 'datetime',
            'notice_sent_at' => 'datetime',
            'minutes_published_at' => 'datetime',
            'minutes_approved_at' => 'datetime',
            'notice_days' => 'integer',
            'quorum_required' => 'integer',
            'quorum_met' => 'boolean',
        ];
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(MeetingAgendaItem::class)->orderBy('sort_order');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class);
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(MeetingResolution::class);
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }

    public function polls(): HasMany
    {
        return $this->hasMany(Poll::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function minutesRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'minutes_recorded_by');
    }

    // Behaviour -----------------------------------------------------------

    public function attendedCount(): int
    {
        return $this->attendees()->where('attended', true)->count();
    }

    public function rsvpYesCount(): int
    {
        return $this->attendees()->where('rsvp', 'yes')->count();
    }

    /** Recomputes quorum from actual attendance and stores the verdict. */
    public function evaluateQuorum(): bool
    {
        $met = $this->quorum_required === 0
            || $this->attendedCount() >= $this->quorum_required;

        if ($met !== (bool) $this->quorum_met) {
            $this->forceFill(['quorum_met' => $met])->save();
        }

        return $met;
    }

    /** The last date notice can go out and still satisfy the notice period. */
    public function noticeDeadline(): \Illuminate\Support\Carbon
    {
        return $this->scheduled_at->copy()->subDays((int) $this->notice_days);
    }

    public function noticeIsOverdue(): bool
    {
        return $this->notice_sent_at === null
            && $this->status !== 'draft'
            && $this->noticeDeadline()->isPast();
    }

    public function isUpcoming(): bool
    {
        return $this->scheduled_at->isFuture()
            && in_array($this->status, ['scheduled', 'draft'], true);
    }

    public function hasMinutes(): bool
    {
        return filled($this->minutes);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'agm' => 'Annual General Meeting',
            'sgm' => 'Special General Meeting',
            'general_body' => 'General Body Meeting',
            default => ucwords(str_replace('_', ' ', $this->type)),
        };
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>=', now())
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->orderBy('scheduled_at');
    }
}
