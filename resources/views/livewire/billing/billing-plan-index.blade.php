<div>
    <x-ui.page-header title="Billing plans" description="How often bills go out, and what they carry.">
        <x-slot:actions>
            <x-ui.button :href="route('charge-heads.index')" variant="secondary" icon="tag">Charge heads</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($plans->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="calendar-repeat"
                title="No billing plans yet"
                description="A plan decides the billing cycle, the due date, and which charge heads appear on each bill."
            />
        </x-ui.card>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($plans as $plan)
                <x-ui.card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold">{{ $plan->name }}</h2>
                            <p class="mt-0.5 text-sm text-secondary">
                                {{ $plan->cycleLabel() }} · due {{ $plan->due_after_days }} days after issue
                            </p>
                        </div>
                        <x-ui.status :value="$plan->is_active ? 'active' : 'inactive'" />
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-subtle pt-4 text-sm">
                        <div>
                            <dt class="text-xs text-muted">Next run</dt>
                            <dd class="numeric mt-0.5 font-medium">
                                {{ $plan->next_run_on?->format('j M Y') ?? 'Not scheduled' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Invoices raised</dt>
                            <dd class="numeric mt-0.5 font-medium">{{ number_format($plan->invoices_count) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Generation</dt>
                            <dd class="mt-0.5 font-medium">{{ $plan->auto_generate ? 'Automatic' : 'Manual' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Late fee</dt>
                            <dd class="mt-0.5 font-medium">
                                {{ $plan->lateFeeRule?->describe() ?? 'None' }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-4 border-t border-subtle pt-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted">Paying the year in one go</p>
                        @if ($plan->offersAdvance())
                            <p class="mt-1 text-sm">
                                {{ rtrim(rtrim(number_format((float) $plan->advance_discount_percent, 2), '0'), '.') }}%
                                off {{ $plan->advance_periods }} {{ \Illuminate\Support\Str::plural('bill', (int) $plan->advance_periods) }}
                                paid together.
                            </p>
                            @if ($plan->advanceDiscounts->isNotEmpty())
                                <p class="mt-1 text-xs text-secondary">
                                    Different for
                                    {{ $plan->advanceDiscounts
                                        ->map(fn ($d) => $d->block?->name.' ('.rtrim(rtrim(number_format((float) $d->discount_percent, 2), '0'), '.').'%)')
                                        ->filter()->implode(', ') }}.
                                </p>
                            @endif
                        @else
                            <p class="mt-1 text-sm text-muted">Nothing offered yet.</p>
                        @endif
                    </div>

                    <div class="mt-4 border-t border-subtle pt-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted">Charge heads</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @forelse ($plan->chargeHeads as $head)
                                <x-ui.badge tone="neutral">{{ $head->name }}</x-ui.badge>
                            @empty
                                <span class="text-sm text-[var(--color-critical)]">None yet - this plan cannot bill.</span>
                            @endforelse
                        </div>
                    </div>

                    @canany([\App\Enums\Permission::INVOICE_GENERATE, \App\Enums\Permission::BILLING_MANAGE])
                        <div class="mt-5 flex flex-wrap gap-2 border-t border-subtle pt-4">
                            @can(\App\Enums\Permission::INVOICE_GENERATE)
                                <x-ui.button
                                    size="sm"
                                    icon="arrow-path"
                                    wire:click="runNow({{ $plan->id }})"
                                    data-confirm="Raise bills for every unit on this plan?"
                                    data-confirm-detail="Residents are billed and, if the plan issues automatically, told straight away. A bill already raised for this period is not raised twice."
                                    data-confirm-action="Raise the bills"
                                    data-confirm-tone="caution"
                                    wire:loading.attr="disabled"
                                    wire:target="runNow({{ $plan->id }})"
                                >
                                    <span wire:loading.remove wire:target="runNow({{ $plan->id }})">Run now</span>
                                    <span wire:loading wire:target="runNow({{ $plan->id }})">Running&hellip;</span>
                                </x-ui.button>
                            @endcan
                            @can(\App\Enums\Permission::BILLING_MANAGE)
                                <x-ui.button size="sm" variant="secondary" wire:click="editAdvance({{ $plan->id }})">
                                    {{ $plan->offersAdvance() ? 'Change prepayment offer' : 'Offer a prepayment discount' }}
                                </x-ui.button>
                                <x-ui.button size="sm" variant="secondary" wire:click="toggle({{ $plan->id }})">
                                    {{ $plan->is_active ? 'Pause' : 'Activate' }}
                                </x-ui.button>
                            @endcan
                        </div>
                    @endcanany
                </x-ui.card>
            @endforeach
        </div>
    @endif

    {{--
        The prepayment deal, in the words a meeting uses.

        Asked as money because that is how it is decided ("12,000 a month, or
        1,20,000 for the year"), and stored as a percentage because that is the
        only form that still means the same thing after maintenance changes or
        where the flats inside a building pay different amounts by size.
    --}}
    <x-ui.modal name="advance-offer" :title="$editing ? 'Paying the year in one go: '.$editing->name : 'Paying the year in one go'" max-width="2xl">
        @if ($editing)
            @php $periods = $editing->periodsPerYear(); @endphp

            @if ($periods < 2)
                <div class="space-y-4">
                    <x-ui.alert tone="caution" title="This plan already bills the whole year at once">
                        A {{ strtolower($editing->cycleLabel()) }} plan raises one bill a year, so there is
                        nothing left to pay up front. Set the discount on a monthly or quarterly plan instead.
                    </x-ui.alert>
                    <div class="flex justify-end">
                        <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'advance-offer')">Close</x-ui.button>
                    </div>
                </div>
            @else
                <form data-validate wire:submit="saveAdvance" class="space-y-5">
                    <x-billing.advance-question
                        :offers="$offersAdvance"
                        :periods="$periods"
                        :society-year="$reference['society']"
                        :amount="$yearAmount"
                        :blocks="$blocks"
                        :block-years="$reference['blocks']"
                        :block-amounts="$blockYearAmounts"
                        amount-field="yearAmount"
                        block-field="blockYearAmounts" />

                    <x-ui.alert tone="info">
                        This changes what residents are shown and what a prepayment is worked out at.
                        Bills already raised are untouched.
                    </x-ui.alert>

                    <div class="flex justify-end gap-2">
                        <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'advance-offer')">Cancel</x-ui.button>
                        <x-ui.button type="submit" icon="check">Save the offer</x-ui.button>
                    </div>
                </form>
            @endif
        @endif
    </x-ui.modal>
</div>
