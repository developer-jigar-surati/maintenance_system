<div>
    <div class="mb-5">
        <a href="{{ route('polls.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-secondary hover:text-primary">
            <x-ui.icon name="chevron-left" class="size-4" /> All polls
        </a>
    </div>

    <x-ui.page-header :title="$poll->title">
        <x-slot:description>
            {{ $poll->isOpen() ? 'Closes '.$poll->ends_at->diffForHumans() : 'Closed '.$poll->ends_at->format('j M Y') }}
            ·
            {{ match ($poll->voting_basis) {
                'per_user' => 'one vote per person',
                'per_unit' => 'one vote per unit',
                default => 'weighted by unit area',
            } }}
        </x-slot:description>
        <x-slot:actions><x-ui.status :value="$poll->status" /></x-slot:actions>
    </x-ui.page-header>

    @if ($poll->description)
        <x-ui.card class="mb-6">
            <p class="whitespace-pre-line text-sm leading-relaxed">{{ $poll->description }}</p>
        </x-ui.card>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @if ($poll->isOpen() && ! $hasVoted)
                <x-ui.card title="Cast your vote">
                    <form wire:submit="vote" class="space-y-3">
                        <fieldset>
                            <legend class="sr-only">Choose one option</legend>
                            <div class="space-y-2">
                                @foreach ($poll->options as $option)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition-colors
                                        {{ $choice === $option->id ? 'accent-soft-bg border-[var(--accent)]' : 'border-subtle hover:surface-inset' }}">
                                        <input
                                            type="radio"
                                            wire:model.live="choice"
                                            value="{{ $option->id }}"
                                            name="poll-option"
                                            class="size-4 accent-[var(--accent)]"
                                        >
                                        <span class="text-sm font-medium">{{ $option->label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        @error('choice')
                            <p class="text-sm font-medium text-[var(--color-critical)]">{{ $message }}</p>
                        @enderror

                        <x-ui.button type="submit" :disabled="$choice === null">
                            <span wire:loading.remove wire:target="vote">Submit vote</span>
                            <span wire:loading wire:target="vote">Submitting&hellip;</span>
                        </x-ui.button>
                    </form>
                </x-ui.card>
            @elseif ($hasVoted && ! $poll->resultsVisible())
                <x-ui.card>
                    <x-ui.empty-state icon="check-badge" title="Your vote is recorded"
                        description="Results will be published once the poll closes." />
                </x-ui.card>
            @endif

            @if ($tally !== null)
                <x-ui.card title="Results" class="{{ $poll->isOpen() && ! $hasVoted ? 'mt-6' : '' }}">
                    <ul class="space-y-4">
                        @foreach ($tally as $row)
                            <li>
                                <div class="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                                    <span class="font-medium">{{ $row['option']->label }}</span>
                                    <span class="numeric text-secondary">
                                        {{ $row['percent'] }}% · {{ $row['votes'] }} {{ \Illuminate\Support\Str::plural('vote', $row['votes']) }}
                                    </span>
                                </div>
                                {{-- The bar is decorative; the figures above carry the meaning. --}}
                                <div class="h-2.5 overflow-hidden rounded-full surface-inset" role="presentation">
                                    <div class="h-full rounded-full accent-bg" style="width: {{ max(1, $row['percent']) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card title="Turnout">
                <p class="numeric text-3xl font-bold">{{ $turnout['percent'] }}%</p>
                <p class="mt-1 text-sm text-secondary">
                    {{ $turnout['cast'] }} of {{ $turnout['eligible'] }} eligible
                </p>
                <div class="mt-3 h-2 overflow-hidden rounded-full surface-inset" role="presentation">
                    <div class="h-full rounded-full accent-bg" style="width: {{ max(1, $turnout['percent']) }}%"></div>
                </div>
            </x-ui.card>

            @if ($poll->meeting)
                <x-ui.card title="Linked meeting">
                    <a href="{{ route('meetings.show', $poll->meeting) }}" class="text-sm font-medium accent-text hover:underline">
                        {{ $poll->meeting->title }}
                    </a>
                </x-ui.card>
            @endif

            @if ($canManage && $poll->status === 'open')
                <x-ui.button variant="secondary" class="w-full" wire:click="close"
                    wire:confirm="Close this poll and record the result?">
                    Close poll
                </x-ui.button>
            @endif
        </div>
    </div>
</div>
