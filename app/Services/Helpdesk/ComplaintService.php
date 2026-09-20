<?php

namespace App\Services\Helpdesk;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Society;
use App\Models\User;
use App\Services\NumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Runs the helpdesk: ticket creation, assignment, the SLA clocks, and the
 * status trail that lets a resident see their complaint is actually moving.
 */
class ComplaintService
{
    public function __construct(private NumberGenerator $numbers) {}

    /**
     * Raises a ticket. SLA deadlines come from the category, so a lift
     * breakdown gets a tighter clock than a parking query.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function raise(Society $society, array $attributes, ?User $raisedBy = null): Complaint
    {
        $category = isset($attributes['complaint_category_id'])
            ? ComplaintCategory::find($attributes['complaint_category_id'])
            : null;

        $priority = $attributes['priority'] ?? $category?->default_priority ?? 'medium';
        $now = now();

        return DB::transaction(function () use ($society, $attributes, $raisedBy, $category, $priority, $now) {
            $complaint = Complaint::create([
                'society_id' => $society->id,
                'ticket_number' => $this->numbers->next(NumberGenerator::COMPLAINT, $society),
                'complaint_category_id' => $category?->id,
                'unit_id' => $attributes['unit_id'] ?? null,
                'raised_by' => $raisedBy?->id,
                'title' => $attributes['title'],
                'description' => $attributes['description'],
                'location' => $attributes['location'] ?? null,
                'priority' => $priority,
                'status' => 'open',
                'is_public' => (bool) ($attributes['is_public'] ?? false),
                'attachments' => $attributes['attachments'] ?? null,
                'response_due_at' => $this->deadline($now, $category?->response_sla_hours ?? 8, $priority),
                'resolution_due_at' => $this->deadline($now, $category?->resolution_sla_hours ?? 48, $priority),
            ]);

            $this->log($complaint, null, 'open', $raisedBy, 'Ticket raised');

            // Categories can name a standing owner, which saves the committee
            // triaging every routine plumbing complaint by hand.
            if ($category?->default_assignee_id) {
                $this->assign($complaint, User::find($category->default_assignee_id), $raisedBy);
            }

            return $complaint->refresh();
        });
    }

    public function assign(Complaint $complaint, ?User $assignee, ?User $by = null): Complaint
    {
        $from = $complaint->status;

        $complaint->forceFill([
            'assigned_to' => $assignee?->id,
            'assigned_at' => $assignee ? now() : null,
            'status' => $assignee ? 'assigned' : 'open',
        ])->save();

        $this->log(
            $complaint, $from, $complaint->status, $by,
            $assignee ? "Assigned to {$assignee->name}" : 'Assignment cleared',
        );

        return $complaint;
    }

    /**
     * Moves a ticket through its lifecycle, keeping the SLA fields honest.
     * Reopening restarts the resolution clock, since the work is not done.
     */
    public function changeStatus(Complaint $complaint, string $to, ?User $by = null, ?string $notes = null): Complaint
    {
        $from = $complaint->status;

        if ($from === $to) {
            return $complaint;
        }

        $changes = ['status' => $to];

        match ($to) {
            'resolved' => $changes += [
                'resolved_at' => now(),
                'is_sla_breached' => $complaint->hasBreachedSla(),
            ],
            'closed' => $changes += ['closed_at' => now()],
            'reopened' => $changes += [
                'resolved_at' => null,
                'closed_at' => null,
                'reopen_count' => $complaint->reopen_count + 1,
                'resolution_due_at' => $this->deadline(
                    now(),
                    $complaint->category?->resolution_sla_hours ?? 48,
                    $complaint->priority,
                ),
            ],
            default => null,
        };

        if ($notes && $to === 'resolved') {
            $changes['resolution_notes'] = $notes;
        }

        $complaint->forceFill($changes)->save();
        $this->log($complaint, $from, $to, $by, $notes);

        return $complaint;
    }

