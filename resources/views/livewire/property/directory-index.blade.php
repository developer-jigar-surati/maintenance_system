<div>
    <x-ui.page-header title="Directory" description="Who lives where." />

    @unless ($showContacts)
        <x-ui.alert tone="info" class="mb-5">
            Phone numbers are hidden. A committee member can reveal them for all residents
            in society settings.
        </x-ui.alert>
    @endunless

    <div class="mb-4 flex flex-wrap gap-3">
        <div class="w-full sm:max-w-xs">
            <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                placeholder="Name or unit" aria-label="Search directory" />
        </div>
        @if ($blocks->isNotEmpty())
            <x-ui.select wire:model.live="blockId" aria-label="Filter by block" class="w-auto min-w-32">
                <option value="">All blocks</option>
                @foreach ($blocks as $block)
                    <option value="{{ $block->id }}">{{ $block->name }}</option>
                @endforeach
            </x-ui.select>
        @endif
    </div>

    @if ($units->isEmpty())
        <x-ui.card><x-ui.empty-state icon="book" title="No entries yet" /></x-ui.card>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($units as $unit)
                <div class="surface-card p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-semibold">{{ $unit->label }}</p>
                        <x-ui.badge tone="neutral">{{ ucwords(str_replace('_', ' ', $unit->occupancy_status)) }}</x-ui.badge>
                    </div>
                    <ul class="mt-3 space-y-2">
                        @foreach ($unit->activeResidents as $resident)
                            <li class="flex items-center gap-2.5">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full accent-soft-bg text-xs font-bold accent-text">
                                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($resident->user?->name ?? '?', 0, 1)) }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">{{ $resident->user?->name }}</span>
                                    <span class="block truncate text-xs text-muted">
                                        {{ ucwords(str_replace('_', ' ', $resident->relation)) }}
                                        @if ($showContacts && $resident->user?->phone)
                                            · <a href="tel:{{ $resident->user->phone }}" class="numeric accent-text hover:underline">{{ $resident->user->phone }}</a>
                                        @endif
                                    </span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="mt-5">{{ $units->links() }}</div>
    @endif
</div>
