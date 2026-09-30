{{-- The resident's own position, front and centre. --}}
<div class="grid gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="surface-card overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-4 p-5 sm:p-6">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-muted">Your outstanding balance</p>
                    <p class="numeric mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                        <x-ui.money :amount="$data['outstanding']" :tone="$data['outstanding'] > 0 ? 'critical' : 'positive'" />
                    </p>
                    <p class="mt-1.5 text-sm text-secondary">
                        @if ($data['outstanding'] <= 0)
                            You are fully paid up. Thank you.
                        @elseif ($data['overdueCount'] > 0)
                            {{ $data['overdueCount'] }} {{ \Illuminate\Support\Str::plural('bill', $data['overdueCount']) }}
                            past the due date.
                        @else
                            Due shortly - no bills are overdue yet.
                        @endif
                    </p>
                </div>

                @if ($data['outstanding'] > 0)
                    <x-ui.button :href="route('invoices.index')" icon="receipt" size="lg">
                        {{ $society->acceptsOnlinePayments() ? 'Pay now' : 'View bills' }}
                    </x-ui.button>
                @endif
            </div>

            @if ($data['openInvoices']->isNotEmpty())
                <ul class="divide-y divide-[var(--border-subtle)] border-t border-subtle">
                    @foreach ($data['openInvoices'] as $invoice)
                        <li class="flex items-center justify-between gap-3 px-5 py-3 sm:px-6">
                            <div class="min-w-0">
                                <a href="{{ route('invoices.show', $invoice) }}" class="truncate text-sm font-medium hover:underline">
                                    {{ $invoice->invoice_number }}
                                </a>
                                <p class="truncate text-xs text-muted">
                                    {{ $invoice->unit?->label }} ·
                                    due {{ $invoice->due_date->format('j M Y') }}
                                    @if ($invoice->isOverdue())
                                        · <span class="font-semibold text-[var(--color-critical)]">{{ $invoice->daysOverdue() }} days late</span>
                                    @endif
                                </p>
                            </div>
                            <x-ui.money :amount="$invoice->balance" class="text-sm font-semibold" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Quick actions, sized for a thumb. --}}
    <x-ui.card title="Quick actions">
        <div class="grid grid-cols-2 gap-2">
            @can(\App\Enums\Permission::COMPLAINT_CREATE)
                <x-ui.button variant="secondary" :href="route('complaints.index')" icon="lifebuoy" class="flex-col !py-4 text-xs">
                    Raise a ticket
                </x-ui.button>
            @endcan
            @can(\App\Enums\Permission::VISITOR_MANAGE)
                <x-ui.button variant="secondary" :href="route('visitors.index')" icon="user-plus" class="flex-col !py-4 text-xs">
                    Expect a visitor
                </x-ui.button>
            @endcan
            @can(\App\Enums\Permission::AMENITY_BOOK)
                <x-ui.button variant="secondary" :href="route('amenities.index')" icon="sparkles" class="flex-col !py-4 text-xs">
                    Book an amenity
                </x-ui.button>
            @endcan
            @can(\App\Enums\Permission::DOCUMENT_VIEW)
                <x-ui.button variant="secondary" :href="route('documents.index')" icon="folder" class="flex-col !py-4 text-xs">
                    Documents
                </x-ui.button>
            @endcan
        </div>

        @if ($data['units']->isNotEmpty())
            <div class="mt-5 border-t border-subtle pt-4">
                <p class="text-xs font-medium uppercase tracking-wide text-muted">Your {{ \Illuminate\Support\Str::plural('unit', $data['units']->count()) }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($data['units'] as $unit)
                        <x-ui.badge tone="accent">{{ $unit->label }}</x-ui.badge>
                    @endforeach
                </div>
            </div>
        @endif
    </x-ui.card>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    {{-- Notices --}}
    <x-ui.card title="Notice board" padded="false">
        <x-slot:actions>
            <x-ui.button variant="ghost" size="sm" :href="route('notices.index')">View all</x-ui.button>
        </x-slot:actions>

        @if ($data['notices']->isEmpty())
            <x-ui.empty-state icon="megaphone" title="No notices right now" />
        @else
            <ul class="divide-y divide-[var(--border-subtle)]">
                @foreach ($data['notices'] as $notice)
                    <li class="px-5 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ $notice->title }}</p>
                                <p class="mt-0.5 line-clamp-2 text-xs text-secondary">
                                    {{ \Illuminate\Support\Str::limit(strip_tags($notice->body), 120) }}
                                </p>
                                <p class="mt-1 text-xs text-muted">{{ $notice->published_at?->diffForHumans() }}</p>
                            </div>
                            @if ($notice->priority !== 'normal')
                                <x-ui.badge :tone="$notice->priority === 'critical' ? 'critical' : 'caution'">
                                    {{ ucfirst($notice->priority) }}
                                </x-ui.badge>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    {{-- My tickets --}}
    <x-ui.card title="Your tickets" padded="false">
        <x-slot:actions>
            <x-ui.button variant="ghost" size="sm" :href="route('complaints.index')">View all</x-ui.button>
        </x-slot:actions>

        @if ($data['myComplaints']->isEmpty())
            <x-ui.empty-state icon="check" title="No open tickets" description="Raise one if something needs attention." />
        @else
            <ul class="divide-y divide-[var(--border-subtle)]">
                @foreach ($data['myComplaints'] as $complaint)
                    <li class="px-5 py-3">
                        <a href="{{ route('complaints.show', $complaint) }}" class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ $complaint->title }}</p>
                                <p class="truncate text-xs text-muted">
                                    {{ $complaint->ticket_number }} · {{ $complaint->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <x-ui.status :value="$complaint->status" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>

{{-- Things coming up --}}
@if ($data['meetings']->isNotEmpty() || $data['openPolls']->isNotEmpty() || $data['bookings']->isNotEmpty() || $data['expectedVisitors']->isNotEmpty())
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @if ($data['meetings']->isNotEmpty() || $data['openPolls']->isNotEmpty())
            <x-ui.card title="Your voice" padded="false">
                <ul class="divide-y divide-[var(--border-subtle)]">
                    @foreach ($data['meetings'] as $meeting)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <a href="{{ route('meetings.show', $meeting) }}" class="truncate text-sm font-semibold hover:underline">
                                    {{ $meeting->title }}
                                </a>
                                <p class="truncate text-xs text-muted">{{ $meeting->scheduled_at->format('D, j M · g:i A') }}</p>
                            </div>
                            <x-ui.badge tone="info">Meeting</x-ui.badge>
                        </li>
                    @endforeach
                    @foreach ($data['openPolls'] as $poll)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <a href="{{ route('polls.show', $poll) }}" class="truncate text-sm font-semibold hover:underline">
                                    {{ $poll->title }}
                                </a>
                                <p class="truncate text-xs text-muted">Closes {{ $poll->ends_at->diffForHumans() }}</p>
                            </div>
                            @if ($poll->hasVoted(auth()->user()))
                                <x-ui.badge tone="positive">Voted</x-ui.badge>
                            @else
                                <x-ui.badge tone="caution">Vote now</x-ui.badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        @if ($data['bookings']->isNotEmpty() || $data['expectedVisitors']->isNotEmpty())
            <x-ui.card title="Coming up" padded="false">
                <ul class="divide-y divide-[var(--border-subtle)]">
                    @foreach ($data['bookings'] as $booking)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ $booking->amenity->name }}</p>
                                <p class="truncate text-xs text-muted">{{ $booking->starts_at->format('D, j M · g:i A') }}</p>
                            </div>
                            <x-ui.status :value="$booking->status" />
                        </li>
                    @endforeach
                    @foreach ($data['expectedVisitors'] as $visitor)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ $visitor->visitor_name }}</p>
                                <p class="truncate text-xs text-muted">
                                    {{ $visitor->purposeLabel() }}
                                    @if ($visitor->pass_code) · code {{ $visitor->pass_code }} @endif
                                </p>
                            </div>
                            <x-ui.status :value="$visitor->status" />
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
    </div>
@endif
