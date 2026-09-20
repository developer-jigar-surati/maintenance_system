<div>
    <x-ui.page-header title="Residents" description="Owners, tenants and family members, by unit." />

    @if ($expiringSoon > 0)
        <x-ui.alert tone="caution" class="mb-5"
            title="{{ $expiringSoon }} rent {{ \Illuminate\Support\Str::plural('agreement', $expiringSoon) }} expiring within 60 days">
            Ask those units for renewed paperwork before the current agreement lapses.
        </x-ui.alert>
    @endif

    <x-ui.table
        :headers="['Resident', 'Unit', 'Relation', 'Contact', 'Since', 'Agreement ends', 'Status']"
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
            <x-ui.select wire:model.live="status" aria-label="Filter by status" class="w-auto min-w-28">
                <option value="">All</option>
                <option value="active">Current</option>
                <option value="ended">Past</option>
            </x-ui.select>
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
                    <span class="numeric">{{ $resident->user?->phone ?? '—' }}</span>
                    <span class="block truncate text-xs text-muted">{{ $resident->user?->email }}</span>
                </x-ui.td>
                <x-ui.td label="Since">{{ $resident->start_date?->format('M Y') ?? '—' }}</x-ui.td>
                <x-ui.td label="Agreement ends">
                    @if ($resident->agreement_end_date)
                        <span class="{{ $resident->agreementExpiringWithin(60) ? 'font-semibold text-[var(--color-caution)]' : '' }}">
                            {{ $resident->agreement_end_date->format('j M Y') }}
                        </span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </x-ui.td>
                <x-ui.td label="Status">
                    <x-ui.status :value="$resident->status === 'active' ? 'active' : 'closed'" />
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $residents->links() }}</x-slot:footer>
    </x-ui.table>
</div>
