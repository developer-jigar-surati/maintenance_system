<div class="mx-auto max-w-3xl">
    <x-ui.page-header title="Set up your society"
        :description="'A few details and '.$society->name.' is ready to bill.'" />

    {{-- Progress. The list is the real structure; the bar is decorative. --}}
    <ol class="mb-8 flex items-center gap-2" aria-label="Setup progress">
        @foreach (['Identity', 'Structure', 'Charges', 'Collection'] as $index => $label)
            @php $number = $index + 1; @endphp
            <li class="flex flex-1 items-center gap-2">
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold
                        {{ $step > $number ? 'bg-[var(--color-positive)] text-white' : ($step === $number ? 'accent-bg' : 'surface-inset text-muted') }}"
                    @if ($step === $number) aria-current="step" @endif
                >
                    @if ($step > $number)
                        <x-ui.icon name="check" class="size-4" />
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span class="hidden text-sm font-medium sm:block {{ $step >= $number ? '' : 'text-muted' }}">
                    {{ $label }}
                </span>
                @unless ($loop->last)
                    <span class="h-px flex-1 {{ $step > $number ? 'bg-[var(--color-positive)]' : 'bg-[var(--border-subtle)]' }}"></span>
                @endunless
            </li>
        @endforeach
    </ol>

    <x-ui.card>
        @if ($step === 1)
            <h2 class="text-lg font-semibold">What kind of community is this?</h2>
            <p class="mt-1 text-sm text-secondary">
                This decides the default unit type and how areas are measured.
            </p>

            <div class="mt-6 space-y-4">
                <x-ui.select wire:model="type" name="type" label="Community type" required>
                    @foreach (['apartment' => 'Apartment complex', 'villa' => 'Villa project', 'row_house' => 'Row houses', 'bungalow' => 'Bungalows', 'gated_community' => 'Gated community', 'township' => 'Township', 'plotted_development' => 'Plotted development', 'builder_floor' => 'Builder floors', 'commercial_complex' => 'Commercial complex', 'office_park' => 'Office park', 'industrial_estate' => 'Industrial estate', 'mixed_use' => 'Mixed use', 'cooperative_housing' => 'Co-operative housing society', 'student_housing' => 'Student housing', 'co_living' => 'Co-living', 'other' => 'Other'] as $v => $l)
                        <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                </x-ui.select>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model="areaUnit" name="areaUnit" label="Area is measured in" required>
                        <option value="sqft">Square feet</option>
                        <option value="sqm">Square metres</option>
                        <option value="sqyd">Square yards</option>
                    </x-ui.select>

                    <x-ui.select wire:model="financialYearStart" name="financialYearStart" label="Financial year starts" required min="1" max="12">
                        @foreach (range(1, 12) as $month)
                            <option value="{{ $month }}">{{ \Illuminate\Support\Carbon::create(null, $month)->format('F') }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <x-ui.input wire:model="city" name="city" label="City" maxlength="80" />
            </div>

        @elseif ($step === 2)
            <h2 class="text-lg font-semibold">How is it laid out?</h2>
            <p class="mt-1 text-sm text-secondary">
                Describe the blocks and unit numbers in plain text. You can adjust
                individual units later.
            </p>

            <div class="mt-6 space-y-4">
                <x-ui.input wire:model="blockNames" name="blockNames" label="Blocks, wings or towers"
                    placeholder="A, B, C" hint="Comma separated. Leave blank if there are no blocks." maxlength="500" />

                <x-ui.textarea wire:model="unitPattern" name="unitPattern" label="Unit numbers" rows="3"
                    placeholder="101-104, 201-204, 301-304"
                    hint="Ranges and individual numbers, comma separated. These are created in every block you named." maxlength="2000" />

                @if ($unitCount > 0)
                    <x-ui.alert tone="positive">
                        {{ $blockCount }} {{ \Illuminate\Support\Str::plural('block', $blockCount) }}
                        and {{ $unitCount }} {{ \Illuminate\Support\Str::plural('unit', $unitCount) }} exist so far.
                        Running this again only adds what is missing.
                    </x-ui.alert>
                @endif
            </div>

        @elseif ($step === 3)
            <h2 class="text-lg font-semibold">How much is maintenance?</h2>
            <p class="mt-1 text-sm text-secondary">
                Most societies take one amount from every home. If yours varies, say how.
            </p>

            {{--
                The question a committee can actually answer.

                The old step listed every seeded charge head with its basis
                already chosen for them, and asked for a rate against each:
                "Maintenance Charges, rate per sq.ft." Nobody decides it that
                way. They decide an amount, and then whether it differs by
                building or by size of home.
            --}}
            <fieldset class="mt-6">
                <legend class="sr-only">How maintenance is worked out</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        'flat' => ['The same for every home', 'One amount, whatever the flat. This is what most societies do.'],
                        'by_block' => ['Different for each building', 'A wing with a lift pays more than one without.'],
                        'by_size' => ['Different by size of home', 'A 3BHK pays more than a 2BHK.'],
                        'by_area' => ['By area', 'A rate for every '.$society->areaUnitLabel().' of the flat.'],
                    ] as $key => [$title, $why])
                        <button
                            type="button"
                            wire:click="$set('rateBasis', '{{ $key }}')"
                            aria-pressed="{{ $rateBasis === $key ? 'true' : 'false' }}"
                            @class([
                                'rounded-xl border p-4 text-left transition-colors',
                                'border-[var(--accent)] accent-soft-bg' => $rateBasis === $key,
                                'border-subtle surface-raised hover:border-strong' => $rateBasis !== $key,
                            ])
                        >
                            <span class="flex items-center gap-2">
                                <span @class([
                                    'flex size-4 shrink-0 items-center justify-center rounded-full border-2',
                                    'border-[var(--accent)]' => $rateBasis === $key,
                                    'border-strong' => $rateBasis !== $key,
                                ])>
                                    @if ($rateBasis === $key)
                                        <span class="size-2 rounded-full accent-bg"></span>
                                    @endif
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
                @if ($rateBasis === 'flat')
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-subtle p-4">
                        <div>
                            <p class="text-sm font-medium">Every home pays</p>
                            <p class="text-xs text-muted">per {{ str_replace('_', ' ', $cycle === 'monthly' ? 'month' : $cycle) }}</p>
                        </div>
                        <div class="w-40">
                            <x-ui.input wire:model.live="flatAmount" name="flatAmount" type="number" step="1" min="0"
                                aria-label="Amount every home pays" placeholder="12000" class="numeric text-right" />
                        </div>
                    </div>
                @endif

                @if ($rateBasis === 'by_block')
                    @forelse ($blocks as $block)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-subtle p-4">
                            <p class="text-sm font-medium">{{ $block->name }}</p>
                            <div class="w-40">
                                <x-ui.input wire:model.live="blockAmounts.{{ $block->id }}" type="number" step="1" min="0"
                                    :aria-label="'Amount for '.$block->name" placeholder="12000" class="numeric text-right" />
                            </div>
                        </div>
                    @empty
                        <x-ui.alert tone="caution" title="No buildings yet">
                            Go back a step and add your buildings, then this asks for an amount for each.
                        </x-ui.alert>
                    @endforelse
                @endif

                @if ($rateBasis === 'by_size')
                    @forelse ($sizes as $size)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-subtle p-4">
                            <p class="text-sm font-medium">{{ $size }}</p>
                            <div class="w-40">
                                <x-ui.input wire:model.live="sizeAmounts.{{ $size }}" type="number" step="1" min="0"
                                    :aria-label="'Amount for a '.$size" placeholder="12000" class="numeric text-right" />
                            </div>
                        </div>
                    @empty
                        <x-ui.alert tone="caution" title="No sizes recorded yet">
                            Your homes do not have a configuration such as 2BHK against them yet. Pick another
                            way for now; you can set rates by size later under Charge heads.
                        </x-ui.alert>
                    @endforelse
                @endif

                @if ($rateBasis === 'by_area')
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-subtle p-4">
                        <div>
                            <p class="text-sm font-medium">Rate per {{ $society->areaUnitLabel() }}</p>
                            <p class="text-xs text-muted">Multiplied by each home's area.</p>
                        </div>
                        <div class="w-40">
                            <x-ui.input wire:model.live="areaRate" name="areaRate" type="number" step="0.01" min="0"
                                aria-label="Rate per unit of area" placeholder="3.50" class="numeric text-right" />
                        </div>
                    </div>
                @endif
            </div>

            {{--
                Paying a year at once for less than twelve months of it.

                Nearly every society offers this and none of the systems we
                looked at record it, so the discount lives in the treasurer's
                head and gets applied by hand.
            --}}
            @if ($rateBasis !== 'by_area')
                @php
                    $periods = $this->periodsPerYear();
                    $yearFull = $this->typicalPeriodAmount() * $periods;
                @endphp

                <div class="mt-6 rounded-xl border border-subtle p-4">
                    <label class="flex min-h-11 items-center gap-3" for="offers-advance">
                        <input type="checkbox" id="offers-advance" wire:model.live="offersAdvance"
                            class="size-5 rounded border-strong accent-[var(--accent)]">
                        <span>
                            <span class="block text-sm font-medium">Cheaper if they pay for the year at once</span>
                            <span class="block text-xs text-secondary">
                                @if ($yearFull > 0)
                                    A year at the rate above comes to <x-ui.money :amount="$yearFull" />.
                                @else
                                    Set the amount above and this works out the year's total.
                                @endif
                            </span>
                        </span>
                    </label>

                    @if ($offersAdvance)
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-subtle pt-4">
                            <div>
                                <p class="text-sm font-medium">A year paid up front costs</p>
                                @if ($yearFull > 0 && (float) $advanceAmount > 0)
                                    <p class="text-xs text-[var(--color-positive)]">
                                        They save <x-ui.money :amount="max(0, $yearFull - (float) $advanceAmount)" />,
                                        which is {{ round((1 - ((float) $advanceAmount / $yearFull)) * 100) }} percent.
                                    </p>
                                @else
                                    <p class="text-xs text-muted">Instead of {{ $periods }} separate bills.</p>
                                @endif
                            </div>
                            <div class="w-40">
                                <x-ui.input wire:model.live="advanceAmount" name="advanceAmount" type="number" step="1" min="0"
                                    aria-label="Cost of a year paid up front"
                                    :placeholder="$yearFull > 0 ? (string) round($yearFull * 0.85) : '120000'"
                                    class="numeric text-right" />
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Water, sinking fund, parking. Folded away, because most
                 societies take one amount and nothing else. --}}
            <div class="mt-6">
                <button type="button" wire:click="$toggle('showExtras')"
                    aria-expanded="{{ $showExtras ? 'true' : 'false' }}"
                    class="flex min-h-11 items-center gap-2 text-sm font-medium text-secondary hover:text-primary">
                    <x-ui.icon name="chevron-right" class="size-4 transition-transform {{ $showExtras ? 'rotate-90' : '' }}" />
                    Do you charge anything else?
                </button>

                @if ($showExtras)
                    <p class="mb-3 mt-2 text-xs text-secondary">
                        Water, sinking fund, parking and the rest. Leave any at zero and it stays off the bill.
                    </p>

                    <div class="space-y-3">
                        @foreach ($extraHeads as $head)
                            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-subtle p-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">{{ $head->name }}</p>
                                    <p class="text-xs text-muted">
                                        {{ match ($head->basis) {
                                            'per_sqft' => 'Rate per '.$society->areaUnitLabel(),
                                            'per_bedroom' => 'Rate per bedroom',
                                            'per_member' => 'Rate per resident',
                                            'per_vehicle' => 'Rate per vehicle',
                                            'manual' => 'Entered per home when needed',
                                            default => 'Same amount for every home',
                                        } }}
                                    </p>
                                </div>
                                @if ($head->basis !== 'manual')
                                    <div class="w-32">
                                        <x-ui.input wire:model="rates.{{ $head->id }}" type="number" step="0.01" min="0"
                                            :aria-label="'Rate for '.$head->name" class="numeric text-right" />
                                    </div>
                                @else
                                    <span class="text-xs text-muted">Not billed automatically</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

                <div class="grid gap-4 border-t border-subtle pt-4 sm:grid-cols-2">
                    <x-ui.select wire:model="cycle" name="cycle" label="Bill every" required>
                        <option value="monthly">Month</option>
                        <option value="bi_monthly">Two months</option>
                        <option value="quarterly">Quarter</option>
                        <option value="half_yearly">Six months</option>
                        <option value="yearly">Year</option>
                    </x-ui.select>

                    <x-ui.input wire:model="dueAfterDays" name="dueAfterDays" label="Due within (days)"
                        type="number" min="1" max="120" required />
                </div>
            </div>

        @else
            <h2 class="text-lg font-semibold">How will residents pay?</h2>
            <p class="mt-1 text-sm text-secondary">
                You can change this at any time, and add a payment gateway later.
            </p>

            <div class="mt-6 space-y-3">
                @foreach ([
                    'offline' => ['Offline only', 'Residents pay the treasurer by cash, cheque or a direct transfer. A committee member records each payment and the system issues the receipt.'],
                    'both' => ['Online and offline', 'Residents can pay through a gateway, and the committee can still record cash and cheques. Needs a gateway configured in settings.'],
                    'online' => ['Online only', 'Every payment goes through the gateway. Cash handed to the treasurer cannot be recorded.'],
                ] as $value => [$label, $description])
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors
                        {{ $paymentMode === $value ? 'accent-soft-bg border-[var(--accent)]' : 'border-subtle hover:surface-inset' }}">
                        <input type="radio" wire:model.live="paymentMode" value="{{ $value }}" name="payment-mode"
                            class="mt-0.5 size-4 accent-[var(--accent)]">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold">{{ $label }}</span>
                            <span class="mt-0.5 block text-xs text-secondary">{{ $description }}</span>
                        </span>
                    </label>
                @endforeach

                @if ($paymentMode !== 'online')
                    <label class="mt-4 flex items-start gap-3 border-t border-subtle pt-4 text-sm">
                        <input type="checkbox" wire:model="requireApproval"
                            class="mt-0.5 size-4 rounded border-strong accent-[var(--accent)]">
                        <span>
                            <span class="font-medium">Offline payments need a second approval</span>
                            <span class="block text-xs text-secondary">
                                Recommended: one person records the payment, another confirms it before the receipt is issued.
                            </span>
                        </span>
                    </label>
                @endif

                @if ($paymentMode !== 'offline')
                    <x-ui.alert tone="info" class="mt-4">
                        After finishing, add your gateway keys under
                        <strong>Society settings &rarr; Collecting payments</strong>. Online
                        payment stays off until the keys are verified.
                    </x-ui.alert>
                @endif
            </div>
        @endif

        <div class="mt-8 flex items-center justify-between gap-3 border-t border-subtle pt-5">
            @if ($step > 1)
                <x-ui.button variant="secondary" wire:click="back" icon="chevron-left">Back</x-ui.button>
            @else
                <span></span>
            @endif

            @if ($step < 4)
                <x-ui.button wire:click="next" icon-trailing="chevron-right">Continue</x-ui.button>
            @else
                <x-ui.button wire:click="finish" icon="check">
                    <span wire:loading.remove wire:target="finish">Finish setup</span>
                    <span wire:loading wire:target="finish">Finishing&hellip;</span>
                </x-ui.button>
            @endif
        </div>
    </x-ui.card>
</div>
