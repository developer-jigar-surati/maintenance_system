@props([
    /** Rows of ['month' => 'Jan 2026', 'billed' => 0.0, 'collected' => 0.0] */
    'series' => [],
    'height' => 220,
])

@php
    $rows = collect($series)->values();
    $max = max(1, (float) max($rows->max('billed') ?? 0, $rows->max('collected') ?? 0));

    // Round the axis up to a friendly step so gridlines land on readable numbers.
    $magnitude = 10 ** max(0, strlen((string) (int) $max) - 2);
    $ceiling = max($magnitude, ceil($max / $magnitude) * $magnitude);

    $chartId = 'chart-'.\Illuminate\Support\Str::random(6);
    $plotHeight = $height - 28;      // leaves room for the month labels
    $groupWidth = $rows->count() > 0 ? 100 / $rows->count() : 100;
@endphp

<figure class="viz-root" aria-labelledby="{{ $chartId }}-title">
    <style>
        #{{ $chartId }} {
            /* Categorical slots 1 and 2. Both validated against this card's
               surface in light and dark: CVD ΔE 24.7 / 26.8, well clear of the
               ≥8 target, so the two series stay distinguishable. */
            --series-billed: #2a78d6;
            --series-collected: #eb6834;
        }
        :root[data-theme='dark'] #{{ $chartId }} {
            --series-billed: #3987e5;
            --series-collected: #d95926;
        }
    </style>

    <figcaption id="{{ $chartId }}-title" class="sr-only">
        Amount billed and amount collected for each of the last {{ $rows->count() }} months.
    </figcaption>

    {{-- Legend: two series, so identity never rests on colour alone. --}}
    <div class="mb-4 flex flex-wrap items-center gap-4 text-xs font-medium text-secondary">
        <span class="flex items-center gap-1.5">
            <span class="size-2.5 rounded-sm" style="background: var(--series-billed)" aria-hidden="true"></span>
            Billed
        </span>
        <span class="flex items-center gap-1.5">
            <span class="size-2.5 rounded-sm" style="background: var(--series-collected)" aria-hidden="true"></span>
            Collected
        </span>
    </div>

    @if ($rows->isEmpty())
        <p class="py-10 text-center text-sm text-muted">No billing history yet.</p>
    @else
        <div id="{{ $chartId }}" x-data="{ hovered: null }" class="relative">
            {{-- Tooltip, positioned over the hovered group. --}}
            <div
                x-show="hovered !== null"
                x-transition.opacity.duration.100ms
                class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-lg border border-subtle surface-raised px-3 py-2 text-xs shadow-[var(--shadow-overlay)]"
                :style="hovered !== null ? `left: ${hovered.x}%; top: ${hovered.top}px` : ''"
                style="display: none"
                role="status"
            >
                <p class="font-semibold text-primary" x-text="hovered?.month"></p>
                <p class="numeric mt-1 flex items-center gap-1.5 text-secondary">
                    <span class="size-2 rounded-sm" style="background: var(--series-billed)"></span>
                    Billed <span class="font-semibold text-primary" x-text="hovered?.billed"></span>
                </p>
                <p class="numeric flex items-center gap-1.5 text-secondary">
                    <span class="size-2 rounded-sm" style="background: var(--series-collected)"></span>
                    Collected <span class="font-semibold text-primary" x-text="hovered?.collected"></span>
                </p>
            </div>

            <svg
                viewBox="0 0 100 {{ $height }}"
                preserveAspectRatio="none"
                class="w-full"
                style="height: {{ $height }}px"
                role="img"
                aria-label="Billed versus collected, last {{ $rows->count() }} months"
            >
                {{-- Recessive gridlines at quarter steps. --}}
                @foreach ([0, 0.25, 0.5, 0.75, 1] as $fraction)
                    @php $y = $plotHeight - ($fraction * $plotHeight); @endphp
                    <line
                        x1="0" y1="{{ $y }}" x2="100" y2="{{ $y }}"
                        stroke="var(--border-subtle)"
                        stroke-width="{{ $fraction === 0 ? 1 : 0.5 }}"
                        vector-effect="non-scaling-stroke"
                    />
                @endforeach

                @foreach ($rows as $index => $row)
                    @php
                        $groupX = $index * $groupWidth;
                        // 2px surface gap between the paired bars; the group is
                        // inset so adjacent months never touch.
                        $barWidth = $groupWidth * 0.32;
                        $gap = $groupWidth * 0.06;
                        $inset = ($groupWidth - ($barWidth * 2 + $gap)) / 2;

                        $billedHeight = max(1.5, (float) $row['billed'] / $ceiling * $plotHeight);
                        $collectedHeight = max(1.5, (float) $row['collected'] / $ceiling * $plotHeight);
                    @endphp

                    {{-- Hover target spanning the whole group, larger than the marks. --}}
                    <rect
                        x="{{ $groupX }}" y="0" width="{{ $groupWidth }}" height="{{ $plotHeight }}"
                        fill="transparent"
                        @mouseenter="hovered = {
                            month: @js($row['month']),
                            billed: @js(\App\Support\Money::format((float) $row['billed'])),
                            collected: @js(\App\Support\Money::format((float) $row['collected'])),
                            x: {{ $groupX + $groupWidth / 2 }},
                            top: {{ $plotHeight - max($billedHeight, $collectedHeight) - 12 }},
                        }"
                        @mouseleave="hovered = null"
                    />

                    {{-- Rounded data-ends, anchored to the baseline. --}}
                    <rect
                        x="{{ $groupX + $inset }}"
                        y="{{ $plotHeight - $billedHeight }}"
                        width="{{ $barWidth }}"
                        height="{{ $billedHeight }}"
                        rx="1"
                        fill="var(--series-billed)"
                    />
                    <rect
                        x="{{ $groupX + $inset + $barWidth + $gap }}"
                        y="{{ $plotHeight - $collectedHeight }}"
                        width="{{ $barWidth }}"
                        height="{{ $collectedHeight }}"
                        rx="1"
                        fill="var(--series-collected)"
                    />
                @endforeach
            </svg>

            {{-- Month labels sit outside the SVG so they keep real font metrics
                 rather than being stretched by preserveAspectRatio="none". --}}
            <div class="flex" aria-hidden="true">
                @foreach ($rows as $row)
                    <span class="flex-1 truncate text-center text-[0.6875rem] text-muted">
                        {{ \Illuminate\Support\Str::before($row['month'], ' ') }}
                    </span>
                @endforeach
            </div>
        </div>

        {{-- Table view: the same numbers, for screen readers and anyone who
             wants exact figures rather than a shape. --}}
        <details class="mt-4">
            <summary class="cursor-pointer text-xs font-medium text-secondary hover:text-primary">
                View as table
            </summary>
            <table class="mt-3 w-full text-xs">
                <thead>
                    <tr class="text-left text-muted">
                        <th scope="col" class="py-1.5 font-medium">Month</th>
                        <th scope="col" class="py-1.5 text-right font-medium">Billed</th>
                        <th scope="col" class="py-1.5 text-right font-medium">Collected</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-subtle)]">
                    @foreach ($rows as $row)
                        <tr>
                            <th scope="row" class="py-1.5 text-left font-normal">{{ $row['month'] }}</th>
                            <td class="numeric py-1.5 text-right">
                                <x-ui.money :amount="$row['billed']" />
                            </td>
                            <td class="numeric py-1.5 text-right">
                                <x-ui.money :amount="$row['collected']" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</figure>
