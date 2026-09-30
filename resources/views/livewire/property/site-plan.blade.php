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

    {{-- What the colours mean, and whether to draw it flat or with height.
         The same figures drive both. --}}
    <div class="mb-5 flex flex-wrap items-center gap-4">
        <fieldset>
            <legend class="sr-only">Draw the plan</legend>
            <div class="flex gap-1 rounded-xl surface-inset p-1">
                @foreach (['2d' => 'Flat plan', '3d' => '3D'] as $key => $label)
                    <button
                        type="button"
                        wire:click="$set('mode', '{{ $key }}')"
                        aria-pressed="{{ $mode === $key ? 'true' : 'false' }}"
                        @class([
                            'min-h-11 rounded-lg px-4 text-sm font-semibold transition-colors',
                            'surface-raised text-primary shadow-[var(--shadow-card)]' => $mode === $key,
                            'text-secondary hover:text-primary' => $mode !== $key,
                        ])
                    >{{ $label }}</button>
                @endforeach
            </div>
        </fieldset>

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

            @if ($mode === '3d')
            {{--
                The 3D site.

                A ground plane tilted away from the viewer, with each building
                extruded along +Z by its floor count -- which is what makes a
                six-storey block read as taller than a row of villas, and what
                a flat plan cannot show. Every building is still a real button
                carrying its own label, so the scene works by keyboard and
                reads out to a screen reader exactly as the flat plan does.

                Orientation is held in Alpine rather than on the server: a
                quarter turn should be instant, not a round trip.
            --}}
            <div
                class="surface-card overflow-hidden p-3 sm:p-5"
                x-data="{
                    narrow: window.innerWidth < 640,
                    spin: 0,
                    tilt: 58,
                    zoom: window.innerWidth < 640 ? 0.78 : 0.95,
                    turn(by) { this.spin = (this.spin + by) % 360 },
                    reset() { this.spin = 0; this.tilt = 58; this.zoom = this.narrow ? 0.78 : 0.95 },
                }"
            >
                <div
                    class="scene-3d relative aspect-square w-full overflow-hidden rounded-xl surface-sunken sm:aspect-[4/3]"
                    role="group"
                    aria-label="Three-dimensional site plan of {{ $society->name }}"
                >
                    <div
                        class="scene-world"
                        x-bind:style="`--spin:${spin}deg; --tilt:${tilt}deg; --zoom:${zoom}`"
                    >
                        {{-- The plot itself, so buildings stand on something. --}}
                        <div class="absolute inset-0 rounded-lg surface-inset"
                            style="background-image:
                                linear-gradient(to right, var(--border-subtle) 1px, transparent 1px),
                                linear-gradient(to bottom, var(--border-subtle) 1px, transparent 1px);
                                background-size: 10% 10%"
                            aria-hidden="true"></div>

                        {{-- Landmarks lie flat on the ground, as they do. --}}
                        @foreach ($features as $feature)
                            <div
                                class="absolute flex items-center justify-center rounded-md border border-dashed border-strong surface-raised/60 text-center"
                                style="left: {{ $feature->plan_x }}%; top: {{ $feature->plan_y }}%; width: {{ $feature->plan_width }}%; height: {{ $feature->plan_height }}%"
                                aria-hidden="true"
                            >
                                <span class="truncate px-1 text-[0.5rem] font-medium text-muted">{{ $feature->name }}</span>
                            </div>
                        @endforeach

                        @foreach ($blocks as $shape)
                            {{-- Extrusion is the floor count, so height means
                                 something rather than being decoration. --}}
                            <div
                                class="solid-3d"
                                wire:key="solid-{{ $shape['id'] }}"
                                style="left: {{ $shape['x'] }}%; top: {{ $shape['y'] }}%; width: {{ $shape['width'] }}%; height: {{ $shape['height'] }}%; --extrude: {{ min(160, max(16, $shape['floors'] * 16)) }}px"
                            >
                                <div class="face face-n" aria-hidden="true"></div>
                                <div class="face face-s" aria-hidden="true"></div>
                                <div class="face face-w" aria-hidden="true"></div>
                                <div class="face face-e" aria-hidden="true"></div>

                                {{-- The roof carries the label and the click:
                                     it is the face that always faces you. --}}
                                <button
                                    type="button"
                                    wire:click="openBlock({{ $shape['id'] }})"
                                    class="face face-roof cursor-pointer transition-[filter] hover:brightness-95 focus-visible:brightness-95"
                                    aria-label="{{ $shape['name'] }}: {{ $shape['units'] }} units over {{ $shape['floors'] }} floors. Open its floor plan."
                                >
                                    <span class="billboard">
                                        <span class="truncate text-base font-bold accent-text">{{ $shape['name'] }}</span>
                                        <span class="numeric truncate text-[0.625rem] font-medium text-secondary">
                                            {{ $shape['floors'] }}F · {{ $shape['units'] }}U
                                        </span>
                                    </span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Orientation controls. Buttons and a slider rather than
                     drag, so this works with a keyboard and on a phone. --}}
                <div class="mt-4 flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <x-ui.button size="icon" variant="secondary" x-on:click="turn(-45)">
                            <x-ui.icon name="arrow-path" class="size-4 -scale-x-100" />
                            <span class="sr-only">Turn the site 45 degrees anticlockwise</span>
                        </x-ui.button>
                        <x-ui.button size="icon" variant="secondary" x-on:click="turn(45)">
                            <x-ui.icon name="arrow-path" class="size-4" />
                            <span class="sr-only">Turn the site 45 degrees clockwise</span>
                        </x-ui.button>
                    </div>

                    <div class="flex min-w-48 flex-1 items-center gap-3">
                        <label for="scene-tilt" class="shrink-0 text-xs font-medium text-secondary">Tilt</label>
                        <input
                            id="scene-tilt"
                            type="range"
                            min="20"
                            max="80"
                            step="1"
                            x-model.number="tilt"
                            class="h-11 w-full accent-[var(--accent)]"
                        >
                    </div>

                    <div class="flex min-w-40 flex-1 items-center gap-3">
                        <label for="scene-zoom" class="shrink-0 text-xs font-medium text-secondary">Zoom</label>
                        <input
                            id="scene-zoom"
                            type="range"
                            min="0.5"
                            max="1.3"
                            step="0.02"
                            x-model.number="zoom"
                            class="h-11 w-full accent-[var(--accent)]"
                        >
                    </div>

                    <x-ui.button size="sm" variant="ghost" x-on:click="reset()">Reset the view</x-ui.button>
                </div>
            </div>
            @endif

            @if ($mode === '2d')
            {{--
                The flat plan. A padded square whose children are positioned in
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

            @endif

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

                @elseif ($mode === '3d')
                    {{--
                        The building in 3D: one slab per floor, stacked in the
                        order they are built, and separable so you can see into
                        the middle of a tower instead of only its roof. Each
                        flat is the same real button the flat plan uses, with
                        the same label, so nothing is lost by looking at it
                        this way.
                    --}}
                    <div
                        x-data="{
                            /* A phone has a third of the room, so it starts
                               further back and with the floors closer. */
                            narrow: window.innerWidth < 640,
                            spin: 0,
                            tilt: 44,
                            zoom: window.innerWidth < 640 ? 0.6 : 1.05,
                            gap: window.innerWidth < 640 ? 46 : 62,
                            turn(by) { this.spin = (this.spin + by) % 360 },
                            reset() {
                                this.spin = 0;
                                this.tilt = 44;
                                this.zoom = this.narrow ? 0.6 : 1.05;
                                this.gap = this.narrow ? 46 : 62;
                            },
                        }"
                    >
                        <div
                            class="scene-3d relative aspect-[3/4] w-full overflow-hidden rounded-xl surface-sunken sm:aspect-[4/3]"
                            role="group"
                            aria-label="{{ $block->name }} in three dimensions, top floor first"
                        >
                            <div class="scene-world"
                                x-bind:style="`--spin:${spin}deg; --tilt:${tilt}deg; --zoom:${zoom}; --slab-gap:${gap}px; --pan:10%`">

                                @foreach ($floors->reverse()->values() as $index => $level)
                                    <div
                                        class="slab-3d absolute left-[8%] right-[8%] rounded-lg border border-subtle surface-raised/80"
                                        style="--level: {{ $index }}; top: 54%; height: 22%"
                                        wire:key="slab-{{ $level['floor'] }}"
                                    >
                                        <div class="flex h-full items-center gap-1.5 p-1.5">
                                            <span class="w-16 shrink-0 text-xs font-semibold text-secondary">
                                                {{ $level['label'] }}
                                            </span>

                                            <div class="flex flex-1 flex-wrap items-center gap-1.5">
                                                @foreach ($level['units'] as $unit)
                                                    @php
                                                        $tone = $plan->toneFor($unit, $view);
                                                        $meaning = $plan->meaningFor($unit, $view);
                                                    @endphp
                                                    <button
                                                        type="button"
                                                        wire:key="unit3d-{{ $unit->id }}"
                                                        wire:click="selectUnit({{ $unit->id }})"
                                                        aria-pressed="{{ $selected?->id === $unit->id ? 'true' : 'false' }}"
                                                        aria-label="{{ $unit->label }}, {{ $level['label'] }}. {{ $meaning }}."
                                                        @class([
                                                            {{-- Sized so the tilt still leaves a 44px target on
                                                                 screen: a tilted cell renders about 70% of its
                                                                 height, so 64px of layout is what it takes. --}}
                                                            'flex min-h-16 min-w-16 items-center justify-center rounded-md border px-2 transition-colors',
                                                            'border-[var(--color-positive)] bg-[var(--color-positive-soft)]' => $tone === 'positive',
                                                            'border-[var(--color-info)] bg-[var(--color-info-soft)]' => $tone === 'info',
                                                            'border-[var(--color-caution)] bg-[var(--color-caution-soft)]' => $tone === 'caution',
                                                            'border-[var(--color-critical)] bg-[var(--color-critical-soft)]' => $tone === 'critical',
                                                            'border-subtle surface-raised' => $tone === 'neutral',
                                                            'ring-2 ring-[var(--accent)]' => $selected?->id === $unit->id,
                                                        ])
                                                    >
                                                        <span class="numeric text-sm font-bold">{{ $unit->unit_number }}</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-4">
                            <div class="flex items-center gap-2">
                                <x-ui.button size="icon" variant="secondary" x-on:click="turn(-45)">
                                    <x-ui.icon name="arrow-path" class="size-4 -scale-x-100" />
                                    <span class="sr-only">Turn the building 45 degrees anticlockwise</span>
                                </x-ui.button>
                                <x-ui.button size="icon" variant="secondary" x-on:click="turn(45)">
                                    <x-ui.icon name="arrow-path" class="size-4" />
                                    <span class="sr-only">Turn the building 45 degrees clockwise</span>
                                </x-ui.button>
                            </div>

                            <div class="flex min-w-44 flex-1 items-center gap-3">
                                <label for="stack-gap" class="shrink-0 text-xs font-medium text-secondary">Separate floors</label>
                                <input id="stack-gap" type="range" min="18" max="90" step="1"
                                    x-model.number="gap" class="h-11 w-full accent-[var(--accent)]">
                            </div>

                            <div class="flex min-w-40 flex-1 items-center gap-3">
                                <label for="stack-tilt" class="shrink-0 text-xs font-medium text-secondary">Tilt</label>
                                <input id="stack-tilt" type="range" min="20" max="80" step="1"
                                    x-model.number="tilt" class="h-11 w-full accent-[var(--accent)]">
                            </div>

                            <x-ui.button size="sm" variant="ghost" x-on:click="reset()">Reset the view</x-ui.button>
                        </div>
                    </div>

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
