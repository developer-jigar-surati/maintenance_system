<div>
    <x-ui.page-header title="Vendors" description="Suppliers and contractors the society works with." />

    <x-ui.table
        :headers="['Vendor', 'Category', 'Contact', 'Contract ends', 'Status']"
        :is-empty="$vendors->isEmpty()"
        empty="No vendors added yet"
        empty-icon="truck"
        caption="Vendors with category, contact and contract period"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Name, category or phone" aria-label="Search vendors" />
            </div>

            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($vendors as $vendor)
            <x-ui.tr>
                <x-ui.td label="Vendor" primary>
                    {{ $vendor->name }}
                    @if ($vendor->gstin)<span class="block text-xs text-muted">GSTIN {{ $vendor->gstin }}</span>@endif
                </x-ui.td>
                <x-ui.td label="Category">
                    {{ $vendor->category ?? "-" }}
                </x-ui.td>
                <x-ui.td label="Contact">
                    {{ $vendor->contact_person ?? "-" }}
                    @if ($vendor->phone)<span class="numeric block text-xs text-muted">{{ $vendor->phone }}</span>@endif
                </x-ui.td>
                <x-ui.td label="Contract ends">
                    @if ($vendor->contract_end){{ $vendor->contract_end->format("j M Y") }}@if ($vendor->contract_end->isPast())<span class="block text-xs font-medium text-[var(--color-critical)]">Expired</span>@endif @else<span class="text-muted">–</span>@endif
                </x-ui.td>
                <x-ui.td label="Status">
                    <x-ui.status :value="$vendor->is_active ? 'active' : 'inactive'" />
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $vendors->links() }}</x-slot:footer>
    </x-ui.table>
</div>
