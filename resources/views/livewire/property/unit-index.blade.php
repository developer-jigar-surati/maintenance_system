<div>
    <x-ui.page-header title="Units" :description="'Every flat, villa, shop or plot in '.$society->name.'.'" />

    <x-ui.table
        :headers="['Unit', 'Type', 'Area', 'Occupancy', 'Residents', ['label' => 'Outstanding', 'align' => 'right'], '']"
        :is-empty="$units->isEmpty()"
        empty="No units match these filters"
        empty-icon="building"
        caption="Units with type, area, occupancy and outstanding balance"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Unit number or resident" aria-label="Search units" />
            </div>

            @if ($blocks->isNotEmpty())
                <x-ui.select wire:model.live="blockId" aria-label="Filter by block" class="w-auto min-w-32">
                    <option value="">All blocks</option>
                    @foreach ($blocks as $block)
                        <option value="{{ $block->id }}">{{ $block->name }}</option>
                    @endforeach
                </x-ui.select>
            @endif

            <x-ui.select wire:model.live="occupancy" aria-label="Filter by occupancy" class="w-auto min-w-36">
                <option value="">Any occupancy</option>
                @foreach (['owner_occupied' => 'Owner occupied', 'rented' => 'Rented', 'vacant' => 'Vacant', 'locked' => 'Locked', 'under_construction' => 'Under construction'] as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </x-ui.select>

            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($units as $unit)
            <x-ui.tr :href="route('units.show', $unit)">
                <x-ui.td label="Unit" primary>
                    <a href="{{ route('units.show', $unit) }}" class="hover:underline">{{ $unit->label }}</a>
                    @if ($unit->floor)
                        <span class="block text-xs text-muted">Floor {{ $unit->floor }}</span>
                    @endif
                </x-ui.td>
                <x-ui.td label="Type">
                    {{ ucwords(str_replace('_', ' ', $unit->type)) }}
                    @if ($unit->configuration)
                        <span class="block text-xs text-muted">{{ $unit->configuration }}</span>
                    @endif
                </x-ui.td>
                <x-ui.td label="Area">
                    @if ($unit->carpet_area)
                        <span class="numeric">{{ rtrim(rtrim(number_format((float) $unit->carpet_area, 2), '0'), '.') }}</span>
                        <span class="text-xs text-muted">{{ $society->areaUnitLabel() }}</span>
                    @else
                        <span class="text-muted">–</span>
                    @endif
                </x-ui.td>
                <x-ui.td label="Occupancy">
                    <x-ui.badge :tone="match ($unit->occupancy_status) {
                        'owner_occupied' => 'positive', 'rented' => 'info', 'vacant' => 'neutral', default => 'caution',
                    }">{{ ucwords(str_replace('_', ' ', $unit->occupancy_status)) }}</x-ui.badge>
                </x-ui.td>
                <x-ui.td label="Residents">
                    @php $primary = $unit->activeResidents->first(); @endphp
                    @if ($primary)
                        {{ $primary->user?->name }}
                        @if ($unit->activeResidents->count() > 1)
                            <span class="block text-xs text-muted">+{{ $unit->activeResidents->count() - 1 }} more</span>
                        @endif
                    @else
                        <span class="text-muted">None linked</span>
                    @endif
                </x-ui.td>
                <x-ui.td label="Outstanding" align="right">
                    <x-ui.money :amount="$unit->outstanding ?? 0"
                        :tone="($unit->outstanding ?? 0) > 0 ? 'critical' : 'positive'" class="font-semibold" />
                </x-ui.td>
                <x-ui.td align="right">
                    <a href="{{ route('units.show', $unit) }}"
                       class="inline-flex items-center gap-1 text-xs font-semibold accent-text hover:underline">
                        View <x-ui.icon name="chevron-right" class="size-3.5" />
                    </a>
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $units->links() }}</x-slot:footer>
    </x-ui.table>
</div>
