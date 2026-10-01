<div>
    <x-ui.page-header title="Polls" description="Decisions put to the members." />

    <div class="mb-4 flex flex-wrap gap-3">
        <div class="w-full sm:max-w-xs">
            <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                placeholder="Poll title" aria-label="Search polls" />
        </div>
        <x-ui.select wire:model.live="status" aria-label="Filter by status" class="w-auto min-w-32">
            <option value="">All statuses</option>
            @foreach (['draft', 'open', 'closed', 'cancelled'] as $v)
                <option value="{{ $v }}">{{ ucfirst($v) }}</option>
            @endforeach
        </x-ui.select>
        @if ($this->hasActiveFilters())
            <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
        @endif
    </div>

    @if ($polls->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="check-badge" title="No polls yet"
                description="Polls let the committee take a decision to the members without waiting for a meeting." />
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($polls as $poll)
                <a href="{{ route('polls.show', $poll) }}" class="surface-card block p-5 transition-colors hover:surface-sunken">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="font-semibold">{{ $poll->title }}</h2>
                        <x-ui.status :value="$poll->status" />
                    </div>

                    @if ($poll->description)
                        <p class="mt-1.5 line-clamp-2 text-sm text-secondary">{{ $poll->description }}</p>
                    @endif

                    <dl class="mt-4 flex flex-wrap gap-x-5 gap-y-1 border-t border-subtle pt-3 text-xs">
                        <div>
                            <dt class="text-muted">Basis</dt>
                            <dd class="mt-0.5 font-medium">
                                {{ match ($poll->voting_basis) {
                                    'per_user' => 'One vote per person',
                                    'per_unit' => 'One vote per unit',
                                    default => 'Weighted by area',
                                } }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted">Votes cast</dt>
                            <dd class="numeric mt-0.5 font-medium">{{ $poll->votes_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">{{ $poll->hasClosed() ? 'Closed' : 'Closes' }}</dt>
                            <dd class="mt-0.5 font-medium">{{ $poll->ends_at->format('j M Y') }}</dd>
                        </div>
                    </dl>

                    @if ($poll->isOpen() && ! $poll->hasVoted(auth()->user()))
                        <p class="mt-3 text-sm font-semibold accent-text">Your vote is pending &rarr;</p>
                    @elseif ($poll->hasVoted(auth()->user()))
                        <p class="mt-3 text-sm font-medium text-[var(--color-positive)]">You have voted</p>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="mt-5">{{ $polls->links() }}</div>
    @endif
</div>
