<div>
    <x-ui.page-header title="Society settings" :description="$society->name" />

    <div class="space-y-6">
        {{-- Payments first: it is the setting that changes what residents can do. --}}
        <x-ui.card title="Collecting payments"
            description="A society collects offline until a gateway is configured and verified.">
            <x-slot:actions>
                <x-ui.button size="sm" x-on:click="$dispatch('open-modal', 'gateway')" icon="plus">
                    Add a gateway
                </x-ui.button>
            </x-slot:actions>

            <div class="rounded-xl surface-inset p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold">
                            Currently:
                            {{ match ($society->payment_mode) {
                                'offline' => 'offline only',
                                'online' => 'online only',
                                default => 'online and offline',
                            } }}
                        </p>
                        <p class="mt-0.5 text-sm text-secondary">
                            @if ($acceptsOnline)
                                Residents can pay through the gateway, and the committee can still record cash and cheques.
                            @else
                                Residents pay the treasurer directly; payments are keyed in and approved here.
                            @endif
                        </p>
                    </div>
                    @if ($acceptsOnline)
                        <x-ui.badge tone="positive" dot>Online enabled</x-ui.badge>
                    @else
                        <x-ui.badge tone="caution" dot>Offline only</x-ui.badge>
                    @endif
                </div>
            </div>

            @if ($gateways->isNotEmpty())
                <ul class="mt-4 space-y-2">
                    @foreach ($gateways as $gateway)
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-subtle p-3">
                            <div>
                                <p class="text-sm font-semibold">
                                    {{ $gateway->providerLabel() }}
                                    <x-ui.badge :tone="$gateway->isLive() ? 'accent' : 'neutral'" class="ml-1">
                                        {{ ucfirst($gateway->environment) }}
                                    </x-ui.badge>
                                </p>
                                <p class="mt-0.5 text-xs text-muted">
                                    @if ($gateway->verified_at)
                                        Verified {{ $gateway->verified_at->diffForHumans() }}
                                    @else
                                        Not verified
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-ui.status :value="$gateway->is_active ? 'active' : 'inactive'" />
                                <x-ui.button size="sm" variant="secondary" wire:click="toggleGateway({{ $gateway->id }})">
                                    {{ $gateway->is_active ? 'Disable' : 'Enable' }}
                                </x-ui.button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form wire:submit="saveProfile" class="mt-5 border-t border-subtle pt-5">
                <x-ui.select wire:model="profile.payment_mode" name="profile.payment_mode" label="Payment mode"
                    hint="Online requires at least one active gateway above.">
                    <option value="offline">Offline only</option>
                    <option value="both">Online and offline</option>
                    <option value="online">Online only</option>
                </x-ui.select>
                <x-ui.button type="submit" size="sm" class="mt-3">Save payment mode</x-ui.button>
            </form>
        </x-ui.card>

        {{-- Profile --}}
        <x-ui.card title="Society details">
            <form wire:submit="saveProfile" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input wire:model="profile.name" name="profile.name" label="Name" required />

                    <x-ui.select wire:model="profile.type" name="profile.type" label="Type">
                        @foreach (['apartment' => 'Apartment complex', 'villa' => 'Villa project', 'row_house' => 'Row houses', 'bungalow' => 'Bungalows', 'gated_community' => 'Gated community', 'township' => 'Township', 'plotted_development' => 'Plotted development', 'builder_floor' => 'Builder floors', 'commercial_complex' => 'Commercial complex', 'office_park' => 'Office park', 'industrial_estate' => 'Industrial estate', 'mixed_use' => 'Mixed use', 'cooperative_housing' => 'Co-operative housing society', 'student_housing' => 'Student housing', 'co_living' => 'Co-living', 'other' => 'Other'] as $v => $l)
                            <option value="{{ $v }}">{{ $l }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input wire:model="profile.registration_number" name="profile.registration_number" label="Registration number" />
                    <x-ui.input wire:model="profile.gstin" name="profile.gstin" label="GSTIN" class="uppercase" />
                    <x-ui.input wire:model="profile.contact_email" name="profile.contact_email" label="Contact email" type="email" />
                    <x-ui.input wire:model="profile.contact_phone" name="profile.contact_phone" label="Contact phone" type="tel" />
                </div>

                <x-ui.input wire:model="profile.address_line1" name="profile.address_line1" label="Address" />

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.input wire:model="profile.city" name="profile.city" label="City" />
                    <x-ui.input wire:model="profile.state" name="profile.state" label="State" />
                    <x-ui.input wire:model="profile.postal_code" name="profile.postal_code" label="PIN code" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model="profile.area_unit" name="profile.area_unit" label="Area unit"
                        hint="Used for per-area billing.">
                        <option value="sqft">Square feet</option>
                        <option value="sqm">Square metres</option>
                        <option value="sqyd">Square yards</option>
                    </x-ui.select>

                    <x-ui.select wire:model="profile.financial_year_start_month"
                        name="profile.financial_year_start_month" label="Financial year starts">
                        @foreach (range(1, 12) as $month)
                            <option value="{{ $month }}">{{ \Illuminate\Support\Carbon::create(null, $month)->format('F') }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <label class="flex items-center gap-2.5 text-sm">
                    <input type="checkbox" wire:model="profile.gst_enabled" class="size-4 rounded border-strong accent-[var(--accent)]">
                    <span>Charge and report GST on invoices</span>
                </label>

                <x-ui.button type="submit">
                    <span wire:loading.remove wire:target="saveProfile">Save details</span>
                    <span wire:loading wire:target="saveProfile">Saving&hellip;</span>
                </x-ui.button>
            </form>
        </x-ui.card>

        {{-- Preferences --}}
        <x-ui.card title="Preferences">
            <form wire:submit="savePreferences" class="space-y-4">
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" wire:model="preferences.offline_requires_approval"
                        class="mt-0.5 size-4 rounded border-strong accent-[var(--accent)]">
                    <span>
                        <span class="font-medium">Offline payments need approval</span>
                        <span class="block text-xs text-secondary">
                            A second committee member confirms the money was actually received before the receipt is issued.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" wire:model="preferences.show_phone_to_residents"
                        class="mt-0.5 size-4 rounded border-strong accent-[var(--accent)]">
                    <span>
                        <span class="font-medium">Show phone numbers in the directory</span>
                        <span class="block text-xs text-secondary">
                            When off, only committee members can see contact numbers.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" wire:model="preferences.require_visitor_approval"
                        class="mt-0.5 size-4 rounded border-strong accent-[var(--accent)]">
                    <span>
                        <span class="font-medium">Visitors need resident approval</span>
                        <span class="block text-xs text-secondary">
                            Walk-in visitors wait at the gate until the resident allows them in.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" wire:model="preferences.auto_escalate"
                        class="mt-0.5 size-4 rounded border-strong accent-[var(--accent)]">
                    <span>
                        <span class="font-medium">Escalate tickets that miss their SLA</span>
                        <span class="block text-xs text-secondary">Raises the escalation level so the committee sees it.</span>
                    </span>
                </label>

                <div class="max-w-48">
                    <x-ui.input wire:model="preferences.escalate_after_hours" name="preferences.escalate_after_hours"
                        label="Re-escalate after (hours)" type="number" min="1" />
                </div>

                <x-ui.button type="submit">Save preferences</x-ui.button>
            </form>
        </x-ui.card>
    </div>

    <x-ui.modal name="gateway" title="Add a payment gateway">
        <form wire:submit="saveGateway" class="space-y-4" id="gateway-form">
            <x-ui.alert tone="info">
                Keys are encrypted before they are stored, and verified with the provider
                before online payment is switched on.
            </x-ui.alert>

            <x-ui.select wire:model="provider" name="provider" label="Provider">
                <option value="razorpay">Razorpay</option>
            </x-ui.select>

            <x-ui.select wire:model="environment" name="environment" label="Environment">
                <option value="test">Test</option>
                <option value="live">Live</option>
            </x-ui.select>

            <x-ui.input wire:model="keyId" name="keyId" label="Key ID" required />
            <x-ui.input wire:model="keySecret" name="keySecret" label="Key secret" type="password" required />
            <x-ui.input wire:model="webhookSecret" name="webhookSecret" label="Webhook secret" type="password"
                hint="Optional, but needed to confirm payments when a resident closes the tab early." />
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'gateway')">Cancel</x-ui.button>
            <x-ui.button type="submit" form="gateway-form">
                <span wire:loading.remove wire:target="saveGateway">Save &amp; verify</span>
                <span wire:loading wire:target="saveGateway">Verifying&hellip;</span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
