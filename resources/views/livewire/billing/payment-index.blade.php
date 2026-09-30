<div>
    <x-ui.page-header title="Payments" description="Money received, online and offline." />

    @if ($canApprove && $pendingCount > 0)
        <x-ui.alert tone="caution" class="mb-5" title="{{ $pendingCount }} {{ \Illuminate\Support\Str::plural('payment', $pendingCount) }} awaiting approval">
            Offline payments are held until a committee member confirms the money was actually received.
        </x-ui.alert>
    @endif

    <x-ui.table
        :headers="[
            'Payment', 'Unit', 'Received', 'Method',
            ['label' => 'Amount', 'align' => 'right'],
            'Status', '',
        ]"
        :is-empty="$payments->isEmpty()"
        empty="No payments match these filters"
        empty-icon="banknote"
        caption="Payments received, with method, amount and approval status"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Payment, reference or unit" aria-label="Search payments" />
            </div>

            <x-ui.select wire:model.live="status" aria-label="Filter by status" class="w-auto min-w-36">
                <option value="">All statuses</option>
                @foreach (['awaiting_approval', 'completed', 'pending', 'failed', 'cancelled', 'refunded', 'bounced'] as $value)
                    <option value="{{ $value }}">{{ ucwords(str_replace('_', ' ', $value)) }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select wire:model.live="mode" aria-label="Filter by mode" class="w-auto min-w-28">
                <option value="">Any mode</option>
                <option value="offline">Offline</option>
                <option value="online">Online</option>
            </x-ui.select>

            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($payments as $payment)
            <x-ui.tr>
                <x-ui.td label="Payment" primary>
                    {{ $payment->payment_number }}
                    @if ($payment->reference_number)
                        <span class="block text-xs text-muted">ref {{ $payment->reference_number }}</span>
                    @endif
                </x-ui.td>
                <x-ui.td label="Unit">{{ $payment->unit?->label ?? '–' }}</x-ui.td>
                <x-ui.td label="Received">{{ $payment->paid_at->format('j M Y') }}</x-ui.td>
                <x-ui.td label="Method">
                    {{ $payment->methodLabel() }}
                    <span class="block text-xs text-muted">{{ ucfirst($payment->mode) }}</span>
                </x-ui.td>
                <x-ui.td label="Amount" align="right">
                    <x-ui.money :amount="$payment->amount" class="font-semibold" />
                    @if ($payment->unallocated_amount > 0)
                        <span class="block text-xs text-muted">
                            <x-ui.money :amount="$payment->unallocated_amount" /> unapplied
                        </span>
                    @endif
                </x-ui.td>
                <x-ui.td label="Status"><x-ui.status :value="$payment->status" /></x-ui.td>
                <x-ui.td align="right">
                    <div class="flex items-center justify-end gap-2">
                        @if ($payment->receipt)
                            <a href="{{ route('receipts.pdf', $payment->receipt) }}"
                               class="text-xs font-semibold accent-text hover:underline">Receipt</a>
                        @endif

                        @if ($canApprove && $payment->needsApproval())
                            <x-ui.button size="sm" variant="positive" wire:click="approve({{ $payment->id }})"
                                wire:loading.attr="disabled" wire:target="approve({{ $payment->id }})">
                                Approve
                            </x-ui.button>
                            <x-ui.button size="sm" variant="ghost" wire:click="startReject({{ $payment->id }})">
                                Reject
                            </x-ui.button>
                        @endif
                    </div>
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $payments->links() }}</x-slot:footer>
    </x-ui.table>

    <x-ui.modal name="reject-payment" title="Reject this payment" max-width="md">
        <form data-validate wire:submit="reject" id="reject-payment-form">
            <x-ui.textarea wire:model="rejectionReason" name="rejectionReason" label="Reason"
                hint="Shown to whoever recorded the payment." rows="3" required minlength="3" maxlength="255" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'reject-payment')">Cancel</x-ui.button>
            <x-ui.button variant="danger" type="submit" form="reject-payment-form">Reject payment</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
