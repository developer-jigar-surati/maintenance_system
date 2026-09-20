<div>
    <div class="mb-5">
        <a href="{{ route('units.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-secondary hover:text-primary">
            <x-ui.icon name="chevron-left" class="size-4" /> All units
        </a>
    </div>

    <x-ui.page-header :title="$unit->label"
        :description="ucwords(str_replace('_', ' ', $unit->type)).($unit->configuration ? ' · '.$unit->configuration : '')" />

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-ui.stat label="Outstanding" :value="\App\Support\Money::compact($outstanding)" icon="banknote"
            :tone="$outstanding > 0 ? 'critical' : 'positive'" />
        <x-ui.stat label="Credit held" :value="\App\Support\Money::compact($credit)" icon="wallet"
            :tone="$credit > 0 ? 'positive' : 'neutral'" />
        <x-ui.stat label="Open tickets" :value="$openComplaints" icon="lifebuoy"
            :tone="$openComplaints > 0 ? 'caution' : 'neutral'" />
        <x-ui.stat label="Area"
            :value="$unit->carpet_area ? rtrim(rtrim(number_format((float) $unit->carpet_area, 2), '0'), '.') : '—'"
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
                        <x-ui.td label="Period">{{ $invoice->period_start?->format('M Y') ?? '—' }}</x-ui.td>
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
            <x-ui.card title="Residents" padded="false">
                @php $active = $unit->residents->where('status', 'active'); @endphp
                @if ($active->isEmpty())
                    <x-ui.empty-state icon="users" title="No residents linked" />
                @else
                    <ul class="divide-y divide-[var(--border-subtle)]">
                        @foreach ($active as $resident)
                            <li class="px-5 py-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold">{{ $resident->user?->name }}</p>
                                        <p class="truncate text-xs text-muted">
                                            {{ $resident->user?->phone ?? $resident->user?->email }}
                                        </p>
                                    </div>
                                    <x-ui.badge :tone="$resident->isOwner() ? 'accent' : 'neutral'">
                                        {{ ucwords(str_replace('_', ' ', $resident->relation)) }}
                                    </x-ui.badge>
                                </div>
                                @if ($resident->isTenant() && $resident->agreement_end_date)
                                    <p class="mt-1 text-xs {{ $resident->agreementExpiringWithin(60) ? 'font-medium text-[var(--color-caution)]' : 'text-muted' }}">
                                        Agreement ends {{ $resident->agreement_end_date->format('j M Y') }}
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
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
</div>
