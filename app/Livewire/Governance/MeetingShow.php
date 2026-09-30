<?php

namespace App\Livewire\Governance;

use App\Enums\Permission;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Services\Messaging\Announcer;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A meeting's agenda, attendance, resolutions and minutes.
 */
#[Layout('components.layouts.app')]
class MeetingShow extends Component
{
    public Meeting $meeting;

    public string $minutes = '';

    public string $rsvp = 'no_response';

    public function mount(Meeting $meeting): void
    {
        $this->meeting = $meeting->load([
            'agendaItems.proposedBy', 'resolutions', 'actionItems.assignee',
            'attendees.user', 'attendees.unit', 'minutesRecordedBy', 'polls',
        ]);

        $this->minutes = (string) $meeting->minutes;
        $this->rsvp = $this->myAttendance()?->rsvp ?? 'no_response';
    }

    private function myAttendance(): ?MeetingAttendee
    {
        return $this->meeting->attendees->firstWhere('user_id', auth()->id());
    }

    public function setRsvp(string $answer): void
    {
        abort_unless(in_array($answer, ['yes', 'no', 'maybe'], true), 422);

        $unitId = auth()->user()->units()->value('units.id');

        MeetingAttendee::updateOrCreate(
            ['meeting_id' => $this->meeting->id, 'user_id' => auth()->id()],
            [
                'society_id' => $this->meeting->society_id,
                'unit_id' => $unitId,
                'rsvp' => $answer,
                'rsvp_at' => now(),
            ],
        );

        $this->rsvp = $answer;
        $this->meeting->refresh()->load('attendees.user');
        $this->dispatch('notify', message: 'Your response has been recorded.', tone: 'positive');
    }

    /**
     * Circulates the formal notice of the meeting.
     *
     * Most societies are bound by their bye-laws to give a minimum number of
     * days' notice and to be able to show they did, so the dispatch record is
     * as much the point as the message.
     */
    public function sendNotice(Announcer $announcer): void
    {
        Gate::authorize(Permission::MEETING_MANAGE);

        $sent = $announcer->meetingNotice($this->meeting);

        $this->meeting->forceFill(['notice_sent_at' => now()])->save();
        $this->meeting->refresh();

        $this->dispatch('notify',
            message: $sent > 0
                ? "Notice of the meeting sent to {$sent} members."
                : 'Everyone has already been sent this notice.',
            tone: 'positive');
    }

    public function saveMinutes(): void
    {
        Gate::authorize(Permission::MINUTES_PUBLISH);

        $this->validate(['minutes' => 'required|string|min:10']);

        $this->meeting->forceFill([
            'minutes' => $this->minutes,
            'minutes_recorded_by' => auth()->id(),
            'minutes_published_at' => now(),
            'status' => $this->meeting->status === 'completed' ? 'completed' : 'completed',
        ])->save();

        $this->meeting->refresh()->load('minutesRecordedBy');

        app(Announcer::class)->minutesCirculated($this->meeting);

        $this->dispatch('notify', message: 'Minutes published and circulated.', tone: 'positive');
    }

    /** Marks attendance and re-checks quorum from the new count. */
    public function markAttended(int $attendeeId): void
    {
        Gate::authorize(Permission::MEETING_MANAGE);

        $attendee = MeetingAttendee::findOrFail($attendeeId);
        $attendee->forceFill([
            'attended' => ! $attendee->attended,
            'checked_in_at' => $attendee->attended ? null : now(),
        ])->save();

        $this->meeting->refresh()->load('attendees.user');
        $this->meeting->evaluateQuorum();
    }

    public function render()
    {
        return view('livewire.governance.meeting-show', [
            'canManage' => auth()->user()->can(Permission::MEETING_MANAGE),
            'canPublishMinutes' => auth()->user()->can(Permission::MINUTES_PUBLISH),
            'attendedCount' => $this->meeting->attendees->where('attended', true)->count(),
        ])->title($this->meeting->title);
    }
}
