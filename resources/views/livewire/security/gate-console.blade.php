{{--
    Sized for a phone held in one hand at a gate. Tap targets are at least
    64px tall, labels are large, and each step announces itself so the screen
    reader keeps pace with the guard.
--}}
<div class="mx-auto max-w-3xl">
    <x-ui.page-header title="Gate" :description="$society->name" />

    {{-- Announces every step change and confirmation. --}}
    <p class="sr-only" aria-live="polite">
        @if ($step === 'purpose') Step 1 of 3. Who is at the gate?
        @elseif ($step === 'who') Step 2 of 3. Which {{ $this->purposeLabel() }}?
        @elseif ($step === 'unit') Step 3 of 3. Which flat are they visiting?
        @elseif ($step === 'done' && $lastLogged) {{ $lastLogged->visitor_name }} logged.
        @endif
    </p>

    {{-- ---------------------------------------------------------------- --}}
    {{-- STEP 1 — who is at the gate                                      --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($step === 'purpose')
        <section aria-labelledby="gate-step-1">
            <h2 id="gate-step-1" class="mb-3 text-lg font-semibold">Who is at the gate?</h2>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ([
                    ['delivery', 'Delivery', 'truck'],
                    ['cab', 'Cab', 'car'],
                    ['guest', 'Guest', 'user-plus'],
                    ['service', 'Service', 'wrench'],
                    ['staff', 'Staff', 'identification'],
                    ['other', 'Someone else', 'users'],
                ] as [$value, $label, $icon])
                    <button
                        type="button"
                        wire:click="choosePurpose('{{ $value }}')"
                        class="flex min-h-28 flex-col items-center justify-center gap-2 rounded-2xl border-2 border-subtle surface-raised p-4 text-base font-semibold transition-colors hover:accent-soft-bg hover:border-[var(--accent)]"
                    >
                        <x-ui.icon :name="$icon" class="size-8 accent-text" />
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </section>

        {{-- Visitor code lookup, for anyone the resident already approved. --}}
        <section class="mt-6" aria-labelledby="gate-code">
            <h2 id="gate-code" class="mb-2 text-sm font-semibold text-secondary">Has a code?</h2>
            <form wire:submit="lookup" class="flex gap-2">
                <input
                    type="text"
                    wire:model="passCode"
                    inputmode="latin"
                    autocomplete="off"
                    placeholder="6-character code"
                    aria-label="Visitor code"
                    class="numeric min-h-14 w-full rounded-xl border-2 border-subtle surface-raised px-4 text-lg uppercase tracking-widest"
                >
                <x-ui.button type="submit" size="lg" icon="search" class="shrink-0">Find</x-ui.button>
            </form>

            @if ($found)
                <div class="mt-3 rounded-2xl border-2 border-[var(--color-positive)] bg-[var(--color-positive-soft)] p-4">
                    <p class="text-lg font-bold">{{ $found->visitor_name }}</p>
                    <p class="text-sm">
                        {{ $found->purposeLabel() }} &middot; {{ $found->unit?->label ?? 'No flat' }}
                    </p>
                    <x-ui.button
                        variant="positive" size="lg" icon="check" class="mt-3 w-full"
                        wire:click="allowIn({{ $found->id }})"
                    >Let them in</x-ui.button>
                </div>
            @endif
        </section>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- STEP 2 — which company, trade, or name                           --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($step === 'who')
        <section aria-labelledby="gate-step-2">
            <button type="button" wire:click="back"
                class="mb-3 -ml-2 inline-flex min-h-11 items-center gap-1 rounded-xl px-2 text-base font-medium text-secondary hover:surface-inset hover:text-primary">
                <x-ui.icon name="chevron-left" class="size-5" /> Back
            </button>

            <h2 id="gate-step-2" class="mb-3 text-lg font-semibold">
                @if ($this->hasPresetList()) Which one? @else Who is visiting? @endif
            </h2>

            @if ($this->hasPresetList())
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                    @foreach ($this->presetList() as $name)
                        <button
                            type="button"
                            wire:click="chooseWho(@js($name))"
                            class="min-h-16 rounded-xl border-2 border-subtle surface-raised px-3 text-base font-semibold transition-colors hover:accent-soft-bg hover:border-[var(--accent)]"
                        >{{ $name }}</button>
                    @endforeach
                </div>

                <p class="my-4 text-center text-sm text-muted">or type a name</p>
            @endif

            <form wire:submit="confirmName" class="flex gap-2">
                <input
                    type="text"
                    wire:model="visitorName"
                    autocomplete="off"
                    @if (! $this->hasPresetList()) autofocus @endif
                    placeholder="{{ $this->hasPresetList() ? 'Another company' : 'Visitor name' }}"
                    aria-label="Visitor name"
                    class="min-h-14 w-full rounded-xl border-2 border-subtle surface-raised px-4 text-lg"
                >
                <x-ui.button type="submit" size="lg" icon-trailing="chevron-right" class="shrink-0">Next</x-ui.button>
            </form>

            @error('visitorName')
                <p class="mt-2 text-sm font-medium text-[var(--color-critical)]">{{ $message }}</p>
            @enderror
        </section>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- STEP 3 — which flat                                              --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($step === 'unit')
        <section aria-labelledby="gate-step-3">
            <button type="button" wire:click="back"
                class="mb-3 -ml-2 inline-flex min-h-11 items-center gap-1 rounded-xl px-2 text-base font-medium text-secondary hover:surface-inset hover:text-primary">
                <x-ui.icon name="chevron-left" class="size-5" /> Back
            </button>

            <h2 id="gate-step-3" class="text-lg font-semibold">Which flat?</h2>
            <p class="mb-3 text-sm text-secondary">
                {{ $this->purposeLabel() }} &middot; {{ $visitorName ?: 'Visitor' }}
            </p>

            <input
                type="search"
                wire:model.live.debounce.200ms="unitSearch"
                inputmode="numeric"
                placeholder="Type a flat number"
                aria-label="Search flats"
                autofocus
                class="numeric mb-3 min-h-14 w-full rounded-xl border-2 border-subtle surface-raised px-4 text-lg"
            >

            @if ($units->isEmpty())
                <p class="py-6 text-center text-sm text-muted">No flat matches that.</p>
            @else
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-5" role="group" aria-label="Choose a flat">
                    @foreach ($units as $unit)
                        <button
                            type="button"
                            wire:click="chooseUnit({{ $unit->id }})"
                            class="numeric min-h-16 rounded-xl border-2 border-subtle surface-raised px-2 text-base font-bold transition-colors hover:accent-soft-bg hover:border-[var(--accent)]"
                        >{{ $unit->label }}</button>
                    @endforeach
                </div>
            @endif

            <x-ui.button variant="secondary" size="lg" class="mt-4 w-full" wire:click="logWithoutUnit">
                Left at the gate &mdash; no flat
            </x-ui.button>
        </section>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- DONE                                                             --}}
    {{-- ---------------------------------------------------------------- --}}
    @if ($step === 'done' && $lastLogged)
        @php $needsApproval = $lastLogged->status === 'pending_approval'; @endphp

        <section
            class="rounded-2xl border-2 p-5 {{ $needsApproval
                ? 'border-[var(--color-caution)] bg-[var(--color-caution-soft)]'
                : 'border-[var(--color-positive)] bg-[var(--color-positive-soft)]' }}"
            aria-labelledby="gate-done"
        >
            <div class="flex items-start gap-3">
                <x-ui.icon :name="$needsApproval ? 'clock' : 'check-badge'" class="mt-0.5 size-8 shrink-0" />
                <div class="min-w-0">
                    <h2 id="gate-done" class="text-xl font-bold">
                        {{ $needsApproval ? 'Waiting for the resident' : 'Let them in' }}
                    </h2>
                    <p class="mt-1 text-base">
                        {{ $lastLogged->visitor_name }} &middot; {{ $lastLogged->purposeLabel() }}
                        @if ($lastLogged->unit) &middot; {{ $lastLogged->unit->label }} @endif
                    </p>
                    <p class="mt-1 text-sm">
                        {{ $needsApproval
                            ? 'The flat has been asked to approve. They appear under "Waiting" below once they answer.'
                            : 'Entry approved. Tap below once they walk in.' }}
                    </p>
                </div>
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                @unless ($needsApproval)
                    <x-ui.button variant="positive" size="lg" icon="check"
                        wire:click="allowIn({{ $lastLogged->id }})">Checked in</x-ui.button>
                @endunless
                <x-ui.button variant="secondary" size="lg" icon="plus" wire:click="startOver">
                    Next visitor
                </x-ui.button>
            </div>
        </section>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- Live lists                                                       --}}
    {{-- ---------------------------------------------------------------- --}}
    <div class="mt-8 space-y-6">
        @if ($waiting->isNotEmpty())
            <x-ui.card :title="'Waiting on a flat ('.$waiting->count().')'" padded="false">
                <ul class="divide-y divide-[var(--border-subtle)]">
                    @foreach ($waiting as $log)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="text-base font-semibold">{{ $log->visitor_name }}</p>
                                <p class="text-sm text-muted">
                                    {{ $log->purposeLabel() }} &middot; {{ $log->unit?->label ?? '—' }}
                                    &middot; {{ $log->created_at->diffForHumans(short: true) }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <x-ui.button variant="positive" wire:click="allowIn({{ $log->id }})">Let in</x-ui.button>
                                <x-ui.button variant="secondary" wire:click="deny({{ $log->id }})">Turn away</x-ui.button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        @if ($expected->isNotEmpty())
            <x-ui.card :title="'Expected ('.$expected->count().')'" padded="false">
                <ul class="divide-y divide-[var(--border-subtle)]">
                    @foreach ($expected as $log)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="text-base font-semibold">{{ $log->visitor_name }}</p>
                                <p class="text-sm text-muted">
                                    {{ $log->unit?->label ?? '—' }}
                                    @if ($log->pass_code)
                                        &middot; code <span class="numeric font-bold">{{ $log->pass_code }}</span>
                                    @endif
                                </p>
                            </div>
                            <x-ui.button variant="positive" class="shrink-0" wire:click="allowIn({{ $log->id }})">
                                Let in
                            </x-ui.button>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        <x-ui.card :title="'Inside now ('.$inside->count().')'" padded="false">
            @if ($inside->isEmpty())
                <x-ui.empty-state icon="shield" title="Nobody is inside" />
            @else
                <ul class="divide-y divide-[var(--border-subtle)]">
                    @foreach ($inside as $log)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="text-base font-semibold">{{ $log->visitor_name }}</p>
                                <p class="text-sm text-muted">
                                    {{ $log->unit?->label ?? '—' }} &middot; {{ $log->purposeLabel() }}
                                    &middot; in {{ $log->entered_at?->diffForHumans(short: true) }}
                                </p>
                            </div>
                            <x-ui.button variant="secondary" class="shrink-0" wire:click="checkOut({{ $log->id }})">
                                Went out
                            </x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</div>
