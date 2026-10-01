<div>
    <x-ui.page-header title="Residents" description="Owners, tenants and family members, by unit." />

    @if ($expiringSoon > 0)
        <x-ui.alert tone="caution" class="mb-5"
            title="{{ $expiringSoon }} rent {{ \Illuminate\Support\Str::plural('agreement', $expiringSoon) }} expiring within 60 days">
            Ask those units for renewed paperwork before the current agreement lapses.
        </x-ui.alert>
    @endif

    <x-ui.table
        :headers="['Resident', 'Unit', 'Relation', 'Contact', 'Since', 'Agreement ends', 'Status', '']"
        :is-empty="$residents->isEmpty()"
        empty="No residents match these filters"
        empty-icon="users"
        caption="Residents with their unit, relation and contact details"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Name, phone or unit" aria-label="Search residents" />
            </div>
            <x-ui.select wire:model.live="relation" aria-label="Filter by relation" class="w-auto min-w-32">
                <option value="">All relations</option>
                @foreach (['owner' => 'Owner', 'co_owner' => 'Co-owner', 'tenant' => 'Tenant', 'family_member' => 'Family member', 'occupant' => 'Occupant'] as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </x-ui.select>
            @if ($canSeeHistory)
                <x-ui.select wire:model.live="status" aria-label="Filter by status" class="w-auto min-w-28">
                    <option value="">All</option>
                    <option value="active">Current</option>
                    <option value="ended">Past</option>
                </x-ui.select>
            @endif
            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($residents as $resident)
            <x-ui.tr>
                <x-ui.td label="Resident" primary>
                    {{ $resident->user?->name }}
                    @if ($resident->is_billing_contact)
                        <x-ui.badge tone="accent" class="ml-1.5">Billing contact</x-ui.badge>
                    @endif
                </x-ui.td>
                <x-ui.td label="Unit">{{ $resident->unit?->label }}</x-ui.td>
                <x-ui.td label="Relation">{{ ucwords(str_replace('_', ' ', $resident->relation)) }}</x-ui.td>
                <x-ui.td label="Contact">
                    <span class="numeric">{{ $resident->user?->phone ?? '–' }}</span>
                    <span class="block truncate text-xs text-muted">{{ $resident->user?->email }}</span>
                </x-ui.td>
                <x-ui.td label="Since">{{ $resident->start_date?->format('M Y') ?? '–' }}</x-ui.td>
                <x-ui.td label="Agreement ends">
                    @if ($resident->agreement_end_date)
                        <span class="{{ $resident->agreementExpiringWithin(60) ? 'font-semibold text-[var(--color-caution)]' : '' }}">
                            {{ $resident->agreement_end_date->format('j M Y') }}
                        </span>
                    @else
                        <span class="text-muted">–</span>
                    @endif
                </x-ui.td>
                <x-ui.td label="Status">
                    <x-ui.status :value="$resident->status === 'active' ? 'active' : 'closed'" />
                </x-ui.td>
                <x-ui.td label="History">
                    @if ($canSeeHistory && $resident->user)
                        <x-ui.button size="sm" variant="ghost"
                            wire:click="showHistory({{ $resident->user->id }})">History</x-ui.button>
                    @endif
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $residents->links() }}</x-slot:footer>
    </x-ui.table>
    {{-- One person's whole record, which is rarely one unit: people move
         within a society, and a deposit query three years later needs the
         dates. --}}
    <x-ui.modal name="resident-history" :title="$person ? $person->name.' - where they have lived' : 'History'" max-width="xl">
        @if ($person)
            @if ($personHistory->isEmpty())
                <x-ui.empty-state icon="users" title="No occupancy recorded for this person" />
            @else
                <ol class="space-y-3">
                    @foreach ($personHistory as $stay)
                        @php $past = $stay->status === 'ended'; @endphp
                        <li @class(['rounded-xl border border-subtle p-4', 'opacity-70' => $past])>
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold">{{ $stay->unit?->label ?? 'Unit removed' }}</p>
                                    <p class="numeric mt-0.5 text-xs text-secondary">
                                        {{ $stay->start_date?->format('j M Y') ?? '–' }} &rarr;
                                        {{ $past ? ($stay->end_date?->format('j M Y') ?? 'ended') : 'present' }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    @if ($stay->is_billing_contact)
                                        <x-ui.badge tone="positive">Bills</x-ui.badge>
                                    @endif
                                    <x-ui.badge :tone="$past ? 'neutral' : ($stay->isOwner() ? 'accent' : 'info')">
                                        {{ ucwords(str_replace('_', ' ', $stay->relation)) }}
                                    </x-ui.badge>
                                </div>
                            </div>

                            @if ($stay->rent_amount)
                                <p class="mt-1 text-xs text-muted">Rent on file: <x-ui.money :amount="$stay->rent_amount" /></p>
                            @endif
                            @if ($past && $stay->move_out_reason)
                                <p class="mt-1 text-xs text-muted">Left: {{ $stay->move_out_reason }}</p>
                            @endif
                            @if ($past && $stay->handover_notes)
                                <p class="mt-1 text-xs text-secondary">{{ $stay->handover_notes }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        @endif
    </x-ui.modal>
</div>
