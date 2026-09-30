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
            <h2 class="text-lg font-semibold">What do you charge?</h2>
            <p class="mt-1 text-sm text-secondary">
                Set a rate for each head you use. Leave the rest at zero - only
                heads with a rate go onto the bill.
            </p>

            <div class="mt-6 space-y-3">
                @foreach ($heads as $head)
                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-subtle p-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">{{ $head->name }}</p>
                            <p class="text-xs text-muted">
                                {{ match ($head->basis) {
                                    'per_sqft' => 'Rate per '.$society->areaUnitLabel(),
                                    'per_bedroom' => 'Rate per bedroom',
                                    'per_member' => 'Rate per resident',
                                    'per_vehicle' => 'Rate per vehicle',
                                    'manual' => 'Entered per unit when needed',
                                    default => 'Same amount for every unit',
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
