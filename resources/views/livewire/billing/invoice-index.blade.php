<div>
    <x-ui.page-header
        title="Invoices"
        :description="$canSeeAll ? 'Every bill raised by the society.' : 'Bills raised for your units.'"
    >
        <x-slot:actions>
            @can(\App\Enums\Permission::INVOICE_GENERATE)
                <x-ui.button :href="route('billing-plans.index')" icon="calendar-repeat" variant="secondary">
                    Billing plans
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Summary of whatever the current filter selects, not just this page. --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <x-ui.stat label="Invoices" :value="number_format($summary['count'])" icon="receipt" />
        <x-ui.stat label="Billed" :value="\App\Support\Money::compact($summary['billed'])" icon="banknote" />
        <x-ui.stat
            label="Outstanding"
            :value="\App\Support\Money::compact($summary['outstanding'])"
            icon="alert"
            :tone="$summary['outstanding'] > 0 ? 'caution' : 'positive'"
        />
    </div>

    <x-ui.table
        :headers="[
            'Invoice',
            'Unit',
            'Period',
            'Due',
            ['label' => 'Total', 'align' => 'right'],
            ['label' => 'Balance', 'align' => 'right'],
            'Status',
            '',
        ]"
        :is-empty="$invoices->isEmpty()"
        empty="No invoices match these filters"
        empty-icon="receipt"
        caption="Invoices, with period, due date, total and outstanding balance"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    icon="search"
                    placeholder="Invoice or unit number"
                    aria-label="Search invoices"
                />
            </div>

            <x-ui.select wire:model.live="status" aria-label="Filter by status" class="w-auto min-w-36">
                <option value="">All statuses</option>
                @foreach (['issued', 'partially_paid', 'overdue', 'paid', 'draft', 'cancelled'] as $value)
                    <option value="{{ $value }}">{{ ucwords(str_replace('_', ' ', $value)) }}</option>
                @endforeach
            </x-ui.select>

            @if ($canSeeAll && $blocks->isNotEmpty())
                <x-ui.select wire:model.live="blockId" aria-label="Filter by block" class="w-auto min-w-32">
                    <option value="">All blocks</option>
                    @foreach ($blocks as $block)
                        <option value="{{ $block->id }}">{{ $block->name }}</option>
                    @endforeach
                </x-ui.select>
            @endif

            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif

            <span class="ml-auto text-xs text-muted" wire:loading wire:target="search,status,blockId">Updating&hellip;</span>
        </x-slot:toolbar>

        @foreach ($invoices as $invoice)
            <x-ui.tr :href="route('invoices.show', $invoice)">
                <x-ui.td label="Invoice" primary>
                    <a href="{{ route('invoices.show', $invoice) }}" class="hover:underline">
                        {{ $invoice->invoice_number }}
                    </a>
                </x-ui.td>

                <x-ui.td label="Unit">
                    {{ $invoice->unit?->label ?? '—' }}
                    @if ($canSeeAll && $invoice->unit?->billingContact()?->user)
                        <span class="block text-xs text-muted">{{ $invoice->unit->billingContact()->user->name }}</span>
                    @endif
                </x-ui.td>

                <x-ui.td label="Period">
                    @if ($invoice->period_start)
                        {{ $invoice->period_start->format('M Y') }}
                    @else
                        —
                    @endif
                </x-ui.td>

                <x-ui.td label="Due">
                    {{ $invoice->due_date->format('j M Y') }}
                    @if ($invoice->isOverdue())
                        <span class="block text-xs font-medium text-[var(--color-critical)]">
                            {{ $invoice->daysOverdue() }} days late
                        </span>
                    @endif
                </x-ui.td>

                <x-ui.td label="Total" align="right">
                    <x-ui.money :amount="$invoice->total" />
                </x-ui.td>

                <x-ui.td label="Balance" align="right">
                    <x-ui.money
                        :amount="$invoice->balance"
                        :tone="$invoice->balance > 0 ? 'critical' : 'positive'"
                        class="font-semibold"
                    />
                </x-ui.td>

                <x-ui.td label="Status">
                    <x-ui.status :value="$invoice->status" />
                </x-ui.td>

                <x-ui.td align="right">
                    <a
                        href="{{ route('invoices.show', $invoice) }}"
                        class="inline-flex items-center gap-1 text-xs font-semibold accent-text hover:underline"
                    >
                        View
                        <x-ui.icon name="chevron-right" class="size-3.5" />
                    </a>
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>
            {{ $invoices->links() }}
        </x-slot:footer>
    </x-ui.table>
</div>
