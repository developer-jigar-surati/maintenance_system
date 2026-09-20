<div>
    <x-ui.page-header title="Reports" description="Statements derived straight from the ledger.">
        <x-slot:actions>
            <x-ui.button wire:click="export" variant="secondary" icon="download">Export CSV</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-5 flex flex-wrap items-end gap-3">
        <x-ui.select wire:model.live="report" label="Report" class="w-auto min-w-52">
            <option value="defaulters">Outstanding dues</option>
            <option value="income_expenditure">Income &amp; expenditure</option>
            <option value="balance_sheet">Balance sheet</option>
            <option value="trial_balance">Trial balance</option>
        </x-ui.select>

        @if ($report === 'income_expenditure')
            <x-ui.input wire:model.live="from" type="date" label="From" class="w-auto" />
        @endif

        @if ($report !== 'defaulters')
            <x-ui.input wire:model.live="to" type="date" :label="$report === 'income_expenditure' ? 'To' : 'As at'" class="w-auto" />
        @endif
    </div>

    @if ($report === 'defaulters')
        <x-ui.table
            :headers="['Unit', 'Billing contact', 'Phone', ['label' => 'Outstanding', 'align' => 'right'], ['label' => 'Days overdue', 'align' => 'right']]"
            :is-empty="$data['defaulters']->isEmpty()"
            empty="No unit has an outstanding balance"
            empty-icon="check"
            caption="Units with money outstanding, worst first"
        >
            @foreach ($data['defaulters'] as $row)
                <x-ui.tr>
                    <x-ui.td label="Unit" primary>{{ $row['unit']->label }}</x-ui.td>
                    <x-ui.td label="Contact">{{ $row['contact']?->name ?? '—' }}</x-ui.td>
                    <x-ui.td label="Phone"><span class="numeric">{{ $row['contact']?->phone ?? '—' }}</span></x-ui.td>
                    <x-ui.td label="Outstanding" align="right">
                        <x-ui.money :amount="$row['outstanding']" tone="critical" class="font-semibold" />
                    </x-ui.td>
                    <x-ui.td label="Days overdue" align="right">
                        <span class="numeric">{{ $row['days_overdue'] ?: '—' }}</span>
                    </x-ui.td>
                </x-ui.tr>
            @endforeach
        </x-ui.table>

    @elseif ($report === 'trial_balance')
        @unless ($data['balanced'])
            <x-ui.alert tone="critical" title="The trial balance does not agree" class="mb-4">
                Debits and credits differ. Something has written to the ledger outside the posting service.
            </x-ui.alert>
        @endunless

        <x-ui.table
            :headers="['Code', 'Account', ['label' => 'Debit', 'align' => 'right'], ['label' => 'Credit', 'align' => 'right']]"
            :is-empty="$data['rows']->isEmpty()"
            empty="Nothing posted yet"
            empty-icon="chart"
            caption="Trial balance as at the selected date"
        >
            @foreach ($data['rows'] as $row)
                <x-ui.tr>
                    <x-ui.td label="Code"><span class="numeric">{{ $row['account']->code }}</span></x-ui.td>
                    <x-ui.td label="Account" primary>{{ $row['account']->name }}</x-ui.td>
                    <x-ui.td label="Debit" align="right">
                        @if ($row['debit'] > 0)<x-ui.money :amount="$row['debit']" />@else<span class="text-muted">—</span>@endif
                    </x-ui.td>
                    <x-ui.td label="Credit" align="right">
                        @if ($row['credit'] > 0)<x-ui.money :amount="$row['credit']" />@else<span class="text-muted">—</span>@endif
                    </x-ui.td>
                </x-ui.tr>
            @endforeach

            <x-slot:foot>
                <tr class="block md:table-row">
                    <td colspan="2" class="hidden px-4 py-3 text-right text-sm font-semibold md:table-cell">Total</td>
                    <td class="flex justify-between px-4 py-3 md:table-cell md:text-right">
                        <span class="text-sm font-semibold md:hidden">Debit</span>
                        <x-ui.money :amount="$data['debit']" class="font-bold" />
                    </td>
                    <td class="flex justify-between px-4 py-3 md:table-cell md:text-right">
                        <span class="text-sm font-semibold md:hidden">Credit</span>
                        <x-ui.money :amount="$data['credit']" class="font-bold" />
                    </td>
                </tr>
            </x-slot:foot>
        </x-ui.table>

    @elseif ($report === 'income_expenditure')
        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card title="Income" padded="false">
                <ul class="divide-y divide-[var(--border-subtle)]">
                    @forelse ($data['income'] as $row)
                        <li class="flex justify-between gap-3 px-5 py-2.5 text-sm">
                            <span>{{ $row['account']->name }}</span>
                            <x-ui.money :amount="$row['amount']" />
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-muted">No income in this period.</li>
                    @endforelse
                    <li class="flex justify-between gap-3 px-5 py-3 text-sm font-bold surface-sunken">
                        <span>Total income</span>
                        <x-ui.money :amount="$data['income_total']" />
                    </li>
                </ul>
            </x-ui.card>

            <x-ui.card title="Expenditure" padded="false">
                <ul class="divide-y divide-[var(--border-subtle)]">
                    @forelse ($data['expense'] as $row)
                        <li class="flex justify-between gap-3 px-5 py-2.5 text-sm">
                            <span>{{ $row['account']->name }}</span>
                            <x-ui.money :amount="$row['amount']" />
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-muted">No expenditure in this period.</li>
                    @endforelse
                    <li class="flex justify-between gap-3 px-5 py-3 text-sm font-bold surface-sunken">
                        <span>Total expenditure</span>
                        <x-ui.money :amount="$data['expense_total']" />
                    </li>
                </ul>
            </x-ui.card>
        </div>

        <x-ui.card class="mt-6">
            <div class="flex items-baseline justify-between">
                <p class="text-sm font-semibold">{{ $data['surplus'] >= 0 ? 'Surplus' : 'Deficit' }} for the period</p>
                <x-ui.money :amount="abs($data['surplus'])" class="text-2xl font-bold"
                    :tone="$data['surplus'] >= 0 ? 'positive' : 'critical'" />
            </div>
        </x-ui.card>

    @else
        @unless ($data['balanced'])
            <x-ui.alert tone="caution" title="The balance sheet does not tie out" class="mb-4">
                Assets do not equal liabilities plus funds and surplus. Check the ledger.
            </x-ui.alert>
        @endunless

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card title="Assets" padded="false">
                <ul class="divide-y divide-[var(--border-subtle)]">
                    @foreach ($data['assets'] as $row)
                        <li class="flex justify-between gap-3 px-5 py-2.5 text-sm">
                            <span>{{ $row['account']->name }}</span>
                            <x-ui.money :amount="$row['amount']" />
                        </li>
                    @endforeach
                    <li class="flex justify-between gap-3 px-5 py-3 text-sm font-bold surface-sunken">
                        <span>Total assets</span>
                        <x-ui.money :amount="$data['assets_total']" />
                    </li>
                </ul>
            </x-ui.card>

            <x-ui.card title="Liabilities &amp; funds" padded="false">
                <ul class="divide-y divide-[var(--border-subtle)]">
                    @foreach ($data['liabilities'] as $row)
                        <li class="flex justify-between gap-3 px-5 py-2.5 text-sm">
                            <span>{{ $row['account']->name }}</span>
                            <x-ui.money :amount="$row['amount']" />
                        </li>
                    @endforeach
                    @foreach ($data['funds'] as $row)
                        <li class="flex justify-between gap-3 px-5 py-2.5 text-sm">
                            <span>{{ $row['account']->name }}</span>
                            <x-ui.money :amount="$row['amount']" />
                        </li>
                    @endforeach
                    <li class="flex justify-between gap-3 px-5 py-2.5 text-sm">
                        <span>{{ $data['surplus'] >= 0 ? 'Surplus' : 'Deficit' }}</span>
                        <x-ui.money :amount="$data['surplus']" />
                    </li>
                    <li class="flex justify-between gap-3 px-5 py-3 text-sm font-bold surface-sunken">
                        <span>Total</span>
                        <x-ui.money :amount="$data['liabilities_total'] + $data['funds_total'] + $data['surplus']" />
                    </li>
                </ul>
            </x-ui.card>
        </div>
    @endif
</div>
