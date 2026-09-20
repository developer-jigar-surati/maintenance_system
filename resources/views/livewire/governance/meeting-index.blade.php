<div>
    <x-ui.page-header title="Meetings" description="AGMs, special meetings and committee sittings." />

    @if ($upcoming)
        <div class="mb-5 surface-card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-4 p-5">
                <div class="min-w-0">
                    <x-ui.badge tone="accent">Next up</x-ui.badge>
                    <h2 class="mt-2 text-lg font-semibold">{{ $upcoming->title }}</h2>
                    <p class="mt-1 text-sm text-secondary">
                        {{ $upcoming->typeLabel() }} ·
                        {{ $upcoming->scheduled_at->format('l, j F Y \a\t g:i A') }}
                        @if ($upcoming->venue) · {{ $upcoming->venue }} @endif
                    </p>
                    @if ($upcoming->noticeIsOverdue())
                        <p class="mt-2 text-sm font-medium text-[var(--color-critical)]">
                            Notice has not gone out and the {{ $upcoming->notice_days }}-day period has passed.
                        </p>
                    @endif
                </div>
                <x-ui.button :href="route('meetings.show', $upcoming)" icon="calendar">Open</x-ui.button>
            </div>
        </div>
    @endif

    <x-ui.table
        :headers="['Meeting', 'Type', 'When', 'Agenda', 'Attendance', 'Status', '']"
        :is-empty="$meetings->isEmpty()"
        empty="No meetings recorded yet"
        empty-icon="calendar"
        caption="Meetings with type, date, agenda size and status"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Meeting title" aria-label="Search meetings" />
            </div>
            <x-ui.select wire:model.live="type" aria-label="Filter by type" class="w-auto min-w-32">
                <option value="">All types</option>
                @foreach (['agm' => 'AGM', 'sgm' => 'Special GM', 'general_body' => 'General body', 'committee' => 'Committee', 'emergency' => 'Emergency'] as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select wire:model.live="status" aria-label="Filter by status" class="w-auto min-w-32">
                <option value="">All statuses</option>
                @foreach (['draft', 'scheduled', 'in_progress', 'completed', 'cancelled', 'adjourned'] as $v)
                    <option value="{{ $v }}">{{ ucwords(str_replace('_', ' ', $v)) }}</option>
                @endforeach
            </x-ui.select>
            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($meetings as $meeting)
            <x-ui.tr :href="route('meetings.show', $meeting)">
                <x-ui.td label="Meeting" primary>
                    <a href="{{ route('meetings.show', $meeting) }}" class="hover:underline">{{ $meeting->title }}</a>
                    @if ($meeting->hasMinutes())
                        <span class="block text-xs text-muted">Minutes recorded</span>
                    @endif
                </x-ui.td>
                <x-ui.td label="Type">{{ $meeting->typeLabel() }}</x-ui.td>
                <x-ui.td label="When">
                    {{ $meeting->scheduled_at->format('j M Y') }}
                    <span class="block text-xs text-muted">{{ $meeting->scheduled_at->format('g:i A') }}</span>
                </x-ui.td>
                <x-ui.td label="Agenda">{{ $meeting->agenda_items_count }} items</x-ui.td>
                <x-ui.td label="Attendance">
                    {{ $meeting->attendees_count }}
                    @if ($meeting->quorum_required > 0)
                        <span class="block text-xs {{ $meeting->quorum_met ? 'text-[var(--color-positive)]' : 'text-muted' }}">
                            quorum {{ $meeting->quorum_required }}
                        </span>
                    @endif
                </x-ui.td>
                <x-ui.td label="Status"><x-ui.status :value="$meeting->status" /></x-ui.td>
                <x-ui.td align="right">
                    <a href="{{ route('meetings.show', $meeting) }}"
                       class="inline-flex items-center gap-1 text-xs font-semibold accent-text hover:underline">
                        Open <x-ui.icon name="chevron-right" class="size-3.5" />
                    </a>
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $meetings->links() }}</x-slot:footer>
    </x-ui.table>
</div>
