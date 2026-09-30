<div>
    <x-ui.page-header
        :title="$block ? 'Plan — '.$block->name : 'Site plan'"
        :description="$block
            ? ($floors->isEmpty()
                ? 'No units recorded in this building yet.'
                : $plan->floorLabel($floors->last()['floor']).' to '.$plan->floorLabel($floors->first()['floor'])
                    .' · '.$floors->sum(fn ($f) => $f['units']->count()).' units')
            : $society->name.' from above. Pick a building to see its flats.'">
        <x-slot:actions>
            @if ($block)
                <x-ui.button size="sm" variant="secondary" icon="chevron-left" wire:click="backToSite">
                    Whole site
                </x-ui.button>
            @elseif ($canArrange && ! $arranging)
                <x-ui.button size="sm" variant="secondary" icon="layout" wire:click="startArranging">
                    Arrange buildings
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- What the colours mean. The same control drives both views. --}}
    <div class="mb-5 flex flex-wrap items-center gap-4">
        <fieldset>
            <legend class="sr-only">Colour the plan by</legend>
            <div class="flex gap-1 rounded-xl surface-inset p-1">
                @foreach ($views as $key => $label)
                    <button
                        type="button"
                        wire:click="$set('view', '{{ $key }}')"
                        aria-pressed="{{ $view === $key ? 'true' : 'false' }}"
                        @class([
                            'min-h-11 rounded-lg px-4 text-sm font-semibold transition-colors',
                            'surface-raised text-primary shadow-[var(--shadow-card)]' => $view === $key,
                            'text-secondary hover:text-primary' => $view !== $key,
                        ])
                    >{{ $label }}</button>
                @endforeach
            </div>
        </fieldset>

        <ul class="flex flex-wrap items-center gap-3">
            @foreach ($legend as $entry)
                <li class="flex items-center gap-1.5 text-xs text-secondary">
                    <span @class([
                        'size-3 shrink-0 rounded-sm',
                        'bg-[var(--color-positive)]' => $entry['tone'] === 'positive',
                        'bg-[var(--color-info)]' => $entry['tone'] === 'info',
                        'bg-[var(--color-caution)]' => $entry['tone'] === 'caution',
                        'bg-[var(--color-critical)]' => $entry['tone'] === 'critical',
                        'surface-inset border border-strong' => $entry['tone'] === 'neutral',
                    ])></span>
                    {{ $entry['label'] }}
                </li>
            @endforeach
        </ul>
    </div>

    {{-- =============================== SITE ============================ --}}
    @if (! $block)
        @if ($arranging)
            <x-ui.alert tone="info" class="mb-4" title="Arranging the site">
                Positions are percentages of the plot: <strong>x</strong> across from the left,
                <strong>y</strong> down from the top. Type rather than drag, so this works with a
                keyboard and on a phone. The plan below updates as you go.
            </x-ui.alert>
        @endif

        @if ($blocks->isEmpty())
            <x-ui.empty-state icon="building" title="No buildings yet"
                description="Add blocks or wings under Units, and they will appear on the plan." />
        @else
            @php $unarranged = $blocks->where('arranged', false)->count(); @endphp

            @if ($unarranged > 0 && ! $arranging)
                <x-ui.alert tone="caution" class="mb-4"
                    title="{{ $unarranged }} {{ \Illuminate\Support\Str::plural('building', $unarranged) }} laid out automatically">
                    They are on a tidy grid rather than where they actually stand.
                    @if ($canArrange) Use &ldquo;Arrange buildings&rdquo; to match your site. @endif
                </x-ui.alert>
            @endif

            {{--
                The plot. A padded square whose children are positioned in
                percentages, so the whole plan scales with the screen and
                every building stays a real button: focusable, and announced
                with its own name and figures.
            --}}
            <div class="surface-card overflow-hidden p-3 sm:p-5">
                <div class="relative w-full overflow-hidden rounded-xl surface-sunken"
                    style="aspect-ratio: 4 / 3"
                    role="group"
                    aria-label="Site plan of {{ $society->name }}">

                    {{-- A faint grid, so the eye has something to measure against. --}}
                    <div class="pointer-events-none absolute inset-0 opacity-40"
                        style="background-image:
                            linear-gradient(to right, var(--border-subtle) 1px, transparent 1px),
                            linear-gradient(to bottom, var(--border-subtle) 1px, transparent 1px);
                            background-size: 10% 10%"
                        aria-hidden="true"></div>

                    @foreach ($features as $feature)
                        <div
                            class="absolute flex flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-strong surface-inset p-1 text-center"
                            style="left: {{ $feature->plan_x }}%; top: {{ $feature->plan_y }}%; width: {{ $feature->plan_width }}%; height: {{ $feature->plan_height }}%"
                        >
                            <x-ui.icon :name="$feature->icon()" class="size-4 text-muted" />
                            <span class="truncate text-[0.625rem] font-medium text-muted">{{ $feature->name }}</span>
                        </div>
                    @endforeach

                    @foreach ($blocks as $shape)
                        @php
                            $position = $arranging ? ($positions[$shape['id']] ?? $shape) : $shape;
                            $x = $position['x'] ?? $shape['x'];
                            $y = $position['y'] ?? $shape['y'];
                            $w = $position['width'] ?? $shape['width'];
                            $h = $position['height'] ?? $shape['height'];
                        @endphp
                        <button
                            type="button"
                            wire:click="openBlock({{ $shape['id'] }})"
                            wire:key="block-{{ $shape['id'] }}"
                            @disabled($arranging)
                            class="absolute flex flex-col items-center justify-center gap-0.5 rounded-xl border-2 border-[var(--accent)] accent-soft-bg p-2 text-center transition-transform hover:scale-[1.02] focus-visible:scale-[1.02] disabled:hover:scale-100"
                            style="left: {{ $x }}%; top: {{ $y }}%; width: {{ $w }}%; height: {{ $h }}%"
                            aria-label="{{ $shape['name'] }}: {{ $shape['units'] }} units over {{ $shape['floors'] }} floors. Open its floor plan."
                        >
                            <span class="truncate text-sm font-bold accent-text">{{ $shape['name'] }}</span>
                            <span class="numeric truncate text-[0.625rem] text-secondary">
                                {{ $shape['units'] }} {{ \Illuminate\Support\Str::plural('unit', $shape['units']) }}
                            </span>
                            <span class="numeric truncate text-[0.625rem] text-muted">
                                {{ $shape['floors'] }} {{ \Illuminate\Support\Str::plural('floor', $shape['floors']) }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            @if ($arranging)
                <x-ui.card title="Positions" class="mt-5"
                    description="All four numbers are percentages of the plot.">
                    <form wire:submit="saveArrangement" class="space-y-3">
                        @foreach ($blocks as $shape)
                            <div class="flex flex-wrap items-end gap-3 rounded-xl border border-subtle p-3">
                                <p class="w-24 shrink-0 text-sm font-semibold">{{ $shape['name'] }}</p>
                                @foreach ([
                                    'x' => 'From left',
                                    'y' => 'From top',
                                    'width' => 'Width',
                                    'height' => 'Height',
                                ] as $field => $label)
                                    <div class="w-28">
                                        <x-ui.input
                                            type="number"
                                            min="0"
                                            max="100"
                                            :label="$label"
                                            :id="'pos-'.$shape['id'].'-'.$field"
                                            wire:model.live.debounce.300ms="positions.{{ $shape['id'] }}.{{ $field }}"
                                        />
                                    </div>
                                @endforeach
                            </div>
                        @endforeach

                        <div class="flex flex-wrap justify-end gap-2 pt-2">
                            <x-ui.button variant="ghost" wire:click="resetArrangement" type="button"
                                wire:confirm="Put every building back on the automatic grid?">
                                Reset to the grid
                            </x-ui.button>
                            <x-ui.button variant="secondary" wire:click="cancelArranging" type="button">Cancel</x-ui.button>
                            <x-ui.button type="submit" icon="check">Save the plan</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @endif

            {{-- The same information as a list. A plan is a picture; some
                 people need the numbers, and every screen reader does. --}}
            <details class="mt-5">
                <summary class="min-h-11 cursor-pointer py-3 text-sm font-medium text-secondary hover:text-primary">
                    Read the buildings as a list
                </summary>
                <ul class="mt-2 space-y-2">
                    @foreach ($blocks as $shape)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-subtle p-3">
                            <span class="text-sm font-semibold">{{ $shape['name'] }}</span>
                            <span class="numeric text-sm text-secondary">
                                {{ $shape['units'] }} units · {{ $shape['floors'] }} floors
                            </span>
                            <x-ui.button size="sm" variant="ghost" wire:click="openBlock({{ $shape['id'] }})">
                                Open floor plan
                            </x-ui.button>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    @endif

    {{-- ============================== BLOCK ============================= --}}
    @if ($block)
        <div class="grid gap-5 lg:grid-cols-[1fr_20rem]">
            <div class="surface-card p-3 sm:p-5">
                @if ($floors->isEmpty())
                    <x-ui.empty-state icon="building" title="No units in this building yet" />
                @else
                    <div class="space-y-2" role="group" aria-label="Floors of {{ $block->name }}, top floor first">
                        @foreach ($floors as $level)
                            <div class="flex items-stretch gap-3">
                                {{-- The floor's own label, so a flat's position
                                     is readable without counting rows. --}}
                                <div class="flex w-20 shrink-0 items-center justify-end">
                                    <span class="text-xs font-semibold text-secondary">{{ $level['label'] }}</span>
                                </div>

                                <div class="flex flex-1 flex-wrap gap-2 rounded-xl surface-sunken p-2">
                                    @foreach ($level['units'] as $unit)
                                        @php
                                            $tone = $plan->toneFor($unit, $view);
                                            $meaning = $plan->meaningFor($unit, $view);
                                        @endphp
                                        <button
                                            type="button"
                                            wire:key="unit-{{ $unit->id }}"
                                            wire:click="selectUnit({{ $unit->id }})"
                                            aria-pressed="{{ $selected?->id === $unit->id ? 'true' : 'false' }}"
                                            aria-label="{{ $unit->label }}, {{ $level['label'] }}. {{ $meaning }}."
                                            @class([
                                                'flex min-h-14 min-w-16 flex-col items-center justify-center rounded-lg border px-2 py-1.5 text-center transition-colors',
                                                'border-[var(--color-positive)] bg-[var(--color-positive-soft)]' => $tone === 'positive',
                                                'border-[var(--color-info)] bg-[var(--color-info-soft)]' => $tone === 'info',
                                                'border-[var(--color-caution)] bg-[var(--color-caution-soft)]' => $tone === 'caution',
                                                'border-[var(--color-critical)] bg-[var(--color-critical-soft)]' => $tone === 'critical',
                                                'border-subtle surface-raised' => $tone === 'neutral',
                                                'ring-2 ring-[var(--accent)] ring-offset-2' => $selected?->id === $unit->id,
                                            ])
                                        >
                                            <span class="numeric text-sm font-bold">{{ $unit->unit_number }}</span>
                                            @if ($unit->configuration)
                                                <span class="text-[0.625rem] text-secondary">{{ $unit->configuration }}</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- The details panel. Tapping a flat should answer the question
                 without leaving the plan. --}}
            <div>
                @if ($selected)
                    <x-ui.card :title="$selected->label" :description="$plan->floorLabel((int) ($selected->floor ?? 0))">
                        <x-slot:actions>
                            {{-- Icon-only, so it gets a full-size target. --}}
                            <x-ui.button size="icon" variant="ghost" icon="close" wire:click="selectUnit({{ $selected->id }})">
                                <span class="sr-only">Close the details panel</span>
                            </x-ui.button>
                        </x-slot:actions>

                        <dl class="space-y-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-secondary">Occupancy</dt>
                                <dd><x-ui.badge :tone="match ($selected->occupancy_status) {
                                    'owner_occupied' => 'positive',
                                    'rented' => 'info',
                                    'vacant' => 'caution',
                                    default => 'neutral',
                                }" dot>{{ ucwords(str_replace('_', ' ', $selected->occupancy_status)) }}</x-ui.badge></dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-secondary">Outstanding</dt>
                                <dd class="font-semibold {{ (float) ($selected->balance_due ?? 0) > 0 ? 'text-[var(--color-critical)]' : '' }}">
                                    <x-ui.money :amount="(float) ($selected->balance_due ?? 0)" />
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-secondary">Open complaints</dt>
                                <dd class="numeric font-semibold">{{ $selected->open_complaints_count ?? 0 }}</dd>
                            </div>
                            @if ($selected->configuration)
                                <div class="flex items-center justify-between gap-2">
                                    <dt class="text-secondary">Configuration</dt>
                                    <dd>{{ $selected->configuration }}</dd>
                                </div>
                            @endif
                            @if ($selected->carpet_area)
                                <div class="flex items-center justify-between gap-2">
                                    <dt class="text-secondary">Carpet area</dt>
                                    <dd class="numeric">{{ rtrim(rtrim(number_format((float) $selected->carpet_area, 2), '0'), '.') }} {{ $society->areaUnitLabel() }}</dd>
                                </div>
                            @endif
                        </dl>

                        @php $current = $selected->currentResidents(); @endphp
                        <div class="mt-4 border-t border-subtle pt-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Who lives here</p>
                            @if ($current->isEmpty())
                                <p class="text-sm text-secondary">Nobody is recorded as living here.</p>
                            @else
                                <ul class="space-y-2">
                                    @foreach ($current as $resident)
                                        <li class="flex items-center justify-between gap-2">
                                            <span class="min-w-0 truncate text-sm">{{ $resident->user?->name }}</span>
                                            <x-ui.badge :tone="$resident->isOwner() ? 'accent' : 'info'">
                                                {{ ucwords(str_replace('_', ' ', $resident->relation)) }}
                                            </x-ui.badge>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <x-ui.button :href="route('units.show', $selected)" wire:navigate
                            variant="secondary" class="mt-4 w-full" icon="arrow-path">
                            Open the full record
                        </x-ui.button>
                    </x-ui.card>
                @else
                    <x-ui.card>
                        <x-ui.empty-state icon="map-pin" title="Pick a flat"
                            description="Tap any flat on the plan to see who lives there, what it owes and what is open." />
                    </x-ui.card>
                @endif
            </div>
        </div>
    @endif
</div>
