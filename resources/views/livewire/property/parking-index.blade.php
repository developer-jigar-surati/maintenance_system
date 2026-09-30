<div>
    <x-ui.page-header title="Parking" description="Slot allotment across the society." />

    <x-ui.table
        :headers="['Slot', 'Level', 'For', 'Allotted to', ['label' => 'Monthly', 'align' => 'right'], 'Status']"
        :is-empty="$parkingSlots->isEmpty()"
        empty="No parking slots recorded"
        empty-icon="car"
        caption="Parking slots with level, type and allotment"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Slot code" aria-label="Search parking" />
            </div>

            <x-ui.select wire:model.live="status" aria-label="Filter by status" class="w-auto min-w-32">
                <option value="">All statuses</option>
                    <option value="vacant">Vacant</option>
                    <option value="allotted">Allotted</option>
                    <option value="reserved">Reserved</option>
                    <option value="blocked">Blocked</option>
            </x-ui.select>

            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($parkingSlots as $parkingSlot)
            <x-ui.tr>
                <x-ui.td label="Slot" primary>
                    {{ $parkingSlot->code }}
                </x-ui.td>
                <x-ui.td label="Level">
                    {{ ucwords(str_replace("_", " ", $parkingSlot->level)) }}
                </x-ui.td>
                <x-ui.td label="For">
                    {{ ucwords(str_replace("_", " ", $parkingSlot->vehicle_type)) }}
                </x-ui.td>
                <x-ui.td label="Allotted to">
                    {{ $parkingSlot->unit?->label ?? ($parkingSlot->is_visitor_slot ? "Visitors" : "-") }}
                </x-ui.td>
                <x-ui.td label="Monthly" align="right">
                    @if ($parkingSlot->monthly_charge > 0)<x-ui.money :amount="$parkingSlot->monthly_charge" />@else<span class="text-muted">Free</span>@endif
                </x-ui.td>
                <x-ui.td label="Status">
                    <x-ui.status :value="$parkingSlot->status" />
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $parkingSlots->links() }}</x-slot:footer>
    </x-ui.table>
</div>
