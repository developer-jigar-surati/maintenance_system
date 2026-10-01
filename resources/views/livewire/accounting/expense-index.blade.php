<div>
    <x-ui.page-header title="Expenses" description="Vendor bills and society spending.">
        <x-slot:actions>
            <x-ui.button :href="route('vendors.index')" variant="secondary" icon="truck">Vendors</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <x-ui.stat label="Total billed" :value="\App\Support\Money::compact($summary['total'])" icon="wallet" />
        <x-ui.stat label="Unpaid" :value="\App\Support\Money::compact($summary['unpaid'])" icon="alert"
            :tone="$summary['unpaid'] > 0 ? 'caution' : 'positive'" />
        <x-ui.stat label="Awaiting approval" :value="$summary['pending']" icon="clock"
            :tone="$summary['pending'] > 0 ? 'caution' : 'neutral'" />
    </div>

    <x-ui.table
        :headers="['Expense', 'Vendor', 'Head', 'Bill date', ['label' => 'Total', 'align' => 'right'], ['label' => 'Balance', 'align' => 'right'], 'Status', '']"
        :is-empty="$expenses->isEmpty()"
        empty="No expenses match these filters"
        empty-icon="wallet"
        caption="Expenses with vendor, head, amount and approval status"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Number, bill or vendor" aria-label="Search expenses" />
            </div>
            <x-ui.select wire:model.live="status" aria-label="Filter by status" class="w-auto min-w-36">
                <option value="">All statuses</option>
                @foreach (['draft', 'pending_approval', 'approved', 'partially_paid', 'paid', 'rejected'] as $v)
                    <option value="{{ $v }}">{{ ucwords(str_replace('_', ' ', $v)) }}</option>
                @endforeach
            </x-ui.select>
            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($expenses as $expense)
            <x-ui.tr>
                <x-ui.td label="Expense" primary>
                    {{ $expense->expense_number }}
                    @if ($expense->bill_number)
                        <span class="block text-xs text-muted">bill {{ $expense->bill_number }}</span>
                    @endif
                </x-ui.td>
                <x-ui.td label="Vendor">{{ $expense->vendor?->name ?? '–' }}</x-ui.td>
                <x-ui.td label="Head">{{ $expense->chargeHead?->name ?? '–' }}</x-ui.td>
                <x-ui.td label="Bill date">{{ $expense->bill_date->format('j M Y') }}</x-ui.td>
                <x-ui.td label="Total" align="right"><x-ui.money :amount="$expense->total" /></x-ui.td>
                <x-ui.td label="Balance" align="right">
                    <x-ui.money :amount="$expense->balance" :tone="$expense->balance > 0 ? 'critical' : 'positive'" />
                </x-ui.td>
                <x-ui.td label="Status"><x-ui.status :value="$expense->status" /></x-ui.td>
                <x-ui.td align="right">
                    @if ($canApprove && $expense->status === 'pending_approval')
                        <x-ui.button size="sm" variant="positive" wire:click="approve({{ $expense->id }})">Approve</x-ui.button>
                    @endif
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $expenses->links() }}</x-slot:footer>
    </x-ui.table>
</div>
