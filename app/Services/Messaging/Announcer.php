<?php

namespace App\Services\Messaging;

use App\Models\Complaint;
use App\Models\Meeting;
use App\Models\Notice;
use App\Models\Receipt;
use App\Models\Society;
use App\Models\User;
use App\Models\VisitorLog;
use App\Support\MessageCatalogue;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * The events a society announces to its residents.
 *
 * Each method here is the other half of a template in the catalogue: if a
 * message can be edited in Settings, something must actually send it, or the
 * editor is a promise the system does not keep. Everything goes through the
 * Messenger, so all of it is recorded and none of it can be sent twice.
 */
class Announcer
{
    public function __construct(private Messenger $messenger) {}

    /** The digital rasid, sent the moment a payment is recorded. */
    public function receiptIssued(Receipt $receipt): void
    {
        $society = $receipt->resolveSociety();
        $recipient = $receipt->unit?->billingContact()?->user;

        if ($recipient === null) {
            return;
        }

        $payment = $receipt->payment;

        $this->messenger->send(
            society: $society,
            templateKey: MessageCatalogue::PAYMENT_RECEIPT,
            recipient: $recipient,
            data: [
                'unit_label' => (string) $receipt->unit?->label,
                'receipt_number' => (string) $receipt->receipt_number,
                'amount_paid' => Money::format((float) $receipt->amount),
                'paid_on' => $receipt->issued_on?->format('j F Y') ?? '',
                'payment_method' => $payment ? str_replace('_', ' ', ucfirst($payment->method)) : '',
                'reference' => (string) ($payment?->reference_number ?? ''),
                'balance' => Money::format((float) ($receipt->unit?->outstandingBalance() ?? 0)),
                'receipt_link' => route('receipts.pdf', $receipt),
            ],
            related: $receipt,
            dedupeKey: "receipt:{$receipt->id}",
        );
    }

    /** A circular, sent to whoever the notice is addressed to. */
    public function noticePublished(Notice $notice): int
    {
        $society = $notice->resolveSociety();

        $sent = 0;

        foreach ($this->audienceFor($society, $notice) as $recipient) {
            $dispatch = $this->messenger->send(
                society: $society,
                templateKey: MessageCatalogue::NOTICE_PUBLISHED,
                recipient: $recipient,
                data: [
                    'notice_title' => $notice->title,
                    'notice_body' => strip_tags($notice->body),
                    'notice_category' => ucfirst(str_replace('_', ' ', $notice->category)),
                    'published_on' => $notice->published_at?->format('j F Y') ?? now()->format('j F Y'),
                    'notice_link' => route('notices.index'),
                    'unit_label' => (string) $recipient->units()->first()?->label,
                ],
                related: $notice,
                dedupeKey: "notice:{$notice->id}:{$recipient->id}",
            );

            $sent += $dispatch === null ? 0 : 1;
        }

        return $sent;
    }

    /**
     * The formal notice of a meeting.
     *
     * Societies are usually required to give a minimum number of days' notice
     * of a general body meeting, and to be able to show they did. The dispatch
     * record is that proof.
     */
    public function meetingNotice(Meeting $meeting): int
    {
        $society = $meeting->resolveSociety();
        $sent = 0;

        foreach ($this->activeMembers($society) as $recipient) {
            $dispatch = $this->messenger->send(
                society: $society,
                templateKey: MessageCatalogue::MEETING_NOTICE,
                recipient: $recipient,
                data: [
                    'meeting_title' => $meeting->title,
                    'meeting_type' => $meeting->typeLabel(),
                    'meeting_date' => $meeting->scheduled_at?->format('j F Y') ?? '',
                    'meeting_time' => $meeting->scheduled_at?->format('g:i a') ?? '',
                    'meeting_venue' => (string) ($meeting->venue ?: 'To be announced'),
                    'meeting_agenda' => $this->agendaLines($meeting),
                    'meeting_link' => route('meetings.show', $meeting),
                    'unit_label' => (string) $recipient->units()->first()?->label,
                ],
                related: $meeting,
                dedupeKey: "meeting-notice:{$meeting->id}:{$recipient->id}",
            );

            $sent += $dispatch === null ? 0 : 1;
        }

        return $sent;
    }

