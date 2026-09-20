<?php

namespace App\Services\Governance;

use App\Enums\Role;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Casts and counts votes.
 *
 * The only interesting part is weighting. Bye-laws differ on what a vote is
 * worth: one per person, one per flat regardless of size, or proportional to
 * area. All three are supported, and the weight is frozen onto the vote at the
 * moment it is cast so a later area correction cannot retroactively change a
 * closed result.
 */
class VotingService
{
    /**
     * Records a vote.
     *
     * @throws \DomainException when the poll is closed or the voter ineligible
     */
    public function cast(Poll $poll, User $user, ?PollOption $option, ?Unit $unit = null, ?int $rating = null): PollVote
    {
        if (! $poll->isOpen()) {
            throw new \DomainException('This poll is not open for voting.');
        }

        $unit ??= $this->votingUnitFor($poll, $user);

        $this->assertEligible($poll, $user, $unit);

        return DB::transaction(function () use ($poll, $user, $option, $unit, $rating) {
            if ($this->alreadyVoted($poll, $user, $unit)) {
                throw new \DomainException('A vote has already been recorded for this poll.');
            }

            return PollVote::create([
                'society_id' => $poll->society_id,
                'poll_id' => $poll->id,
                'poll_option_id' => $option?->id,
                'user_id' => $user->id,
                'unit_id' => $unit?->id,
                'weight' => $this->weightFor($poll, $unit),
                'rating' => $rating,
                'voted_at' => now(),
                'ip_address' => request()?->ip(),
            ]);
        });
    }

    /**
     * The weight a vote carries. Area-weighted polls use the unit's carpet
     * area, falling back to 1 so a unit with no recorded area still counts
     * rather than being silently disenfranchised.
     */
    public function weightFor(Poll $poll, ?Unit $unit): float
    {
        if ($poll->voting_basis !== 'weighted_by_area' || $unit === null) {
            return 1.0;
        }

        $area = $unit->areaFor('carpet_area');

        return $area > 0 ? round($area, 4) : 1.0;
    }

    /**
     * Whether this voter has already been counted, judged by the poll's basis:
     * per-unit polls allow one vote per flat, not one per resident.
     */
    public function alreadyVoted(Poll $poll, User $user, ?Unit $unit): bool
    {
        $query = $poll->votes();

        return match ($poll->voting_basis) {
            'per_user' => $query->where('user_id', $user->id)->exists(),
            default => $unit
                ? $query->where('unit_id', $unit->id)->exists()
                : $query->where('user_id', $user->id)->exists(),
        };
    }

    public function assertEligible(Poll $poll, User $user, ?Unit $unit): void
    {
        $eligible = match ($poll->eligibility) {
            'owners_only' => $user->residencies()
                ->where('status', 'active')
                ->whereIn('relation', ['owner', 'co_owner'])
                ->exists(),
            'committee_only' => $user->hasManagementRole()
                || $user->hasRole(Role::COMMITTEE_MEMBER),
            default => true,
        };

        if (! $eligible) {
            throw new \DomainException('You are not eligible to vote in this poll.');
        }

        if ($poll->voting_basis !== 'per_user' && $unit === null) {
            throw new \DomainException('This poll is voted per unit, and you are not linked to one.');
        }
    }

    /** The unit a user votes on behalf of, preferring one they own. */
    public function votingUnitFor(Poll $poll, User $user): ?Unit
    {
        if ($poll->voting_basis === 'per_user') {
            return null;
        }

        return $user->billableUnits()->first() ?? $user->units()->first();
    }

    /**
     * Closes a poll and, when it backs a resolution, writes the outcome onto
     * that resolution so the minutes and the vote never disagree.
     */
    public function close(Poll $poll): Poll
    {
        $poll->forceFill(['status' => 'closed'])->save();

        $resolution = $poll->resolution;

        if ($resolution === null) {
            return $poll;
        }

        $tally = collect($poll->load('options', 'votes')->tally());

        $weightFor = fn (string $label) => (float) ($tally
            ->first(fn ($row) => strcasecmp($row['option']->label, $label) === 0)['weight'] ?? 0);

        $for = $weightFor('Yes') ?: $weightFor('For');
        $against = $weightFor('No') ?: $weightFor('Against');
        $abstain = $weightFor('Abstain');

        // A special resolution needs two thirds of the votes cast; an ordinary
        // one needs a simple majority.
        $decisive = $for + $against;
        $threshold = $resolution->type === 'special' ? (2 / 3) : 0.5;

        $resolution->forceFill([
            'votes_for' => round($for, 2),
            'votes_against' => round($against, 2),
            'votes_abstain' => round($abstain, 2),
            'result' => $decisive > 0 && ($for / $decisive) > $threshold ? 'passed' : 'rejected',
            'decided_at' => now(),
        ])->save();

        return $poll;
    }

    /** Turnout as a share of the units entitled to vote. */
    public function turnout(Poll $poll): array
    {
        $eligible = match ($poll->voting_basis) {
            'per_user' => $poll->society->users()->wherePivot('status', 'active')->count(),
            default => Unit::query()->where('society_id', $poll->society_id)->billable()->count(),
        };

        $cast = $poll->votes()->count();

        return [
            'eligible' => $eligible,
            'cast' => $cast,
            'percent' => $eligible > 0 ? round($cast / $eligible * 100, 1) : 0.0,
        ];
    }
}
