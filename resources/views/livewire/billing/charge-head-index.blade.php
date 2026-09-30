<div>
    <x-ui.page-header title="Charge heads" description="What the society bills for, and how each amount is worked out." />

    <x-ui.table
        :headers="['Head', 'Type', 'Basis', 'Fund', ['label' => 'Rate', 'align' => 'right'], ['label' => 'Tax', 'align' => 'right'], 'Status', '']"
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
                    @php $slices = $sliceCounts[$head->id] ?? 0; @endphp
                    @if ($slices > 0)
                        {{-- A head split by building or size has no single
                             number, and showing one would be a lie. --}}
                        <button type="button" wire:click="editRates({{ $head->id }})"
                            class="text-sm font-medium accent-text hover:underline">
                            {{ $slices }} different {{ \Illuminate\Support\Str::plural('rate', $slices) }}
                        </button>
                    @else
                        <span class="numeric">{{ rtrim(rtrim(number_format((float) $head->default_rate, 4), "0"), ".") }}</span>
                    @endif
                </x-ui.td>
                <x-ui.td label="Tax" align="right">
                    @if ($head->is_taxable)<span class="numeric">{{ rtrim(rtrim(number_format((float) $head->tax_rate, 2), "0"), ".") }}%</span>@else<span class="text-muted">–</span>@endif
                </x-ui.td>
                <x-ui.td label="Status">
                    <x-ui.status :value="$head->is_active ? 'active' : 'inactive'" />
                </x-ui.td>
                <x-ui.td label="Rates">
                    @if ($canManage && $head->basis !== 'manual')
                        <x-ui.button size="sm" variant="ghost" wire:click="editRates({{ $head->id }})">
                            Set rates
                        </x-ui.button>
                    @endif
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $heads->links() }}</x-slot:footer>
    </x-ui.table>
    {{--
        Rates, asked the same way the setup wizard asks them.

        Rates get revised at every general body meeting, so setting them once
        during setup and never again would not be enough.
    --}}
    <x-ui.modal name="edit-rates" :title="$editing ? $editing->name : 'Rates'" max-width="2xl">
        @if ($editing)
            <div class="space-y-5">
                <x-billing.rate-question
                    :basis="$rateBasis"
                    :blocks="$blocks"
                    :sizes="$sizes"
                    :area-unit="auth()->user()?->currentSociety?->areaUnitLabel() ?? 'sq.ft.'" />

                <x-ui.alert tone="info">
                    Bills raised from now on use these. Bills already issued keep the rate they
                    were raised at, which is why an old receipt still adds up.
                </x-ui.alert>

                <div class="flex justify-end gap-2">
                    <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'edit-rates')">Cancel</x-ui.button>
                    <x-ui.button icon="check" wire:click="saveRates">Save rates</x-ui.button>
                </div>
            </div>
        @endif
    </x-ui.modal>
</div>
