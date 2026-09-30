<div>
    <div class="mb-5">
        <a href="{{ route('units.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-secondary hover:text-primary">
            <x-ui.icon name="chevron-left" class="size-4" /> All units
        </a>
    </div>

    <x-ui.page-header :title="$unit->label"
        :description="ucwords(str_replace('_', ' ', $unit->type)).($unit->configuration ? ' · '.$unit->configuration : '')">
        {{-- Derived from who lives here, never typed, so it cannot drift. --}}
        <x-slot:actions>
            <x-ui.badge dot :tone="match ($unit->occupancy_status) {
                'owner_occupied' => 'positive',
                'rented' => 'info',
                'vacant' => 'caution',
                default => 'neutral',
            }">{{ ucwords(str_replace('_', ' ', $unit->occupancy_status)) }}</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-ui.stat label="Outstanding" :value="\App\Support\Money::compact($outstanding)" icon="banknote"
            :tone="$outstanding > 0 ? 'critical' : 'positive'" />
        <x-ui.stat label="Credit held" :value="\App\Support\Money::compact($credit)" icon="wallet"
            :tone="$credit > 0 ? 'positive' : 'neutral'" />
        <x-ui.stat label="Open tickets" :value="$openComplaints" icon="lifebuoy"
            :tone="$openComplaints > 0 ? 'caution' : 'neutral'" />
        <x-ui.stat label="Area"
            :value="$unit->carpet_area ? rtrim(rtrim(number_format((float) $unit->carpet_area, 2), '0'), '.') : '–'"
            :hint="$society->areaUnitLabel()" icon="building" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.table
                :headers="['Invoice', 'Period', 'Due', ['label' => 'Total', 'align' => 'right'], ['label' => 'Balance', 'align' => 'right'], 'Status']"
                :is-empty="$invoices->isEmpty()"
                empty="No invoices raised for this unit yet"
                empty-icon="receipt"
                caption="Recent invoices for this unit"
            >
                @foreach ($invoices as $invoice)
                    <x-ui.tr :href="route('invoices.show', $invoice)">
                        <x-ui.td label="Invoice" primary>{{ $invoice->invoice_number }}</x-ui.td>
                        <x-ui.td label="Period">{{ $invoice->period_start?->format('M Y') ?? '–' }}</x-ui.td>
                        <x-ui.td label="Due">{{ $invoice->due_date->format('j M Y') }}</x-ui.td>
                        <x-ui.td label="Total" align="right"><x-ui.money :amount="$invoice->total" /></x-ui.td>
                        <x-ui.td label="Balance" align="right">
                            <x-ui.money :amount="$invoice->balance" :tone="$invoice->balance > 0 ? 'critical' : 'positive'" />
                        </x-ui.td>
                        <x-ui.td label="Status"><x-ui.status :value="$invoice->status" /></x-ui.td>
                    </x-ui.tr>
                @endforeach
            </x-ui.table>

            @if ($payments->isNotEmpty())
                <x-ui.card title="Recent payments" padded="false">
                    <ul class="divide-y divide-[var(--border-subtle)]">
                        @foreach ($payments as $payment)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ $payment->payment_number }}</p>
                                    <p class="truncate text-xs text-muted">
                                        {{ $payment->methodLabel() }} · {{ $payment->paid_at->format('j M Y') }}
                                    </p>
                                </div>
                                <x-ui.money :amount="$payment->amount" class="text-sm font-semibold" tone="positive" />
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-ui.card title="Who lives here" padded="false">
                <x-slot:description>
                    @if ($canSeeHistory)
                        A unit keeps its whole occupancy history: rows are closed, never deleted,
                        so an old receipt still names whoever actually paid it.
                    @else
                        Who lives here now. Past residents are part of the society&rsquo;s record
                        - ask the secretary if you need them.
                    @endif
                </x-slot:description>

                @if ($canManageResidents)
                    <x-slot:actions>
                        <x-ui.button size="sm" variant="secondary" icon="plus"
                            x-on:click="$dispatch('open-modal', 'move-in')">Move someone in</x-ui.button>
                    </x-slot:actions>
                @endif

                @php
                    $shown = $showPastResidents ? $history : $history->where('status', 'active');
                @endphp

                @if ($shown->isEmpty())
                    <x-ui.empty-state icon="users" title="Nobody is living here"
                        description="This unit reads as vacant until someone is moved in." />
                @else
                    <ul class="divide-y divide-[var(--border-subtle)]">
                        @foreach ($shown as $resident)
                            @php $past = $resident->status === 'ended'; @endphp
                            <li @class(['px-5 py-4', 'opacity-70' => $past])>
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold">{{ $resident->user?->name ?? 'Unknown' }}</p>
                                        <p class="truncate text-xs text-muted">
                                            {{ $resident->user?->phone ?? $resident->user?->email }}
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @if ($resident->is_billing_contact)
                                            <x-ui.badge tone="positive">Bills go here</x-ui.badge>
                                        @endif
                                        <x-ui.badge :tone="$past ? 'neutral' : ($resident->isOwner() ? 'accent' : 'info')">
                                            {{ ucwords(str_replace('_', ' ', $resident->relation)) }}
                                        </x-ui.badge>
                                    </div>
                                </div>

                                {{-- The period, which is the whole point of keeping the row. --}}
                                <p class="numeric mt-1.5 text-xs text-secondary">
                                    {{ $resident->start_date?->format('j M Y') ?? 'Start unknown' }}
                                    &rarr;
                                    @if ($past)
                                        {{ $resident->end_date?->format('j M Y') ?? 'ended' }}
                                        <span class="text-muted">({{ $resident->durationLabel() }})</span>
                                    @else
                                        <span class="font-medium text-[var(--color-positive)]">present</span>
                                        <span class="text-muted">({{ $resident->durationLabel() }} so far)</span>
                                    @endif
                                </p>

                                @if ($resident->isTenant() && $resident->agreement_end_date && ! $past)
                                    <p class="mt-1 text-xs {{ $resident->agreementExpiringWithin(60) ? 'font-medium text-[var(--color-caution)]' : 'text-muted' }}">
                                        Agreement ends {{ $resident->agreement_end_date->format('j M Y') }}
                                        @if ($resident->agreementExpiringWithin(60)) - renewal due @endif
                                    </p>
                                @endif

                                @if ($past && $canSeeHistory && $resident->move_out_reason)
                                    <p class="mt-1 text-xs text-muted">Left: {{ $resident->move_out_reason }}</p>
                                @endif

                                @if ($canManageResidents && ! $past)
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @unless ($resident->is_billing_contact)
                                            <x-ui.button size="sm" variant="ghost"
                                                wire:click="makeBillingContact({{ $resident->id }})">Send bills here</x-ui.button>
                                        @endunless
                                        <x-ui.button size="sm" variant="ghost"
                                            wire:click="startMoveOut({{ $resident->id }})">Record move-out</x-ui.button>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($canSeeHistory && $pastCount > 0)
                    <div class="border-t border-subtle px-5 py-3">
                        <x-ui.button size="sm" variant="ghost"
                            wire:click="$toggle('showPastResidents')"
                            :aria-expanded="$showPastResidents ? 'true' : 'false'"
                        >
                            {{ $showPastResidents
                                ? 'Show only who lives here now'
                                : 'Show '.$pastCount.' past '.\Illuminate\Support\Str::plural('resident', $pastCount) }}
                        </x-ui.button>
                    </div>
                @endif
            </x-ui.card>

            @if ($unit->vehicles->isNotEmpty() || $unit->parkingSlots->isNotEmpty())
                <x-ui.card title="Parking & vehicles">
                    @if ($unit->parkingSlots->isNotEmpty())
                        <p class="text-xs font-medium uppercase tracking-wide text-muted">Slots</p>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            @foreach ($unit->parkingSlots as $slot)
                                <x-ui.badge tone="accent">{{ $slot->code }}</x-ui.badge>
                            @endforeach
                        </div>
                    @endif
                    @if ($unit->vehicles->isNotEmpty())
                        <p class="mt-4 text-xs font-medium uppercase tracking-wide text-muted">Vehicles</p>
                        <ul class="mt-1.5 space-y-1 text-sm">
                            @foreach ($unit->vehicles as $vehicle)
                                <li class="numeric">
                                    {{ $vehicle->registration_number }}
                                    <span class="text-xs text-muted">{{ $vehicle->make_model }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            @endif
        </div>
    </div>
    {{-- ------------------------------------------------------------------ --}}
    @if ($canManageResidents)
        <x-ui.modal name="move-in" title="Move someone in" max-width="xl">
            <form data-validate wire:submit="moveIn" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input wire:model="personName" name="personName" label="Name" required minlength="2" maxlength="120" />
                    <x-ui.select wire:model.live="relation" name="relation" label="Living here as" required
                        :options="[
                            'owner' => 'Owner',
                            'co_owner' => 'Co-owner',
                            'tenant' => 'Tenant',
                            'family_member' => 'Family member',
                            'occupant' => 'Other occupant',
                        ]" />
                    <x-ui.input wire:model="personEmail" name="personEmail" type="email" label="Email" required
                        hint="An account with this address is reused, so their history follows them." maxlength="180" />
                    <x-ui.input wire:model="personPhone" name="personPhone" type="tel" label="Phone" maxlength="20" data-rule="phone" />
                    <x-ui.input wire:model="startDate" name="startDate" type="date" label="Moving in on" required />

                    @if ($relation === 'tenant')
                        <x-ui.input wire:model="agreementEnd" name="agreementEnd" type="date"
                            label="Agreement ends" hint="Used to warn the committee before it runs out." />
                        <x-ui.input wire:model="rentAmount" name="rentAmount" type="number" step="0.01"
                            label="Monthly rent" hint="Kept on file; it does not affect maintenance." min="0" max="10000000" />
                    @endif
                </div>

                <label class="flex min-h-11 items-center gap-2 text-sm" for="billing-contact">
                    <input type="checkbox" id="billing-contact" wire:model="isBillingContact"
                        class="size-5 rounded border-strong accent-[var(--accent)]">
                    Send this unit&rsquo;s bills to this person
                </label>

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'move-in')">Cancel</x-ui.button>
                    <x-ui.button type="submit" icon="check">Move in</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal name="move-out" title="Record a move-out" max-width="lg">
            @if ($movingOut)
                <form data-validate wire:submit="moveOut" class="space-y-4">
                    <x-ui.alert tone="info">
                        <strong>{{ $movingOut->user?->name }}</strong> has lived here since
                        {{ $movingOut->start_date?->format('j F Y') }}. Their record is closed, not
                        deleted, so past bills and receipts keep naming them.
                    </x-ui.alert>

                    <x-ui.input wire:model="moveOutDate" name="moveOutDate" type="date" label="Moving out on" required />
                    <x-ui.input wire:model="moveOutReason" name="moveOutReason" label="Reason"
                        placeholder="e.g. Agreement ended, Unit sold, Relocated" maxlength="160" />
                    <x-ui.textarea wire:model="handoverNotes" name="handoverNotes" rows="3"
                        label="Handover notes"
                        placeholder="Keys returned, deposit settled, meter readings…" maxlength="2000" />

                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'move-out')">Cancel</x-ui.button>
                        <x-ui.button type="submit" icon="check">Record move-out</x-ui.button>
                    </div>
                </form>
            @endif
        </x-ui.modal>
    @endif
</div>
