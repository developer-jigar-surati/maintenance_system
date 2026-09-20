<div>
    <x-ui.page-header title="Charge heads" description="What the society bills for, and how each amount is worked out." />

    <x-ui.table
        :headers="['Head', 'Type', 'Basis', 'Fund', ['label' => 'Rate', 'align' => 'right'], ['label' => 'Tax', 'align' => 'right'], 'Status']"
        :is-empty="$heads->isEmpty()"
        empty="No charge heads yet"
        empty-icon="tag"
        caption="Charge heads with type, billing basis and rate"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Name or code" aria-label="Search charge heads" />
            </div>

            <x-ui.select wire:model.live="type" aria-label="Filter by type" class="w-auto min-w-32">
                <option value="">All types</option>
                    <option value="income">Income</option>
                    <option value="expense">Expense</option>
            </x-ui.select>

            <x-ui.select wire:model.live="fund" aria-label="Filter by fund" class="w-auto min-w-32">
                <option value="">All funds</option>
                    <option value="general">General</option>
                    <option value="sinking">Sinking</option>
                    <option value="corpus">Corpus</option>
                    <option value="repair">Repair</option>
                    <option value="festival">Festival</option>
                    <option value="welfare">Welfare</option>
            </x-ui.select>

            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($heads as $head)
            <x-ui.tr>
                <x-ui.td label="Head" primary>
                    {{ $head->name }}
                    <span class="block text-xs text-muted">{{ $head->code }}</span>
                </x-ui.td>
                <x-ui.td label="Type">
                    <x-ui.badge :tone="$head->type === 'income' ? 'positive' : 'caution'">{{ ucfirst($head->type) }}</x-ui.badge>
                </x-ui.td>
                <x-ui.td label="Basis">
                    {{ ucwords(str_replace("_", " ", $head->basis)) }}
                </x-ui.td>
                <x-ui.td label="Fund">
                    {{ ucfirst($head->fund) }}
                </x-ui.td>
                <x-ui.td label="Rate" align="right">
                    <span class="numeric">{{ rtrim(rtrim(number_format((float) $head->default_rate, 4), "0"), ".") }}</span>
                </x-ui.td>
                <x-ui.td label="Tax" align="right">
                    @if ($head->is_taxable)<span class="numeric">{{ rtrim(rtrim(number_format((float) $head->tax_rate, 2), "0"), ".") }}%</span>@else<span class="text-muted">—</span>@endif
                </x-ui.td>
                <x-ui.td label="Status">
                    <x-ui.status :value="$head->is_active ? 'active' : 'inactive'" />
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $heads->links() }}</x-slot:footer>
    </x-ui.table>
</div>
