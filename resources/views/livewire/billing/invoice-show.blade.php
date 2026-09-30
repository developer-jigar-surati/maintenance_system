<div>
    <div class="mb-5">
        <a href="{{ route('invoices.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-secondary hover:text-primary">
            <x-ui.icon name="chevron-left" class="size-4" />
            All invoices
        </a>
    </div>

    <x-ui.page-header :title="$invoice->invoice_number">
        <x-slot:description>
            {{ $invoice->unit?->label }}
            @if ($invoice->period_start)
                · {{ $invoice->period_start->format('j M') }} – {{ $invoice->period_end?->format('j M Y') }}
            @endif
        </x-slot:description>

        <x-slot:actions>
            <x-ui.button :href="route('invoices.pdf', $invoice)" variant="secondary" icon="download">PDF</x-ui.button>

            @if ($canPayOnline)
                <x-ui.button wire:click="payOnline" icon="banknote">Pay online</x-ui.button>
            @endif

            @if ($canRecord && $invoice->balance > 0)
                <x-ui.button x-on:click="$dispatch('open-modal', 'record-payment')" icon="plus">
                    Record payment
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($invoice->status === 'cancelled')
        <x-ui.alert tone="critical" title="This invoice was cancelled" class="mb-5">
            {{ $invoice->cancellation_reason }}
        </x-ui.alert>
    @elseif ($invoice->isOverdue())
        <x-ui.alert tone="caution" title="Payment overdue" class="mb-5">
            This bill was due on {{ $invoice->due_date->format('j F Y') }},
            {{ $invoice->daysOverdue() }} days ago.
        </x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Line items --}}
            <x-ui.table
                :headers="[
                    'Description',
                    ['label' => 'Qty', 'align' => 'right'],
                    ['label' => 'Rate', 'align' => 'right'],
                    ['label' => 'Amount', 'align' => 'right'],
                ]"
                caption="Charges on this invoice"
            >
                @foreach ($invoice->lines as $line)
                    <x-ui.tr>
                        <x-ui.td label="Description" primary>
                            {{ $line->description }}
                            @if ($line->isLateFee())
                                <x-ui.badge tone="critical" class="ml-1.5">Interest</x-ui.badge>
                            @endif
                            @if ($line->tax_rate > 0)
                                <span class="block text-xs text-muted">
                                    incl. {{ rtrim(rtrim(number_format((float) $line->tax_rate, 2), '0'), '.') }}% tax
                                </span>
                            @endif
                        </x-ui.td>
                        <x-ui.td label="Qty" align="right">
                            <span class="numeric">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</span>
                        </x-ui.td>
                        <x-ui.td label="Rate" align="right">
                            <span class="numeric">{{ rtrim(rtrim(number_format((float) $line->rate, 4), '0'), '.') }}</span>
                        </x-ui.td>
                        <x-ui.td label="Amount" align="right">
                            <x-ui.money :amount="$line->line_total" />
                        </x-ui.td>
                    </x-ui.tr>
                @endforeach

                <x-slot:foot>
                    <tr class="block md:table-row">
                        <td colspan="3" class="hidden px-4 py-2 text-right text-sm text-secondary md:table-cell">Subtotal</td>
                        <td class="flex justify-between px-4 py-2 text-right md:table-cell">
                            <span class="text-sm text-secondary md:hidden">Subtotal</span>
                            <x-ui.money :amount="$invoice->subtotal" class="text-sm" />
                        </td>
                    </tr>
                    @if ($invoice->tax_total > 0)
                        <tr class="block md:table-row">
                            <td colspan="3" class="hidden px-4 py-2 text-right text-sm text-secondary md:table-cell">Tax</td>
                            <td class="flex justify-between px-4 py-2 text-right md:table-cell">
                                <span class="text-sm text-secondary md:hidden">Tax</span>
                                <x-ui.money :amount="$invoice->tax_total" class="text-sm" />
                            </td>
                        </tr>
                    @endif
                    <tr class="block md:table-row">
                        <td colspan="3" class="hidden px-4 py-3 text-right text-sm font-semibold md:table-cell">Total</td>
                        <td class="flex justify-between px-4 py-3 text-right md:table-cell">
                            <span class="text-sm font-semibold md:hidden">Total</span>
                            <x-ui.money :amount="$invoice->total" class="text-base font-bold" />
                        </td>
                    </tr>
                </x-slot:foot>
            </x-ui.table>

            {{-- Payments applied --}}
            @if ($invoice->allocations->isNotEmpty())
                <x-ui.card title="Payments received" padded="false">
                    <ul class="divide-y divide-[var(--border-subtle)]">
                        @foreach ($invoice->allocations as $allocation)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ $allocation->payment?->payment_number }}
                                    </p>
                                    <p class="truncate text-xs text-muted">
                                        {{ $allocation->payment?->methodLabel() }} ·
                                        {{ $allocation->payment?->paid_at?->format('j M Y') }}
                                        @if ($allocation->payment?->reference_number)
                                            · ref {{ $allocation->payment->reference_number }}
                                        @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <x-ui.money :amount="$allocation->amount" class="text-sm font-semibold" />
                                    <x-ui.status :value="$allocation->payment?->status ?? 'unknown'" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>

        {{-- Summary rail --}}
        <div class="space-y-6">
            <x-ui.card>
                <dl class="space-y-3 text-sm">
                    <div class="flex items-baseline justify-between">
                        <dt class="text-secondary">Status</dt>
                        <dd><x-ui.status :value="$invoice->status" /></dd>
                    </div>
                    <div class="flex items-baseline justify-between">
                        <dt class="text-secondary">Issued</dt>
                        <dd class="numeric">{{ $invoice->issue_date->format('j M Y') }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between">
                        <dt class="text-secondary">Due</dt>
                        <dd class="numeric">{{ $invoice->due_date->format('j M Y') }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between border-t border-subtle pt-3">
                        <dt class="text-secondary">Total</dt>
                        <dd><x-ui.money :amount="$invoice->total" class="font-semibold" /></dd>
                    </div>
                    <div class="flex items-baseline justify-between">
                        <dt class="text-secondary">Paid</dt>
                        <dd><x-ui.money :amount="$invoice->amount_paid" tone="positive" /></dd>
                    </div>
                    <div class="flex items-baseline justify-between border-t border-subtle pt-3">
                        <dt class="font-semibold">Balance due</dt>
                        <dd>
                            <x-ui.money
                                :amount="$invoice->balance"
                                :tone="$invoice->balance > 0 ? 'critical' : 'positive'"
                                class="text-lg font-bold"
                            />
                        </dd>
                    </div>
                </dl>

                @if ($invoice->arrears_amount > 0)
                    <p class="mt-4 rounded-lg surface-inset px-3 py-2 text-xs text-secondary">
                        Earlier unpaid bills at the time of issue:
                        <x-ui.money :amount="$invoice->arrears_amount" class="font-semibold" />
                    </p>
                @endif
            </x-ui.card>

            <x-ui.card title="Billed to">
                @php $contact = $invoice->unit?->billingContact()?->user; @endphp
                <p class="text-sm font-semibold">{{ $contact?->name ?? 'No billing contact set' }}</p>
                <p class="mt-0.5 text-sm text-secondary">{{ $invoice->unit?->label }}</p>
                @if ($contact?->phone)
                    <p class="numeric mt-2 text-sm text-secondary">{{ $contact->phone }}</p>
                @endif
                @if ($contact?->email)
                    <p class="truncate text-sm text-secondary">{{ $contact->email }}</p>
                @endif
            </x-ui.card>

            @can(\App\Enums\Permission::INVOICE_CANCEL)
                @if ($invoice->status !== 'cancelled' && $invoice->amount_paid <= 0)
                    <x-ui.button
                        variant="secondary"
                        class="w-full"
                        wire:click="cancel"
                        data-confirm="Cancel this invoice?"
                        data-confirm-detail="The bill is voided and its ledger entries reversed. This cannot be undone."
                        data-confirm-action="Cancel the invoice"
                    >
                        Cancel invoice
                    </x-ui.button>
                @endif
            @endcan
        </div>
    </div>

    {{-- Offline payment capture --}}
    @if ($canRecord)
        <x-ui.modal name="record-payment" title="Record a payment">
            <form data-validate wire:submit="recordPayment" class="space-y-4" id="record-payment-form">
                <x-ui.input wire:model="amount" name="amount" label="Amount received" type="number" step="0.01" min="0.01" required />

                <x-ui.select wire:model="method" name="method" label="Method" required>
                    @foreach (['cash' => 'Cash', 'cheque' => 'Cheque', 'upi' => 'UPI', 'neft' => 'NEFT', 'rtgs' => 'RTGS', 'imps' => 'IMPS', 'card' => 'Card', 'netbanking' => 'Net banking', 'demand_draft' => 'Demand draft', 'other' => 'Other'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.input wire:model="paidAt" name="paidAt" label="Date received" type="date" required />
                <x-ui.input wire:model="reference" name="reference" label="Reference number" hint="Cheque number, UPI reference, and so on." maxlength="120" />
                <x-ui.textarea wire:model="notes" name="notes" label="Notes" rows="2" maxlength="500" />
            </form>

            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'record-payment')">Cancel</x-ui.button>
                <x-ui.button type="submit" form="record-payment-form">
                    <span wire:loading.remove wire:target="recordPayment">Record payment</span>
                    <span wire:loading wire:target="recordPayment">Saving&hellip;</span>
                </x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
