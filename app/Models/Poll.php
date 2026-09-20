<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A vote put to the members.
 *
 * Supports the three bases societies actually use: one vote per person, one
 * per unit, or weighted by the unit's area.
 */
class Poll extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_anonymous' => 'boolean',
            'show_results_before_close' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('sort_order');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(MeetingResolution::class, 'meeting_resolution_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Behaviour -----------------------------------------------------------

    public function isOpen(): bool
    {
        return $this->status === 'open'
            && $this->starts_at->isPast()
            && $this->ends_at->isFuture();
    }

    public function hasClosed(): bool
    {
        return $this->status === 'closed' || $this->ends_at->isPast();
    }

    /** Results are hidden mid-poll unless the organiser chose otherwise. */
    public function resultsVisible(): bool
    {
        return $this->hasClosed() || $this->show_results_before_close;
    }

    public function hasVoted(User $user): bool
    {
        return $this->votes()->where('user_id', $user->id)->exists();
    }

    /**
     * Tallies weighted by voting basis. Per-user and per-unit polls carry a
     * weight of 1; area-weighted polls carry the unit's area.
     */
    public function tally(): array
    {
        $totals = $this->votes()
            ->selectRaw('poll_option_id, SUM(weight) as weight_total, COUNT(*) as vote_count')
            ->groupBy('poll_option_id')
            ->get()
            ->keyBy('poll_option_id');

        $grandWeight = (float) $totals->sum('weight_total');

        return $this->options->map(function (PollOption $option) use ($totals, $grandWeight) {
            $row = $totals->get($option->id);
            $weight = (float) ($row->weight_total ?? 0);

            return [
                'option' => $option,
                'votes' => (int) ($row->vote_count ?? 0),
                'weight' => round($weight, 2),
                'percent' => $grandWeight > 0 ? round($weight / $grandWeight * 100, 1) : 0.0,
            ];
        })->all();
    }

    public function totalVotes(): int
    {
        return $this->votes()->count();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }
}
