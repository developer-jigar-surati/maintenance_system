<div>
    <x-ui.page-header title="All societies" description="Every community on this installation.">
        <x-slot:actions>
            <x-ui.button x-on:click="$dispatch('open-modal', 'new-society')" icon="plus">
                New society
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <x-ui.stat label="Societies" :value="number_format($totals['societies'])" icon="building" tone="accent" />
        <x-ui.stat label="Units" :value="number_format($totals['units'])" icon="home" />
        <x-ui.stat label="Still onboarding" :value="number_format($totals['onboarding'])" icon="clock"
            :tone="$totals['onboarding'] > 0 ? 'caution' : 'positive'" />
    </div>

    <x-ui.table
        :headers="['Society', 'Type', 'Location', ['label' => 'Units', 'align' => 'right'], ['label' => 'People', 'align' => 'right'], 'Collection', 'Status', '']"
        :is-empty="$societies->isEmpty()"
        empty="No societies match these filters"
        empty-icon="building"
        caption="Societies with type, size and status"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Name, code or city" aria-label="Search societies" />
            </div>

            <x-ui.select wire:model.live="type" aria-label="Filter by type" class="w-auto min-w-40">
                <option value="">All types</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select wire:model.live="status" aria-label="Filter by status" class="w-auto min-w-32">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="onboarding">Onboarding</option>
                <option value="suspended">Suspended</option>
                <option value="archived">Archived</option>
            </x-ui.select>

            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($societies as $society)
            <x-ui.tr>
                <x-ui.td label="Society" primary>
                    {{ $society->name }}
                    <span class="numeric block text-xs text-muted">{{ $society->code }}</span>
                </x-ui.td>
                <x-ui.td label="Type">{{ $society->typeLabel() }}</x-ui.td>
                <x-ui.td label="Location">
                    {{ collect([$society->city, $society->state])->filter()->implode(', ') ?: '—' }}
                </x-ui.td>
                <x-ui.td label="Units" align="right">
                    <span class="numeric">{{ number_format($society->units_count) }}</span>
                </x-ui.td>
                <x-ui.td label="People" align="right">
                    <span class="numeric">{{ number_format($society->users_count) }}</span>
                </x-ui.td>
                <x-ui.td label="Collection">
                    @if ($society->acceptsOnlinePayments())
                        <x-ui.badge tone="positive" dot>Online</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral" dot>Offline</x-ui.badge>
                    @endif
                </x-ui.td>
                <x-ui.td label="Status"><x-ui.status :value="$society->status" /></x-ui.td>
                <x-ui.td align="right">
                    <x-ui.button size="sm" variant="secondary" wire:click="open({{ $society->id }})">
                        {{ $society->isOnboarding() ? 'Set up' : 'Open' }}
                    </x-ui.button>
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $societies->links() }}</x-slot:footer>
    </x-ui.table>

    <x-ui.modal name="new-society" title="Create a society" max-width="xl">
        <form wire:submit="create" class="space-y-5" id="new-society-form">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-muted">The community</h3>
                <div class="mt-3 space-y-4">
                    <x-ui.input wire:model="name" name="name" label="Name"
                        placeholder="e.g. Shreeji Residency" required />

                    <x-ui.select wire:model="newType" name="newType" label="Type" required
                        hint="This decides the default unit type — flats, villas, plots, shops.">
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-ui.select>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input wire:model="city" name="city" label="City" />
                        <x-ui.input wire:model="state" name="state" label="State" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select wire:model="areaUnit" name="areaUnit" label="Area measured in">
                            <option value="sqft">Square feet</option>
                            <option value="sqm">Square metres</option>
                            <option value="sqyd">Square yards</option>
                        </x-ui.select>

                        <x-ui.select wire:model="financialYearStart" name="financialYearStart"
                            label="Financial year starts">
                            @foreach (range(1, 12) as $month)
                                <option value="{{ $month }}">
                                    {{ \Illuminate\Support\Carbon::create(null, $month)->format('F') }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <x-ui.select wire:model="paymentMode" name="paymentMode" label="How will they collect?"
                        hint="Online needs a gateway configured in the society's own settings before it works.">
                        <option value="offline">Offline only</option>
                        <option value="both">Online and offline</option>
                        <option value="online">Online only</option>
                    </x-ui.select>
                </div>
            </div>

            <div class="border-t border-subtle pt-5">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-muted">Its first administrator</h3>
                <p class="mt-1 text-xs text-secondary">
                    Whoever will run the society day to day. If this email already has an
                    account, it is reused rather than duplicated.
                </p>

                <div class="mt-3 space-y-4">
                    <x-ui.input wire:model="adminName" name="adminName" label="Name" required />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input wire:model="adminEmail" name="adminEmail" label="Email" type="email" required />
                        <x-ui.input wire:model="adminPhone" name="adminPhone" label="Mobile" type="tel" />
                    </div>
                    <x-ui.input wire:model="adminPassword" name="adminPassword" label="Password" type="password"
                        hint="Leave blank to set a random one; they can reset it from the sign-in page." />
                </div>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'new-society')">Cancel</x-ui.button>
            <x-ui.button type="submit" form="new-society-form">
                <span wire:loading.remove wire:target="create">Create society</span>
                <span wire:loading wire:target="create">Creating&hellip;</span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
