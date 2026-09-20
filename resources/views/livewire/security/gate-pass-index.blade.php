<div>
    <x-ui.page-header title="Gate passes" description="Authorisations for moving goods and furniture past the gate.">
        <x-slot:actions>
            <x-ui.button x-on:click="$dispatch('open-modal', 'issue-pass')" icon="plus">Request a pass</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.table
        :headers="['Pass', 'Type', 'Unit', 'Issued to', 'Valid until', 'Status', '']"
        :is-empty="$passes->isEmpty()"
        empty="No gate passes match these filters"
        empty-icon="qr"
        caption="Gate passes with type, validity and approval status"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Pass number or name" aria-label="Search gate passes" />
            </div>
            <x-ui.select wire:model.live="status" aria-label="Filter by status" class="w-auto min-w-36">
                <option value="">All statuses</option>
                @foreach (['pending_approval', 'approved', 'used', 'rejected', 'expired', 'cancelled'] as $v)
                    <option value="{{ $v }}">{{ ucwords(str_replace('_', ' ', $v)) }}</option>
                @endforeach
            </x-ui.select>
            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($passes as $pass)
            <x-ui.tr>
                <x-ui.td label="Pass" primary>{{ $pass->pass_number }}</x-ui.td>
                <x-ui.td label="Type">{{ $pass->typeLabel() }}</x-ui.td>
                <x-ui.td label="Unit">{{ $pass->unit?->label ?? '—' }}</x-ui.td>
                <x-ui.td label="Issued to">
                    {{ $pass->issued_to_name }}
                    @if ($pass->items)
                        <span class="block text-xs text-muted">{{ count($pass->items) }} items</span>
                    @endif
                </x-ui.td>
                <x-ui.td label="Valid until">{{ $pass->valid_to->format('j M, g:i A') }}</x-ui.td>
                <x-ui.td label="Status"><x-ui.status :value="$pass->status" /></x-ui.td>
                <x-ui.td align="right">
                    <div class="flex items-center justify-end gap-2">
                        @if ($pass->status === 'approved')
                            <a href="{{ $pass->verificationUrl() }}" target="_blank" rel="noopener"
                               class="text-xs font-semibold accent-text hover:underline">QR</a>
                        @endif
                        @if ($canApprove && $pass->status === 'pending_approval')
                            <x-ui.button size="sm" variant="positive" wire:click="approve({{ $pass->id }})">Approve</x-ui.button>
                        @endif
                    </div>
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $passes->links() }}</x-slot:footer>
    </x-ui.table>

    <x-ui.modal name="issue-pass" title="Request a gate pass">
        <form wire:submit="issue" class="space-y-4" id="issue-pass-form">
            <x-ui.select wire:model="type" name="type" label="What is this for?" required>
                @foreach (['material_out' => 'Taking material out', 'material_in' => 'Bringing material in', 'move_in' => 'Moving in', 'move_out' => 'Moving out', 'contractor' => 'Contractor entry', 'vehicle' => 'Vehicle'] as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </x-ui.select>

            @if ($myUnits->count() > 1)
                <x-ui.select wire:model="unitId" name="unitId" label="Unit" required>
                    @foreach ($myUnits as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->label }}</option>
                    @endforeach
                </x-ui.select>
            @endif

            <x-ui.input wire:model="issuedTo" name="issuedTo" label="Who is carrying it out?" required />
            <x-ui.input wire:model="phone" name="phone" label="Their phone" type="tel" inputmode="numeric" />
            <x-ui.textarea wire:model="itemsText" name="itemsText" label="Items" rows="4"
                hint="One item per line." placeholder="Old sofa&#10;2 cartons" />
            <x-ui.input wire:model="validTo" name="validTo" label="Valid until" type="datetime-local" required />
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'issue-pass')">Cancel</x-ui.button>
            <x-ui.button type="submit" form="issue-pass-form">Request pass</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
