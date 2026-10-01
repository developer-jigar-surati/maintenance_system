<div>
    <x-ui.card title="What this home pays" padded="false">
        <x-slot:description>
            @if ($plan)
                Per {{ strtolower($plan->cycleLabel()) }} bill, as it would be raised today.
            @else
                No billing plan is active, so nothing is being raised yet.
            @endif
        </x-slot:description>

        @if ($lines->isEmpty())
            <div class="p-5">
                <x-ui.empty-state icon="tag" title="Nothing is billed to this home yet"
                    description="Charge heads on an active billing plan appear here with the amount this home pays." />
            </div>
        @else
            <ul class="divide-y divide-[var(--border-subtle)]">
                @foreach ($lines as $line)
                    @php
                        $head = $line['head'];
                        $byHand = $line['source'] === 'unit' || $line['exempt'];
                    @endphp
                    <li class="px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ $head->name }}</p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-muted">
                                    <span>{{ $line['origin'] }}</span>
                                    @if ($byHand)
                                        <x-ui.badge tone="accent">By hand</x-ui.badge>
                                    @endif
                                    @if ($line['tax_rate'] > 0)
                                        <span>incl. {{ rtrim(rtrim(number_format($line['tax_rate'], 2), '0'), '.') }}% tax</span>
                                    @endif
                                </p>
                            </div>

                            <div class="text-right">
                                @if (! $line['applies'])
                                    <span class="text-sm text-muted">Does not apply</span>
                                @elseif ($line['exempt'])
                                    <span class="text-sm font-medium text-[var(--color-caution)]">Not charged</span>
                                @else
                                    <x-ui.money :amount="$line['gross']" class="text-sm font-semibold" />
                                    @if ($line['quantity'] != 1)
                                        <span class="numeric block text-xs text-muted">
                                            {{ rtrim(rtrim(number_format($line['quantity'], 2), '0'), '.') }}
                                            &times; <x-ui.money :amount="$line['rate']" />
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </div>

                        @if ($byHand && $line['override'])
                            {{-- Why, and who agreed to it. An exception nobody
                                 can account for is the one that gets reversed. --}}
                            <p class="mt-1.5 text-xs text-secondary">
                                @if ($line['override']->reason){{ $line['override']->reason }}@else Set for this home. @endif
                                @if ($line['override']->effective_from || $line['override']->effective_to)
                                    <span class="numeric text-muted">
                                        ({{ $line['override']->effective_from?->format('j M Y') ?? 'from the start' }}
                                        to {{ $line['override']->effective_to?->format('j M Y') ?? 'until changed' }})
                                    </span>
                                @endif
                                @if ($line['override']->setBy)
                                    <span class="text-muted">by {{ $line['override']->setBy->name }}</span>
                                @endif
                            </p>
                        @endif

                        @if ($canManage && $head->basis !== 'manual')
                            <div class="mt-2 flex flex-wrap gap-2">
                                <x-ui.button size="sm" variant="ghost" wire:click="edit({{ $head->id }})">
                                    {{ $byHand ? 'Change the amount for this home' : 'Set a different amount for this home' }}
                                </x-ui.button>

                                @if ($byHand)
                                    <x-ui.button size="sm" variant="ghost"
                                        wire:click="useSharedAmount({{ $head->id }})"
                                        data-confirm="Put this home back on the shared amount?"
                                        data-confirm-detail="The amount set for this home is removed, and it pays whatever its building and size say. Bills already raised are unchanged."
                                        data-confirm-action="Use the shared amount">
                                        Use the shared amount
                                    </x-ui.button>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="flex items-center justify-between gap-3 border-t border-subtle px-5 py-3 surface-inset">
                <p class="text-sm font-semibold">Every {{ $plan?->periodNoun() ?? 'bill' }}</p>
                <x-ui.money :amount="$periodTotal" class="text-sm font-semibold" />
            </div>

            @if ($offer)
                {{--
                    The deal every society offers and none of them wrote down.
                    Shown here as money rather than a percentage, because
                    "you save 24,000" is the sentence that gets people to pay.
                --}}
                <div class="border-t border-subtle px-5 py-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted">Paying the year in one go</p>
                    <p class="mt-1.5 text-sm">
                        <x-ui.money :amount="$offer['payable']" class="font-semibold" />
                        instead of <x-ui.money :amount="$offer['full']" />,
                        so this home saves <x-ui.money :amount="$offer['saving']" class="font-semibold" tone="positive" />.
                    </p>
                    <p class="mt-1 text-xs text-muted">
                        {{ rtrim(rtrim(number_format($offer['percent'], 2), '0'), '.') }}% off
                        {{ $offer['periods'] }} {{ \Illuminate\Support\Str::plural('bill', $offer['periods']) }}
                        paid together.
                        @if ($canManage)
                            <a href="{{ route('billing-plans.index') }}" wire:navigate class="accent-text hover:underline">Change this offer</a>
                        @endif
                    </p>
                </div>
            @endif
        @endif
    </x-ui.card>

    @if ($canManage)
        <x-ui.modal name="home-amount" :title="$editing ? $editing->name.' for this home' : 'Amount for this home'" max-width="lg">
            @if ($editing)
                <form data-validate wire:submit="save" class="space-y-4">
                    <x-ui.alert tone="info">
                        This amount applies to <strong>{{ $unit->label }}</strong> alone. Every other
                        home keeps whatever its building and size say, so changing those later will
                        not quietly undo this.
                    </x-ui.alert>

                    @php
                        /* A head billed per square foot is asking for a rate,
                           not a bill. Labelling both "what this home pays" is
                           how somebody types 9,000 into a per-sqft box and
                           bills a flat seventy-eight lakh. */
                        $basis = $editingLine['basis'] ?? 'fixed_per_unit';
                        $quantity = (float) ($editingLine['quantity'] ?? 1);
                        $per = match ($basis) {
                            'per_sqft' => $areaUnit,
                            'per_bedroom' => 'bedroom',
                            'per_member' => 'member',
                            'per_vehicle' => 'vehicle',
                            default => null,
                        };
                        $trim = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
                    @endphp

                    <x-ui.input wire:model.live="amount" name="amount" type="number"
                        :step="$basis === 'per_sqft' ? '0.01' : '1'" min="0" max="10000000"
                        :label="$per ? 'Rate per '.$per.' for this home' : 'What this home pays'"
                        class="numeric"
                        :disabled="$isExempt" />

                    @if ($per && $quantity > 0)
                        <p class="-mt-2 text-xs text-secondary">
                            This home has {{ $trim($quantity) }} {{ $per === $areaUnit ? $areaUnit : \Illuminate\Support\Str::plural($per, $quantity) }},
                            so its bill comes to
                            <x-ui.money :amount="round((float) $amount * $quantity, 2)" class="font-medium" />
                            before tax.
                        </p>
                    @else
                        <p class="-mt-2 text-xs text-secondary">Per bill, before tax.</p>
                    @endif

                    <label class="flex min-h-11 items-center gap-2 text-sm" for="home-exempt">
                        <input type="checkbox" id="home-exempt" wire:model.live="isExempt"
                            class="size-5 rounded border-strong accent-[var(--accent)]">
                        Do not charge this home for {{ $editing->name }} at all
                    </label>

                    <x-ui.input wire:model="reason" name="reason" label="Why" maxlength="160"
                        placeholder="e.g. AGM concession, ground floor, no lift access"
                        hint="Written on the record so the next committee knows what was agreed." />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input wire:model="startsOn" name="startsOn" type="date" label="From"
                            hint="Leave empty to apply straight away." />
                        <x-ui.input wire:model="endsOn" name="endsOn" type="date" label="Until"
                            hint="Leave empty to keep it until changed." />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'home-amount')">Cancel</x-ui.button>
                        <x-ui.button type="submit" icon="check">Save for this home</x-ui.button>
                    </div>
                </form>
            @endif
        </x-ui.modal>
    @endif
</div>