    /** Records the first reply, which stops the response clock. */
    public function recordFirstResponse(Complaint $complaint): void
    {
        if ($complaint->first_responded_at === null) {
            $complaint->forceFill(['first_responded_at' => now()])->save();
        }
    }

    public function comment(Complaint $complaint, User $user, string $body, bool $internal = false, ?array $attachments = null): void
    {
        $complaint->comments()->create([
            'society_id' => $complaint->society_id,
            'user_id' => $user->id,
            'body' => $body,
            'is_internal' => $internal,
            'attachments' => $attachments,
        ]);

        // Only a reply visible to the resident counts as a response.
        if (! $internal && $user->id !== $complaint->raised_by) {
            $this->recordFirstResponse($complaint);
        }
    }

    /**
     * Raises the escalation level of tickets that have blown their SLA.
     * Returns the tickets escalated so the caller can notify the committee.
     *
     * @return Collection<int, Complaint>
     */
    public function escalateBreached(Society $society, ?Carbon $on = null): Collection
    {
        $on ??= now();

        if (! $society->setting('helpdesk.auto_escalate', true)) {
            return new Collection;
        }

        $cooldown = (int) $society->setting('helpdesk.escalate_after_hours', 24);

        return Complaint::query()
            ->where('society_id', $society->id)
            ->open()
            ->where('resolution_due_at', '<', $on)
            // Do not re-escalate the same ticket every time the job runs.
            ->where(fn ($q) => $q
                ->whereNull('escalated_at')
                ->orWhere('escalated_at', '<', $on->copy()->subHours($cooldown)))
            ->get()
            ->each(function (Complaint $complaint) use ($on) {
                $complaint->forceFill([
                    'is_sla_breached' => true,
                    'escalation_level' => min(3, $complaint->escalation_level + 1),
                    'escalated_at' => $on,
                ])->save();

                $this->log($complaint, $complaint->status, $complaint->status, null,
                    "Escalated to level {$complaint->escalation_level} after SLA breach");
            });
    }

    public function rate(Complaint $complaint, int $rating, ?string $feedback = null): Complaint
    {
        $complaint->forceFill([
            'rating' => max(1, min(5, $rating)),
            'feedback' => $feedback,
        ])->save();

        return $complaint;
    }

    /**
     * Urgent tickets get a compressed clock regardless of what the category
     * says, because "urgent" should mean something.
     */
    private function deadline(Carbon $from, int $slaHours, string $priority): Carbon
    {
        $multiplier = match ($priority) {
            'urgent' => 0.5,
            'high' => 0.75,
            'low' => 1.5,
            default => 1.0,
        };

        return $from->copy()->addMinutes((int) round($slaHours * 60 * $multiplier));
    }

    private function log(Complaint $complaint, ?string $from, string $to, ?User $by, ?string $notes): void
    {
        $complaint->statusLogs()->create([
            'society_id' => $complaint->society_id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $by?->id,
            'notes' => $notes,
        ]);
    }

    /** Headline numbers for the helpdesk dashboard. */
    public function statistics(Society $society): array
    {
        $base = fn () => Complaint::query()->where('society_id', $society->id);

        $resolved = (clone $base)()->whereIn('status', ['resolved', 'closed'])->get(['created_at', 'resolved_at']);

        $avgHours = $resolved
            ->filter(fn ($c) => $c->resolved_at !== null)
            ->map(fn ($c) => $c->created_at->diffInHours($c->resolved_at))
            ->avg();

        return [
            'open' => (clone $base)()->open()->count(),
            'breached' => (clone $base)()->breached()->count(),
            'resolved_this_month' => (clone $base)()
                ->whereIn('status', ['resolved', 'closed'])
                ->whereMonth('resolved_at', now()->month)
                ->whereYear('resolved_at', now()->year)
                ->count(),
            'average_resolution_hours' => $avgHours ? round((float) $avgHours, 1) : null,
            'average_rating' => round((float) (clone $base)()->whereNotNull('rating')->avg('rating'), 1) ?: null,
        ];
    }
}