    /** The minutes, circulated once they are final. */
    public function minutesCirculated(Meeting $meeting): int
    {
        $society = $meeting->resolveSociety();
        $sent = 0;

        foreach ($this->activeMembers($society) as $recipient) {
            $dispatch = $this->messenger->send(
                society: $society,
                templateKey: MessageCatalogue::MEETING_MINUTES,
                recipient: $recipient,
                data: [
                    'meeting_title' => $meeting->title,
                    'meeting_date' => $meeting->scheduled_at?->format('j F Y') ?? '',
                    'attendee_count' => (string) $meeting->attendees()->where('attended', true)->count(),
                    'resolutions' => $this->resolutionLines($meeting),
                    'meeting_link' => route('meetings.show', $meeting),
                    'unit_label' => (string) $recipient->units()->first()?->label,
                ],
                related: $meeting,
                // Keyed on when the minutes were published, so a correction
                // that is republished goes out again rather than being eaten.
                dedupeKey: "minutes:{$meeting->id}:{$meeting->minutes_published_at?->timestamp}:{$recipient->id}",
            );

            $sent += $dispatch === null ? 0 : 1;
        }

        return $sent;
    }

    /** Tells a resident that someone is at the gate for them. */
    public function visitorWaiting(VisitorLog $log): void
    {
        $recipient = $log->unit?->billingContact()?->user;

        if ($recipient === null) {
            return;
        }

        $this->messenger->send(
            society: $log->resolveSociety(),
            templateKey: MessageCatalogue::VISITOR_WAITING,
            recipient: $recipient,
            data: [
                'visitor_name' => (string) $log->visitor_name,
                'visitor_purpose' => ucfirst((string) $log->purpose),
                'visitor_company' => (string) ($log->company ?? ''),
                'visitor_phone' => (string) ($log->phone ?? ''),
                'visitor_link' => route('visitors.index'),
                'unit_label' => (string) $log->unit?->label,
            ],
            channel: 'in_app',
            related: $log,
            dedupeKey: "visitor:{$log->id}",
        );
    }

    /** Tells whoever raised a complaint that it has moved on. */
    public function complaintUpdated(Complaint $complaint, string $note = ''): void
    {
        $recipient = $complaint->raisedBy;

        if ($recipient === null) {
            return;
        }

        $this->messenger->send(
            society: $complaint->resolveSociety(),
            templateKey: MessageCatalogue::COMPLAINT_UPDATE,
            recipient: $recipient,
            data: [
                'ticket_number' => (string) $complaint->ticket_number,
                'ticket_subject' => (string) $complaint->title,
                'ticket_status' => ucfirst(str_replace('_', ' ', $complaint->status)),
                'ticket_note' => $note,
                'assignee_name' => (string) ($complaint->assignee?->name ?? 'the committee'),
                'ticket_link' => route('complaints.show', $complaint),
                'unit_label' => (string) $complaint->unit?->label,
            ],
            related: $complaint,
            // The status is part of the key, so each real change is announced
            // once but a re-save of the same status is not.
            dedupeKey: "complaint:{$complaint->id}:{$complaint->status}",
        );
    }

    /** @return Collection<int, User> */
    private function audienceFor(Society $society, Notice $notice): Collection
    {
        return $this->activeMembers($society)
            ->filter(fn (User $user) => $notice->isVisibleTo($user))
            ->values();
    }

    /** @return Collection<int, User> */
    private function activeMembers(Society $society): Collection
    {
        return $society->users()
            ->wherePivot('status', 'active')
            ->get();
    }

    private function resolutionLines(Meeting $meeting): string
    {
        $resolutions = $meeting->resolutions()->orderBy('id')->pluck('title');

        if ($resolutions->isEmpty()) {
            return 'No formal resolutions were recorded.';
        }

        return $resolutions
            ->map(fn (string $title, int $i) => ($i + 1).'. '.$title)
            ->implode("\n");
    }

    private function agendaLines(Meeting $meeting): string
    {
        $items = $meeting->agendaItems()->orderBy('sort_order')->pluck('title');

        if ($items->isEmpty()) {
            return 'The agenda will be circulated separately.';
        }

        return $items
            ->map(fn (string $title, int $i) => ($i + 1).'. '.$title)
            ->implode("\n");
    }
}
