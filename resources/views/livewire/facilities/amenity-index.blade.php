<div>
    <x-ui.page-header title="Amenities" description="Book the clubhouse, hall, courts and guest rooms." />

    @if ($canManage && $pendingBookings->isNotEmpty())
        <x-ui.card title="Awaiting your approval" class="mb-6" padded="false">
            <ul class="divide-y divide-[var(--border-subtle)]">
                @foreach ($pendingBookings as $booking)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">
                                {{ $booking->amenity->name }} · {{ $booking->unit?->label }}
                            </p>
                            <p class="truncate text-xs text-muted">
                                {{ $booking->starts_at->format('D, j M · g:i A') }}
                                – {{ $booking->ends_at->format('g:i A') }}
                                @if ($booking->guests_count) · {{ $booking->guests_count }} guests @endif
                                · requested by {{ $booking->bookedBy?->name }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <x-ui.button size="sm" variant="positive" wire:click="approve({{ $booking->id }})">Approve</x-ui.button>
                            <x-ui.button size="sm" variant="ghost" wire:click="cancel({{ $booking->id }})">Decline</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    @if ($myBookings->isNotEmpty())
        <x-ui.card title="Your upcoming bookings" class="mb-6" padded="false">
            <ul class="divide-y divide-[var(--border-subtle)]">
                @foreach ($myBookings as $booking)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $booking->amenity->name }}</p>
                            <p class="truncate text-xs text-muted">
                                {{ $booking->booking_number }} ·
                                {{ $booking->starts_at->format('D, j M · g:i A') }} – {{ $booking->ends_at->format('g:i A') }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <x-ui.status :value="$booking->status" />
                            @if ($booking->isCancellable())
                                <x-ui.button size="sm" variant="ghost" wire:click="cancel({{ $booking->id }})"
                                    wire:confirm="Cancel this booking?">Cancel</x-ui.button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    @if ($amenities->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="sparkles" title="No bookable amenities yet"
                description="Add the clubhouse, hall or courts so residents can reserve them." />
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($amenities as $amenity)
                <div class="surface-card flex flex-col p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-semibold">{{ $amenity->name }}</h2>
                            <p class="text-xs text-muted">{{ $amenity->typeLabel() }}</p>
                        </div>
                        @if ($amenity->charge_basis === 'free')
                            <x-ui.badge tone="positive">Free</x-ui.badge>
                        @else
                            <x-ui.badge tone="accent">
                                <x-ui.money :amount="$amenity->charge_amount" />
                                /{{ str_replace('per_', '', $amenity->charge_basis) }}
                            </x-ui.badge>
                        @endif
                    </div>

                    @if ($amenity->description)
                        <p class="mt-2 line-clamp-2 text-sm text-secondary">{{ $amenity->description }}</p>
                    @endif

                    <dl class="mt-3 space-y-1 text-xs text-secondary">
                        @if ($amenity->capacity)
                            <div class="flex justify-between"><dt>Capacity</dt><dd class="numeric">{{ $amenity->capacity }}</dd></div>
                        @endif
                        @if ($amenity->opens_at && $amenity->closes_at)
                            <div class="flex justify-between">
                                <dt>Hours</dt>
                                <dd class="numeric">
                                    {{ \Illuminate\Support\Carbon::parse($amenity->opens_at)->format('g:i A') }}
                                    – {{ \Illuminate\Support\Carbon::parse($amenity->closes_at)->format('g:i A') }}
                                </dd>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <dt>Approval</dt>
                            <dd>{{ $amenity->requires_approval ? 'Required' : 'Instant' }}</dd>
                        </div>
                    </dl>

                    @can(\App\Enums\Permission::AMENITY_BOOK)
                        <x-ui.button class="mt-4 w-full" size="sm" wire:click="startBooking({{ $amenity->id }})">
                            Book
                        </x-ui.button>
                    @endcan
                </div>
            @endforeach
        </div>
    @endif

    @can(\App\Enums\Permission::AMENITY_BOOK)
        <x-ui.modal name="book-amenity" :title="$selected ? 'Book '.$selected->name : 'Book an amenity'">
            @if ($bookingErrors !== [])
                <x-ui.alert tone="critical" title="This slot cannot be booked" class="mb-4">
                    <ul class="mt-1 list-inside list-disc space-y-0.5">
                        @foreach ($bookingErrors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            <form wire:submit="book" class="space-y-4" id="book-amenity-form">
                @if ($myUnits->count() > 1)
                    <x-ui.select wire:model="unitId" name="unitId" label="Booking for" required>
                        @foreach ($myUnits as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->label }}</option>
                        @endforeach
                    </x-ui.select>
                @endif

                <x-ui.input wire:model="date" name="date" label="Date" type="date"
                    :min="now()->toDateString()" required />

                <div class="grid grid-cols-2 gap-3">
                    <x-ui.input wire:model="startTime" name="startTime" label="From" type="time" required />
                    <x-ui.input wire:model="endTime" name="endTime" label="To" type="time" required />
                </div>

                <x-ui.input wire:model="guests" name="guests" label="Expected guests" type="number" min="0" />
                <x-ui.input wire:model="purpose" name="purpose" label="Purpose"
                    placeholder="e.g. Birthday celebration" />

                @if ($selected?->rules)
                    <div class="rounded-lg surface-inset p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted">House rules</p>
                        <p class="mt-1 selectable whitespace-pre-line text-xs text-secondary">{{ $selected->rules }}</p>
                    </div>
                @endif
            </form>

            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'book-amenity')">Cancel</x-ui.button>
                <x-ui.button type="submit" form="book-amenity-form">
                    <span wire:loading.remove wire:target="book">Confirm booking</span>
                    <span wire:loading wire:target="book">Checking&hellip;</span>
                </x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
