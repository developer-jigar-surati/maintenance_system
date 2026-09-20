<div>
    <x-ui.page-header title="Assets & AMC" description="Equipment the society owns and the contracts covering it." />

    <x-ui.table
        :headers="['Asset', 'Category', 'Location', 'Warranty', 'AMC', 'Status']"
        :is-empty="$assets->isEmpty()"
        empty="No assets recorded yet"
        empty-icon="cube"
        caption="Assets with category, location, condition and status"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Name, code or serial" aria-label="Search assets & amc" />
            </div>

            <x-ui.select wire:model.live="category" aria-label="Filter by category" class="w-auto min-w-32">
                <option value="">All categories</option>
                    <option value="lift">Lift</option>
                    <option value="generator">Generator</option>
                    <option value="water_pump">Water pump</option>
                    <option value="fire_safety">Fire safety</option>
                    <option value="cctv">CCTV</option>
                    <option value="stp">STP</option>
                    <option value="solar">Solar</option>
                    <option value="hvac">HVAC</option>
                    <option value="other">Other</option>
            </x-ui.select>

            <x-ui.select wire:model.live="status" aria-label="Filter by status" class="w-auto min-w-32">
                <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="under_repair">Under repair</option>
                    <option value="idle">Idle</option>
                    <option value="retired">Retired</option>
            </x-ui.select>

            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($assets as $asset)
            <x-ui.tr>
                <x-ui.td label="Asset" primary>
                    {{ $asset->name }}
                    @if ($asset->code)<span class="block text-xs text-muted">{{ $asset->code }}</span>@endif
                </x-ui.td>
                <x-ui.td label="Category">
                    {{ ucwords(str_replace("_", " ", $asset->category)) }}
                </x-ui.td>
                <x-ui.td label="Location">
                    {{ $asset->location ?? $asset->block?->name ?? "—" }}
                </x-ui.td>
                <x-ui.td label="Warranty">
                    @if ($asset->warranty_expires_on){{ $asset->warranty_expires_on->format("M Y") }}@if ($asset->warranty_expires_on->isPast())<span class="block text-xs text-muted">Expired</span>@endif @else<span class="text-muted">—</span>@endif
                </x-ui.td>
                <x-ui.td label="AMC">
                    @php $amc = $asset->amcContracts->firstWhere("status", "active"); @endphp
                    @if ($amc)<x-ui.badge tone="positive">to {{ $amc->end_date->format("M Y") }}</x-ui.badge>@else<span class="text-muted">None</span>@endif
                </x-ui.td>
                <x-ui.td label="Status">
                    <x-ui.status :value="$asset->status" />
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $assets->links() }}</x-slot:footer>
    </x-ui.table>
</div>
