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
                <x-ui.input wire:model.live.blur="blockNames" name="blockNames" label="Blocks, wings or towers"
                    placeholder="A, B, C" hint="Comma separated. Leave blank if there are no blocks." maxlength="500" />

                @if (count($typedBlocks) > 1)
                    {{-- A society with eleven wings rarely has the same flats
                         in all eleven. Repeating one pattern into every
                         building creates homes that do not exist and misses
                         the ones that do. --}}
                    <label class="flex min-h-11 items-start gap-3" for="same-units">
                        <input type="checkbox" id="same-units" wire:model.live="sameUnitsEveryBlock"
                            class="mt-0.5 size-5 rounded border-strong accent-[var(--accent)]">
                        <span>
                            <span class="block text-sm font-medium">Every building has the same unit numbers</span>
                            <span class="block text-xs text-secondary">
                                Untick this if some buildings have more floors or more flats than others.
                            </span>
                        </span>
                    </label>
                @endif

                @if ($sameUnitsEveryBlock || count($typedBlocks) < 2)
                    <x-ui.textarea wire:model.live.blur="unitPattern" name="unitPattern" label="Unit numbers" rows="3"
                        placeholder="101-104, 201-204, 301-304"
                        :hint="count($typedBlocks) > 1
                            ? 'Ranges and individual numbers, comma separated. These are created in every building you named.'
                            : 'Ranges and individual numbers, comma separated.'"
                        maxlength="2000" />

                    @if ($this->unitsIn($unitPattern) > 0)
                        <p class="-mt-2 text-xs text-secondary">
                            {{ $this->unitsIn($unitPattern) }}
                            {{ \Illuminate\Support\Str::plural('home', $this->unitsIn($unitPattern)) }}
                            @if (count($typedBlocks) > 1)
                                in each of {{ count($typedBlocks) }} buildings, so
                                {{ $this->unitsIn($unitPattern) * count($typedBlocks) }} in all.
                            @endif
                        </p>
                    @endif
                @else
                    <div class="space-y-3">
                        <p class="text-sm font-medium">Unit numbers in each building</p>

                        @foreach ($typedBlocks as $name)
                            @php $count = $this->unitsIn((string) ($blockUnitPatterns[$name] ?? '')); @endphp
                            <div class="rounded-xl border border-subtle p-4" wire:key="block-units-{{ \Illuminate\Support\Str::slug($name) }}">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <label class="text-sm font-semibold" for="units-{{ \Illuminate\Support\Str::slug($name) }}">
                                        {{ $name }}
                                    </label>
                                    <span class="text-xs {{ $count > 0 ? 'text-secondary' : 'text-muted' }}">
                                        {{ $count > 0 ? $count.' '.\Illuminate\Support\Str::plural('home', $count) : 'No homes yet' }}
                                    </span>
                                </div>
                                <div class="mt-2">
                                    <x-ui.textarea wire:model.live.blur="blockUnitPatterns.{{ $name }}"
                                        id="units-{{ \Illuminate\Support\Str::slug($name) }}" rows="2"
                                        :aria-label="'Unit numbers in '.$name"
                                        placeholder="101-104, 201-204" maxlength="2000" />
                                </div>
                            </div>
                        @endforeach

                        <p class="text-xs text-secondary">
                            Total:
                            {{ collect($typedBlocks)->sum(fn ($n) => $this->unitsIn((string) ($blockUnitPatterns[$n] ?? ''))) }}
                            homes across {{ count($typedBlocks) }} buildings.
                        </p>
                    </div>
                @endif

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
            <div class="mt-6">
                <x-billing.rate-question
                    :basis="$rateBasis"
                    :blocks="$blocks"
                    :sizes="$sizes"
                    :area-unit="$society->areaUnitLabel()"
                    area-field="areaRate"
                    :per-label="'per '.($cycle === 'monthly' ? 'month' : str_replace('_', ' ', $cycle))" />
            </div>

            {{--
                Paying a year at once for less than twelve months of it.

                Nearly every society offers this and none of the systems we
                looked at record it, so the discount lives in the treasurer's
                head and gets applied by hand.
            --}}
            @if ($rateBasis !== 'by_area')
                <div class="mt-6">
                    <x-billing.advance-question
                        :offers="$offersAdvance"
                        :periods="$this->periodsPerYear()"
                        :society-year="$this->typicalPeriodAmount() * $this->periodsPerYear()"
                        :amount="$advanceAmount"
                        :blocks="$blocks"
                        :block-years="$blockYears"
                        :block-amounts="$blockAdvanceAmounts" />
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

            {{-- One </div> too many used to close here, which ended the card's
                 own padded body early and left the Back and Continue buttons
                 flush against the card's edge. --}}
            <div class="mt-6 grid gap-4 border-t border-subtle pt-5 sm:grid-cols-2">
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
