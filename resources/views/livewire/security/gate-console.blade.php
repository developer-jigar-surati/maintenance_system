<div>
    <x-ui.page-header title="Gate" description="Check visitors in and out.">
        <x-slot:actions>
            <x-ui.button x-on:click="$dispatch('open-modal', 'walk-in')" icon="plus">Log a walk-in</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Code lookup: the fastest path at the gate. --}}
    <x-ui.card title="Check a visitor code" class="mb-6">
        <form wire:submit="lookup" class="flex flex-wrap items-end gap-3">
            <div class="min-w-48 flex-1">
                <x-ui.input wire:model="passCode" name="passCode" label="Visitor code"
                    placeholder="6-character code" class="numeric text-lg uppercase tracking-widest"
                    autocomplete="off" />
            </div>
            <x-ui.button type="submit" size="lg" icon="search">Look up</x-ui.button>
        </form>

        @if ($found)
            <div class="mt-4 rounded-xl border border-[var(--color-positive)] bg-[var(--color-positive-soft)] p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-base font-bold">{{ $found->visitor_name }}</p>
                        <p class="text-sm">
                            {{ $found->purposeLabel() }} · visiting {{ $found->unit?->label ?? '—' }}
                            @if ($found->vehicle_number) · {{ $found->vehicle_number }} @endif
                        </p>
                        @if ($found->expected_at)
                            <p class="text-xs">Expected {{ $found->expected_at->format('j M, g:i A') }}</p>
                        @endif
                    </div>
                    <x-ui.button variant="positive" wire:click="checkIn({{ $found->id }})" icon="check">
                        Check in
                    </x-ui.button>
                </div>
            </div>
        @endif
    </x-ui.card>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Currently inside --}}
        <x-ui.card :title="'Inside now ('.$inside->count().')'" padded="false">
            @if ($inside->isEmpty())
                <x-ui.empty-state icon="shield" title="Nobody is inside" />
            @else
                <ul class="divide-y divide-[var(--border-subtle)]">
                    @foreach ($inside as $log)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ $log->visitor_name }}</p>
                                <p class="truncate text-xs text-muted">
                                    {{ $log->unit?->label ?? '—' }} · {{ $log->purposeLabel() }}
                                    · in {{ $log->entered_at?->diffForHumans(short: true) }}
                                </p>
                            </div>
                            <x-ui.button size="sm" variant="secondary" wire:click="checkOut({{ $log->id }})">
                                Check out
                            </x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        {{-- Expected today --}}
        <x-ui.card title="Expected" padded="false">
            @if ($expected->isEmpty())
                <x-ui.empty-state icon="user-plus" title="No pre-approved visitors" />
            @else
                <ul class="divide-y divide-[var(--border-subtle)]">
                    @foreach ($expected as $log)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ $log->visitor_name }}</p>
                                <p class="truncate text-xs text-muted">
                                    {{ $log->unit?->label ?? '—' }} ·
                                    code <span class="numeric font-semibold">{{ $log->pass_code }}</span>
                                    @if ($log->expected_at) · {{ $log->expected_at->format('j M, g:i A') }} @endif
                                </p>
                            </div>
                            <x-ui.button size="sm" variant="positive" wire:click="checkIn({{ $log->id }})">In</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    @if ($awaitingApproval->isNotEmpty())
        <x-ui.card title="Waiting on a resident" class="mt-6" padded="false">
            <ul class="divide-y divide-[var(--border-subtle)]">
                @foreach ($awaitingApproval as $log)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $log->visitor_name }}</p>
                            <p class="truncate text-xs text-muted">
                                {{ $log->unit?->label ?? '—' }} · {{ $log->purposeLabel() }}
                                · {{ $log->created_at->diffForHumans(short: true) }}
                            </p>
                        </div>
                        <x-ui.badge tone="caution" dot>Awaiting approval</x-ui.badge>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <x-ui.modal name="walk-in" title="Log a walk-in">
        <form wire:submit="logWalkIn" class="space-y-4" id="walk-in-form">
            <x-ui.input wire:model="visitorName" name="visitorName" label="Visitor name" required autofocus />
            <x-ui.input wire:model="phone" name="phone" label="Phone" type="tel" inputmode="numeric" />

            <x-ui.select wire:model="purpose" name="purpose" label="Purpose" required>
                @foreach (['guest' => 'Guest', 'delivery' => 'Delivery', 'cab' => 'Cab', 'service' => 'Service', 'vendor' => 'Vendor', 'courier' => 'Courier', 'staff' => 'Staff', 'other' => 'Other'] as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select wire:model="unitId" name="unitId" label="Visiting" placeholder="Select a unit">
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->label }}</option>
                @endforeach
            </x-ui.select>

            <div class="grid grid-cols-2 gap-3">
                <x-ui.input wire:model="vehicleNumber" name="vehicleNumber" label="Vehicle" class="uppercase" />
                <x-ui.input wire:model="accompanying" name="accompanying" label="Others with them" type="number" min="0" />
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'walk-in')">Cancel</x-ui.button>
            <x-ui.button type="submit" form="walk-in-form">Log visitor</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
