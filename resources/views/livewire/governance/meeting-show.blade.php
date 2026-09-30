<div>
    <div class="mb-5">
        <a href="{{ route('meetings.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-secondary hover:text-primary">
            <x-ui.icon name="chevron-left" class="size-4" /> All meetings
        </a>
    </div>

    <x-ui.page-header :title="$meeting->title">
        <x-slot:description>
            {{ $meeting->typeLabel() }} · {{ $meeting->scheduled_at->format('l, j F Y \a\t g:i A') }}
            @if ($meeting->venue) · {{ $meeting->venue }} @endif
        </x-slot:description>
        <x-slot:actions>
            {{-- Giving notice, and being able to show it was given, is a
                 bye-law obligation for most societies. --}}
            @if ($canManage && $meeting->isUpcoming())
                <x-ui.button
                    size="sm"
                    :variant="$meeting->notice_sent_at ? 'ghost' : 'secondary'"
                    icon="megaphone"
                    wire:click="sendNotice"
                    wire:confirm="Send the notice of this meeting to every member?"
                >{{ $meeting->notice_sent_at ? 'Send notice again' : 'Send notice' }}</x-ui.button>
            @endif
            @if ($meeting->notice_sent_at)
                <x-ui.badge tone="positive" dot>Notice given {{ $meeting->notice_sent_at->format('j M') }}</x-ui.badge>
            @endif
            <x-ui.status :value="$meeting->status" />
        </x-slot:actions>
    </x-ui.page-header>

    {{-- RSVP: the one thing a resident is here to do. --}}
    @if ($meeting->isUpcoming())
        <x-ui.card class="mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold">Will you attend?</p>
                    <p class="mt-0.5 text-sm text-secondary">
                        {{ $meeting->rsvpYesCount() }} {{ \Illuminate\Support\Str::plural('member', $meeting->rsvpYesCount()) }}
                        have said yes so far.
                    </p>
                </div>
                <div class="flex gap-2" role="group" aria-label="Your response">
                    @foreach (['yes' => 'Yes', 'maybe' => 'Maybe', 'no' => 'No'] as $value => $label)
                        <x-ui.button
                            size="sm"
                            :variant="$rsvp === $value ? 'primary' : 'secondary'"
                            wire:click="setRsvp('{{ $value }}')"
                            :aria-pressed="$rsvp === $value ? 'true' : 'false'"
                        >{{ $label }}</x-ui.button>
                    @endforeach
                </div>
            </div>
        </x-ui.card>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Agenda --}}
            <x-ui.card title="Agenda" padded="false">
                @if ($meeting->agendaItems->isEmpty())
                    <x-ui.empty-state icon="clipboard" title="No agenda items yet" />
                @else
                    <ol class="divide-y divide-[var(--border-subtle)]">
                        @foreach ($meeting->agendaItems as $item)
                            <li class="flex gap-4 px-5 py-4">
                                <span class="numeric flex size-7 shrink-0 items-center justify-center rounded-lg surface-inset text-xs font-bold">
                                    {{ $loop->iteration }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <p class="font-medium">{{ $item->title }}</p>
                                        <x-ui.status :value="$item->outcome" />
                                    </div>
                                    @if ($item->description)
                                        <p class="mt-1 text-sm text-secondary">{{ $item->description }}</p>
                                    @endif
                                    @if ($item->discussion_notes)
                                        <p class="mt-2 rounded-lg surface-inset p-3 text-sm text-secondary">
                                            {{ $item->discussion_notes }}
                                        </p>
                                    @endif
                                    @if ($item->proposedBy)
                                        <p class="mt-1 text-xs text-muted">Proposed by {{ $item->proposedBy->name }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>

            {{-- Resolutions --}}
            @if ($meeting->resolutions->isNotEmpty())
                <x-ui.card title="Resolutions" padded="false">
                    <ul class="divide-y divide-[var(--border-subtle)]">
                        @foreach ($meeting->resolutions as $resolution)
                            <li class="px-5 py-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <p class="font-medium">{{ $resolution->title }}</p>
                                    <div class="flex items-center gap-2">
                                        <x-ui.badge :tone="$resolution->type === 'special' ? 'accent' : 'neutral'">
                                            {{ ucfirst($resolution->type) }}
                                        </x-ui.badge>
                                        <x-ui.status :value="$resolution->result" />
                                    </div>
                                </div>
                                <p class="mt-1.5 text-sm text-secondary">{{ $resolution->text }}</p>
                                @if ($resolution->result !== 'pending')
                                    <p class="numeric mt-2 text-xs text-muted">
                                        For {{ rtrim(rtrim(number_format((float) $resolution->votes_for, 2), '0'), '.') }} ·
                                        Against {{ rtrim(rtrim(number_format((float) $resolution->votes_against, 2), '0'), '.') }} ·
                                        Abstain {{ rtrim(rtrim(number_format((float) $resolution->votes_abstain, 2), '0'), '.') }}
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            {{-- Minutes --}}
            <x-ui.card title="Minutes">
                @if ($canPublishMinutes)
                    <form wire:submit="saveMinutes" class="space-y-3">
                        <x-ui.textarea wire:model="minutes" name="minutes" rows="10"
                            label="What was discussed and decided"
                            placeholder="Record the discussion, decisions and who is responsible for what…" />
                        <x-ui.button type="submit" size="sm">
                            <span wire:loading.remove wire:target="saveMinutes">Publish minutes</span>
                            <span wire:loading wire:target="saveMinutes">Publishing&hellip;</span>
                        </x-ui.button>
                    </form>
                @elseif ($meeting->hasMinutes())
                    <p class="selectable whitespace-pre-line text-sm leading-relaxed">{{ $meeting->minutes }}</p>
                    @if ($meeting->minutes_published_at)
                        <p class="mt-4 border-t border-subtle pt-3 text-xs text-muted">
                            Recorded by {{ $meeting->minutesRecordedBy?->name }} ·
                            {{ $meeting->minutes_published_at->format('j M Y') }}
                        </p>
                    @endif
                @else
                    <x-ui.empty-state icon="clipboard" title="Minutes not published yet" />
                @endif
            </x-ui.card>

            {{-- Follow-ups --}}
            @if ($meeting->actionItems->isNotEmpty())
                <x-ui.card title="Action items" padded="false">
                    <ul class="divide-y divide-[var(--border-subtle)]">
                        @foreach ($meeting->actionItems as $action)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ $action->title }}</p>
                                    <p class="truncate text-xs text-muted">
                                        {{ $action->assignee?->name ?? 'Unassigned' }}
                                        @if ($action->due_date) · due {{ $action->due_date->format('j M Y') }} @endif
                                    </p>
                                </div>
                                <x-ui.status :value="$action->status" />
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>

        {{-- Rail --}}
        <div class="space-y-6">
            <x-ui.card title="Attendance">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-secondary">Said yes</dt>
                        <dd class="numeric font-semibold">{{ $meeting->rsvpYesCount() }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-secondary">Attended</dt>
                        <dd class="numeric font-semibold">{{ $attendedCount }}</dd>
                    </div>
                    @if ($meeting->quorum_required > 0)
                        <div class="flex justify-between border-t border-subtle pt-2">
                            <dt class="text-secondary">Quorum needed</dt>
                            <dd class="numeric font-semibold">{{ $meeting->quorum_required }}</dd>
                        </div>
                        <div class="pt-1">
                            @if ($meeting->quorum_met)
                                <x-ui.badge tone="positive" dot>Quorum met</x-ui.badge>
                            @else
                                <x-ui.badge tone="caution" dot>Quorum not met</x-ui.badge>
                            @endif
                        </div>
                    @endif
                </dl>
            </x-ui.card>

            @if ($canManage && $meeting->attendees->isNotEmpty())
                <x-ui.card title="Mark attendance" padded="false">
                    <ul class="max-h-96 divide-y divide-[var(--border-subtle)] overflow-y-auto scrollbar-slim">
                        @foreach ($meeting->attendees as $attendee)
                            <li class="flex items-center justify-between gap-2 px-5 py-2.5">
                                <div class="min-w-0">
                                    <p class="truncate text-sm">{{ $attendee->user?->name }}</p>
                                    <p class="truncate text-xs text-muted">
                                        {{ $attendee->unit?->label }} · RSVP {{ str_replace('_', ' ', $attendee->rsvp) }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    wire:click="markAttended({{ $attendee->id }})"
                                    class="shrink-0 rounded-lg border px-2.5 py-1 text-xs font-semibold
                                        {{ $attendee->attended ? 'border-transparent bg-[var(--color-positive)] text-white' : 'border-subtle text-secondary' }}"
                                    aria-pressed="{{ $attendee->attended ? 'true' : 'false' }}"
                                >
                                    {{ $attendee->attended ? 'Present' : 'Mark' }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            @if ($meeting->polls->isNotEmpty())
                <x-ui.card title="Votes" padded="false">
                    <ul class="divide-y divide-[var(--border-subtle)]">
                        @foreach ($meeting->polls as $poll)
                            <li class="px-5 py-3">
                                <a href="{{ route('polls.show', $poll) }}" class="text-sm font-medium hover:underline">
                                    {{ $poll->title }}
                                </a>
                                <p class="text-xs text-muted">{{ ucfirst($poll->status) }}</p>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
