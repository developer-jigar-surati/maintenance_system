@php $finance = $data['finance']; @endphp

{{-- Headline figures --}}
<div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
    <x-ui.stat
        label="Outstanding"
        :value="\App\Support\Money::compact($finance['outstanding'])"
        :hint="$finance['defaulter_count'].' units with dues'"
        icon="banknote"
        :tone="$finance['outstanding'] > 0 ? 'caution' : 'positive'"
        :href="route('invoices.index', ['status' => 'overdue'])"
    />
    <x-ui.stat
        label="Overdue"
        :value="\App\Support\Money::compact($finance['overdue'])"
        hint="past the due date"
        icon="alert"
        tone="critical"
        :href="route('invoices.index', ['status' => 'overdue'])"
    />
    <x-ui.stat
        label="Collected this month"
        :value="\App\Support\Money::compact($finance['collected_this_month'])"
        :hint="$finance['collection_rate'] !== null ? $finance['collection_rate'].'% of billed' : 'no bills raised yet'"
        icon="check"
        tone="positive"
        :href="route('payments.index')"
    />
    <x-ui.stat
        label="Cash & bank"
        :value="\App\Support\Money::compact($finance['cash_and_bank'])"
        hint="across all accounts"
        icon="wallet"
        tone="accent"
    />
</div>

{{-- Anything waiting on a human decision --}}
@php $pending = $finance['pending_approvals']; @endphp
@if ($pending['payments'] > 0 || $pending['expenses'] > 0 || $data['pendingBookings']->isNotEmpty())
    <div class="mt-4 flex flex-wrap gap-2">
        @if ($pending['payments'] > 0)
            <x-ui.button variant="secondary" size="sm" icon="banknote" :href="route('payments.index', ['status' => 'awaiting_approval'])">
                {{ $pending['payments'] }} {{ \Illuminate\Support\Str::plural('payment', $pending['payments']) }} to approve
            </x-ui.button>
        @endif
        @if ($pending['expenses'] > 0)
            <x-ui.button variant="secondary" size="sm" icon="wallet" :href="route('expenses.index', ['status' => 'pending_approval'])">
                {{ $pending['expenses'] }} {{ \Illuminate\Support\Str::plural('expense', $pending['expenses']) }} to approve
            </x-ui.button>
        @endif
        @if ($data['pendingBookings']->isNotEmpty())
            <x-ui.button variant="secondary" size="sm" icon="sparkles" :href="route('amenities.index')">
                {{ $data['pendingBookings']->count() }} booking {{ \Illuminate\Support\Str::plural('request', $data['pendingBookings']->count()) }}
            </x-ui.button>
        @endif
    </div>
@endif

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    {{-- Billed vs collected --}}
    <x-ui.card title="Billed vs collected" description="Last six months" class="lg:col-span-2">
        <div wire:ignore>
            <x-charts.bars :series="$data['trend']" />
        </div>
    </x-ui.card>

    {{-- Helpdesk health --}}
    <x-ui.card title="Helpdesk">
        @php $hd = $data['helpdesk']; @endphp
        <dl class="space-y-4">
            <div class="flex items-baseline justify-between">
                <dt class="text-sm text-secondary">Open tickets</dt>
                <dd class="numeric text-2xl font-bold">{{ $hd['open'] }}</dd>
            </div>
            <div class="flex items-baseline justify-between">
                <dt class="text-sm text-secondary">Past SLA</dt>
                <dd class="numeric text-2xl font-bold {{ $hd['breached'] > 0 ? 'text-[var(--color-critical)]' : '' }}">
                    {{ $hd['breached'] }}
                </dd>
            </div>
            <div class="flex items-baseline justify-between">
                <dt class="text-sm text-secondary">Resolved this month</dt>
                <dd class="numeric text-lg font-semibold">{{ $hd['resolved_this_month'] }}</dd>
            </div>
            @if ($hd['average_resolution_hours'] !== null)
                <div class="flex items-baseline justify-between border-t border-subtle pt-4">
                    <dt class="text-sm text-secondary">Average time to resolve</dt>
                    <dd class="numeric text-sm font-semibold">{{ $hd['average_resolution_hours'] }} hrs</dd>
                </div>
            @endif
        </dl>
    </x-ui.card>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    {{-- Who owes the most --}}
    <x-ui.card title="Largest outstanding" padded="false">
        <x-slot:actions>
            <x-ui.button variant="ghost" size="sm" :href="route('reports.index')">All reports</x-ui.button>
        </x-slot:actions>

        @if ($data['topDefaulters']->isEmpty())
            <x-ui.empty-state icon="check" title="Everyone is up to date" description="No unit has an outstanding balance." />
        @else
            <ul class="divide-y divide-[var(--border-subtle)]">
                @foreach ($data['topDefaulters'] as $row)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $row['unit']->label }}</p>
                            <p class="truncate text-xs text-muted">
                                {{ $row['contact']?->name ?? 'No billing contact' }}
                                @if ($row['days_overdue'] > 0)
                                    · {{ $row['days_overdue'] }} days overdue
                                @endif
                            </p>
                        </div>
                        <x-ui.money :amount="$row['outstanding']" class="text-sm font-semibold" tone="critical" />
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    {{-- Tickets needing attention --}}
    <x-ui.card title="Open tickets" padded="false">
        <x-slot:actions>
            <x-ui.button variant="ghost" size="sm" :href="route('complaints.index')">View all</x-ui.button>
        </x-slot:actions>

        @if ($data['recentComplaints']->isEmpty())
            <x-ui.empty-state icon="check" title="No open tickets" description="Nothing is waiting on the committee." />
        @else
            <ul class="divide-y divide-[var(--border-subtle)]">
                @foreach ($data['recentComplaints'] as $complaint)
                    <li class="px-5 py-3">
                        <a href="{{ route('complaints.show', $complaint) }}" class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ $complaint->title }}</p>
                                <p class="truncate text-xs text-muted">
                                    {{ $complaint->ticket_number }} · {{ $complaint->unit?->label ?? 'Common area' }}
                                    @if ($complaint->assignee) · {{ $complaint->assignee->name }} @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <x-ui.badge :tone="match ($complaint->priority) {
                                    'urgent' => 'critical', 'high' => 'caution', 'medium' => 'info', default => 'neutral',
                                }">{{ ucfirst($complaint->priority) }}</x-ui.badge>
                                @if ($complaint->hasBreachedSla())
                                    <span class="text-[0.6875rem] font-semibold text-[var(--color-critical)]">Past SLA</span>
                                @endif
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>

@if ($data['upcomingMeetings']->isNotEmpty())
    <x-ui.card title="Upcoming meetings" class="mt-6" padded="false">
        <ul class="divide-y divide-[var(--border-subtle)]">
            @foreach ($data['upcomingMeetings'] as $meeting)
                <li class="flex items-center justify-between gap-3 px-5 py-3">
                    <div class="min-w-0">
                        <a href="{{ route('meetings.show', $meeting) }}" class="truncate text-sm font-semibold hover:underline">
                            {{ $meeting->title }}
                        </a>
                        <p class="truncate text-xs text-muted">
                            {{ $meeting->typeLabel() }} · {{ $meeting->scheduled_at->format('D, j M Y · g:i A') }}
                        </p>
                    </div>
                    @if ($meeting->noticeIsOverdue())
                        <x-ui.badge tone="critical">Notice overdue</x-ui.badge>
                    @else
                        <x-ui.badge tone="info">{{ $meeting->scheduled_at->diffForHumans() }}</x-ui.badge>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-ui.card>
@endif
