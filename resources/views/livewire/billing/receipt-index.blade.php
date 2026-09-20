<div>
    <x-ui.page-header title="Receipts" description="Every receipt issued, in one gap-free series." />

    <x-ui.table
        :headers="['Receipt', 'Unit', 'Received from', 'Date', ['label' => 'Amount', 'align' => 'right'], 'Status', ['label' => '', 'align' => 'right']]"
        :is-empty="$receipts->isEmpty()"
        empty="No receipts issued yet"
        empty-icon="ticket"
        caption="Receipts with number, unit, date and amount"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Receipt number or unit" aria-label="Search receipts" />
            </div>

            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($receipts as $receipt)
            <x-ui.tr>
                <x-ui.td label="Receipt" primary>
                    {{ $receipt->receipt_number }}
                </x-ui.td>
                <x-ui.td label="Unit">
                    {{ $receipt->unit?->label ?? "—" }}
                </x-ui.td>
                <x-ui.td label="Received from">
                    {{ $receipt->received_from }}
                </x-ui.td>
                <x-ui.td label="Date">
                    {{ $receipt->issued_on->format("j M Y") }}
                </x-ui.td>
                <x-ui.td label="Amount" align="right">
                    <x-ui.money :amount="$receipt->amount" class="font-semibold" />
                </x-ui.td>
                <x-ui.td label="Status">
                    @if ($receipt->is_cancelled)<x-ui.badge tone="critical">Cancelled</x-ui.badge>@else<x-ui.badge tone="positive">Valid</x-ui.badge>@endif
                </x-ui.td>
                <x-ui.td align="right">
                    <a href="{{ route('receipts.pdf', $receipt) }}" class="text-xs font-semibold accent-text hover:underline">Download</a>
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $receipts->links() }}</x-slot:footer>
    </x-ui.table>
</div>
