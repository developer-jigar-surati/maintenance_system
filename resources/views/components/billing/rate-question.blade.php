@props([
    'basis' => 'flat',
    'blocks' => null,
    'sizes' => null,
    'areaUnit' => 'sq.ft.',
    /* Property names on the calling component, so this works for both the
       setup wizard and the charge heads screen without either being special. */
    'basisField' => 'rateBasis',
    'flatField' => 'flatAmount',
    'blockField' => 'blockAmounts',
    'sizeField' => 'sizeAmounts',
    'gridField' => 'gridAmounts',
    'areaField' => null,
    'perLabel' => null,
])

{{--
    "How much is this?" asked once, used everywhere.

    The setup wizard and the charge heads screen ask the same question, so
    they ask it with the same markup. Two copies would drift, and the drift
    would only show up as a wrong bill.
--}}
<fieldset>
    <legend class="sr-only">How this charge is worked out</legend>
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ([
            'flat' => ['The same for every home', 'One amount, whatever the flat. This is what most societies do.'],
            'by_block' => ['Different for each building', 'A wing with a lift pays more than one without.'],
            'by_size' => ['Different by size of home', 'A 3BHK pays more than a 2BHK.'],
            'by_block_and_size' => ['Different by building and size', 'A wing 3BHK is one amount, B wing 3BHK another.'],
            'by_area' => ['By area', 'A rate for every '.$areaUnit.' of the flat.'],
        ] as $key => [$title, $why])
            <button
                type="button"
                wire:key="basis-{{ $key }}"
                wire:click="$set('{{ $basisField }}', '{{ $key }}')"
                aria-pressed="{{ $basis === $key ? 'true' : 'false' }}"
                @class([
                    'rounded-xl border p-4 text-left transition-colors',
                    'border-[var(--accent)] accent-soft-bg' => $basis === $key,
                    'border-subtle surface-raised hover:border-strong' => $basis !== $key,
                ])
            >
                <span class="flex items-center gap-2">
                    <span @class([
                        'flex size-4 shrink-0 items-center justify-center rounded-full border-2',
                        'border-[var(--accent)]' => $basis === $key,
                        'border-strong' => $basis !== $key,
                    ])>
                        @if ($basis === $key)<span class="size-2 rounded-full accent-bg"></span>@endif
                    </span>
                    <span class="text-sm font-semibold">{{ $title }}</span>
                </span>
                <span class="mt-1.5 block pl-6 text-xs text-secondary">{{ $why }}</span>
            </button>
        @endforeach
    </div>
</fieldset>

{{-- Then only the numbers that answer it. --}}
<div class="mt-6 space-y-3">
    @if ($basis === 'flat')
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-subtle p-4">
            <div>
                <p class="text-sm font-medium">Every home pays</p>
                @if ($perLabel)<p class="text-xs text-muted">{{ $perLabel }}</p>@endif
            </div>
            <div class="w-40">
                <x-ui.input wire:model.live.debounce.700ms="{{ $flatField }}" name="{{ $flatField }}" type="number" step="1" min="0"
                    aria-label="Amount every home pays" placeholder="12000" class="numeric text-right" />
            </div>
        </div>
    @endif

    @if ($basis === 'by_block')
        @forelse ($blocks ?? [] as $block)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-subtle p-4"
                wire:key="rate-block-{{ $block->id }}">
                <p class="text-sm font-medium">{{ $block->name }}</p>
                <div class="w-40">
                    <x-ui.input wire:model.live.debounce.700ms="{{ $blockField }}.{{ $block->id }}" name="{{ $blockField }}.{{ $block->id }}" type="number" step="1" min="0"
                        :aria-label="'Amount for '.$block->name" placeholder="12000" class="numeric text-right" />
                </div>
            </div>
        @empty
            <x-ui.alert tone="caution" title="No buildings yet">
                Add your buildings first, then this asks for an amount for each.
            </x-ui.alert>
        @endforelse
    @endif

    @if ($basis === 'by_size')
        @forelse ($sizes ?? [] as $size)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-subtle p-4"
                wire:key="rate-size-{{ \Illuminate\Support\Str::slug($size) }}">
                <p class="text-sm font-medium">{{ $size }}</p>
                <div class="w-40">
                    <x-ui.input wire:model.live.debounce.700ms="{{ $sizeField }}.{{ $size }}" name="{{ $sizeField }}.{{ $size }}" type="number" step="1" min="0"
                        :aria-label="'Amount for a '.$size" placeholder="12000" class="numeric text-right" />
                </div>
            </div>
        @empty
            <x-ui.alert tone="caution" title="No sizes recorded yet">
                Your homes do not have a configuration such as 2BHK against them yet. Record those
                under Units, or pick another way for now.
            </x-ui.alert>
        @endforelse
    @endif

    @if ($basis === 'by_block_and_size')
        @if (($blocks?->isEmpty() ?? true) || ($sizes?->isEmpty() ?? true))
            <x-ui.alert tone="caution" title="Not enough recorded yet">
                This needs both your buildings and the size of each home. Add the buildings, or
                pick another way for now and set these later.
            </x-ui.alert>
        @else
            <p class="text-xs text-secondary">
                One amount per building and size. Leave a box empty where that combination does not exist.
            </p>

            {{-- Scrolls sideways rather than squeezing: six buildings and four
                 sizes is a genuinely wide table. --}}
            <div class="scrollbar-slim -mx-1 overflow-x-auto px-1">
                <table class="w-full min-w-md border-separate border-spacing-2">
                    <caption class="sr-only">Amount for each building and size of home</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="text-left text-xs font-semibold text-muted">Building</th>
                            @foreach ($sizes as $size)
                                <th scope="col" class="text-right text-xs font-semibold text-muted">{{ $size }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($blocks as $block)
                            <tr wire:key="rate-grid-row-{{ $block->id }}">
                                <th scope="row" class="whitespace-nowrap pr-2 text-left text-sm font-medium">
                                    {{ $block->name }}
                                </th>
                                @foreach ($sizes as $size)
                                    <td class="w-32" wire:key="rate-grid-{{ $block->id }}-{{ \Illuminate\Support\Str::slug($size) }}">
                                        <x-ui.input
                                            wire:model.live.debounce.700ms="{{ $gridField }}.{{ $block->id }}|{{ $size }}"
                                            name="{{ $gridField }}.{{ $block->id }}|{{ $size }}"
                                            type="number" step="1" min="0"
                                            :aria-label="$block->name.' '.$size"
                                            placeholder="0" class="numeric text-right" />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    @if ($basis === 'by_area')
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-subtle p-4">
            <div>
                <p class="text-sm font-medium">Rate per {{ $areaUnit }}</p>
                <p class="text-xs text-muted">Multiplied by each home's area.</p>
            </div>
            <div class="w-40">
                <x-ui.input wire:model.live.debounce.700ms="{{ $areaField ?? $flatField }}" name="{{ $areaField ?? $flatField }}" type="number" step="0.01" min="0"
                    aria-label="Rate per unit of area" placeholder="3.50" class="numeric text-right" />
            </div>
        </div>
    @endif
</div>
