@props([
    'offers' => false,
    'periods' => 12,
    'societyYear' => 0.0,
    'amount' => null,
    'blocks' => null,
    'blockYears' => [],
    'blockAmounts' => [],
    /* Property names on the calling component, so the setup wizard and the
       billing plan screen ask this the same way without either being special. */
    'offersField' => 'offersAdvance',
    'amountField' => 'advanceAmount',
    'blockField' => 'blockAdvanceAmounts',
])

{{--
    "Pay the year together and pay less", asked once.

    Nearly every society offers this and none of them could record it, so the
    figure lived in the treasurer's head and residents heard whichever version
    the person they asked remembered.

    Asked in money because that is how a meeting decides it, and stored as a
    percentage because a percentage is the only form that still means the same
    thing once A wing pays 12,000 and the GHI block pays 8,000.
--}}
<div class="rounded-xl border border-subtle p-4">
    <label class="flex min-h-11 items-start gap-3" for="{{ $offersField }}">
        <input type="checkbox" id="{{ $offersField }}" wire:model.live="{{ $offersField }}"
            class="mt-0.5 size-5 rounded border-strong accent-[var(--accent)]">
        <span>
            <span class="block text-sm font-medium">Cheaper if they pay for the year at once</span>
            <span class="block text-xs text-secondary">
                @if ($societyYear > 0)
                    A year at the amounts above comes to <x-ui.money :amount="$societyYear" /> for a typical home.
                @else
                    Set the amounts above and this works out what a year comes to.
                @endif
            </span>
        </span>
    </label>

    @if ($offers)
        <div class="mt-4 border-t border-subtle pt-4" wire:key="advance-amounts">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-medium">A year paid up front costs</p>
                    <p class="text-xs text-muted">
                        Instead of {{ $periods }} separate {{ \Illuminate\Support\Str::plural('bill', $periods) }}.
                    </p>
                </div>
                <div class="w-44">
                    <x-ui.input wire:model.live.debounce.700ms="{{ $amountField }}" name="{{ $amountField }}"
                        type="number" step="1" min="0" required
                        aria-label="What a year costs when paid up front"
                        :placeholder="$societyYear > 0 ? (string) round($societyYear * 0.85) : '120000'"
                        class="numeric text-right" />
                </div>
            </div>

            @if ($societyYear > 0 && (float) $amount > 0)
                @if ((float) $amount >= $societyYear)
                    {{-- Saying "they save 0, which is -0.81 percent" is worse
                         than saying nothing. A figure above the year's total
                         is a typo, so name it. --}}
                    <p class="mt-2 text-xs text-[var(--color-caution)]">
                        That is not less than the <x-ui.money :amount="$societyYear" /> a year already costs,
                        so nobody saves anything. Enter a smaller amount, or leave the offer switched off.
                    </p>
                @else
                    <p class="mt-2 text-xs text-[var(--color-positive)]">
                        They save <x-ui.money :amount="$societyYear - (float) $amount" />, which is
                        {{ round((1 - ((float) $amount / $societyYear)) * 100, 2) }} percent. Every home gets
                        that same percentage off whatever it pays, so a 2BHK and a 3BHK both keep their own amount.
                    </p>
                @endif
            @endif
        </div>

        @if ($blocks !== null && $blocks->isNotEmpty())
            <div class="mt-4 border-t border-subtle pt-4" wire:key="advance-blocks">
                <p class="text-sm font-medium">Buildings promised something different</p>
                <p class="mt-0.5 text-xs text-secondary">
                    Leave a building empty and it gets the offer above. Fill one in only where a meeting
                    agreed a different deal for that wing, and put 0 where a wing was told it gets nothing.
                </p>

                <div class="mt-3 space-y-3">
                    @foreach ($blocks as $block)
                        @php $blockYear = (float) ($blockYears[$block->id] ?? $societyYear); @endphp
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-subtle p-4"
                            wire:key="advance-block-{{ $block->id }}">
                            <div class="min-w-0">
                                <p class="text-sm font-medium">{{ $block->name }}</p>
                                <p class="text-xs text-muted">
                                    @if ($blockYear > 0)
                                        Normally about <x-ui.money :amount="$blockYear" /> a year.
                                    @else
                                        No amount set for this building yet.
                                    @endif
                                </p>
                            </div>
                            <div class="w-44">
                                <x-ui.input wire:model.live.debounce.700ms="{{ $blockField }}.{{ $block->id }}"
                                    name="{{ $blockField }}.{{ $block->id }}"
                                    type="number" step="1" min="0"
                                    :aria-label="'What a year costs in '.$block->name.' when paid up front'"
                                    placeholder="Same as above" class="numeric text-right" />
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
