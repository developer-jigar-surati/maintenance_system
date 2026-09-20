<div>
    <x-ui.page-header title="Visitors" description="Pre-approve guests and review who has come and gone.">
        <x-slot:actions>
            <x-ui.button x-on:click="$dispatch('open-modal', 'pre-approve')" icon="plus">Expect a visitor</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($awaitingMe->isNotEmpty())
        <x-ui.card title="Someone is at the gate for you" class="mb-6" padded="false">
            <ul class="divide-y divide-[var(--border-subtle)]">
                @foreach ($awaitingMe as $log)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">{{ $log->visitor_name }}</p>
                            <p class="text-xs text-muted">
                                {{ $log->purposeLabel() }}
                                @if ($log->phone) · {{ $log->phone }} @endif
                                · {{ $log->created_at->diffForHumans(short: true) }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <x-ui.button size="sm" variant="positive" wire:click="approve({{ $log->id }})">Allow</x-ui.button>
                            <x-ui.button size="sm" variant="danger" wire:click="deny({{ $log->id }})">Decline</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <x-ui.table
        :headers="['Visitor', 'Unit', 'Purpose', 'Code', 'In', 'Out', 'Status']"
        :is-empty="$logs->isEmpty()"
        empty="No visitor records match these filters"
        empty-icon="user-plus"
        caption="Visitor log with purpose, entry and exit times"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Name, phone or code" aria-label="Search visitors" />
            </div>
            <x-ui.select wire:model.live="status" aria-label="Filter by status" class="w-auto min-w-36">
                <option value="">All statuses</option>
                @foreach (['expected', 'pending_approval', 'approved', 'inside', 'exited', 'denied', 'expired'] as $v)
                    <option value="{{ $v }}">{{ ucwords(str_replace('_', ' ', $v)) }}</option>
                @endforeach
            </x-ui.select>
            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($logs as $log)
            <x-ui.tr>
                <x-ui.td label="Visitor" primary>
                    {{ $log->visitor_name }}
                    @if ($log->phone)<span class="numeric block text-xs text-muted">{{ $log->phone }}</span>@endif
                </x-ui.td>
                <x-ui.td label="Unit">{{ $log->unit?->label ?? '—' }}</x-ui.td>
                <x-ui.td label="Purpose">{{ $log->purposeLabel() }}</x-ui.td>
                <x-ui.td label="Code"><span class="numeric">{{ $log->pass_code ?? '—' }}</span></x-ui.td>
                <x-ui.td label="In">{{ $log->entered_at?->format('j M, g:i A') ?? '—' }}</x-ui.td>
                <x-ui.td label="Out">
                    {{ $log->exited_at?->format('j M, g:i A') ?? '—' }}
                    @if ($log->durationMinutes() !== null)
                        <span class="block text-xs text-muted">{{ $log->durationMinutes() }} min</span>
                    @endif
                </x-ui.td>
                <x-ui.td label="Status"><x-ui.status :value="$log->status" /></x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $logs->links() }}</x-slot:footer>
    </x-ui.table>

    <x-ui.modal name="pre-approve" title="Expect a visitor">
        <form wire:submit="preApprove" class="space-y-4" id="pre-approve-form">
            <x-ui.input wire:model="visitorName" name="visitorName" label="Who is coming?" required />
            <x-ui.input wire:model="phone" name="phone" label="Their phone" type="tel" inputmode="numeric" />

            <x-ui.select wire:model="purpose" name="purpose" label="Purpose" required>
                @foreach (['guest' => 'Guest', 'delivery' => 'Delivery', 'cab' => 'Cab', 'service' => 'Service', 'vendor' => 'Vendor', 'courier' => 'Courier', 'other' => 'Other'] as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </x-ui.select>

            @if ($myUnits->count() > 1)
                <x-ui.select wire:model="unitId" name="unitId" label="Visiting your unit" required>
                    @foreach ($myUnits as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->label }}</option>
                    @endforeach
                </x-ui.select>
            @endif

            <x-ui.input wire:model="expectedAt" name="expectedAt" label="When?" type="datetime-local"
                hint="The gate will hold the approval for 12 hours from this time." required />
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'pre-approve')">Cancel</x-ui.button>
            <x-ui.button type="submit" form="pre-approve-form">Pre-approve</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
