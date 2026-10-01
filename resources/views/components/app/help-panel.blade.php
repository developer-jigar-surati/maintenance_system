@php
    $guide = \App\Support\ModuleGuide::forCurrentRoute();
@endphp

@if ($guide)
    {{--
        Help for the screen you are on, one tap away and never in the way.

        A slide-over rather than a modal: reading an explanation usually means
        glancing back at the thing being explained, and a panel down one side
        leaves it visible. Focus is trapped while open; Escape and the
        backdrop both close it.

        Lives at the end of the layout rather than in the topbar, because a
        `backdrop-filter` ancestor becomes the containing block for `fixed`
        descendants.
    --}}
    <div
        x-data="{ open: false }"
        x-on:open-help.window="open = true; $nextTick(() => $refs.panel?.focus())"
        x-on:keydown.escape.window="open = false"
        x-show="open"
        class="fixed inset-0 z-50"
        style="display: none"
    >
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50"
            x-on:click="open = false" aria-hidden="true"></div>

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            x-trap.noscroll="open"
            x-ref="panel"
            tabindex="-1"
            role="dialog"
            aria-modal="true"
            aria-labelledby="help-title"
            class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col surface-raised shadow-[var(--shadow-overlay)]"
        >
            <div class="flex items-start justify-between gap-3 border-b border-subtle px-5 py-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted">How this works</p>
                    <h2 id="help-title" class="truncate text-lg font-bold">{{ $guide['title'] }}</h2>
                </div>
                <button
                    type="button"
                    x-on:click="open = false"
                    class="flex size-11 shrink-0 items-center justify-center rounded-lg text-secondary hover:surface-inset"
                >
                    <x-ui.icon name="close" class="size-5" />
                    <span class="sr-only">Close help</span>
                </button>
            </div>

            <div class="flex-1 space-y-6 overflow-y-auto scrollbar-slim px-5 py-5">
                <p class="text-sm leading-relaxed text-secondary">{{ $guide['summary'] }}</p>

                @if ($guide['steps'] !== [])
                    <section>
                        <h3 class="mb-3 text-sm font-semibold">How it works</h3>
                        <ol class="space-y-3">
                            @foreach ($guide['steps'] as $i => $step)
                                <li class="flex gap-3">
                                    <span class="numeric flex size-6 shrink-0 items-center justify-center rounded-full accent-soft-bg text-xs font-bold accent-text"
                                        aria-hidden="true">{{ $i + 1 }}</span>
                                    <span class="text-sm leading-relaxed text-secondary">{{ $step }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                @if ($guide['watch'] !== [])
                    <section>
                        <h3 class="mb-3 text-sm font-semibold">Worth knowing</h3>
                        <ul class="space-y-3">
                            @foreach ($guide['watch'] as $note)
                                <li class="flex gap-3 rounded-xl bg-[var(--color-caution-soft)] p-3">
                                    <x-ui.icon name="alert" class="size-4 shrink-0 text-[var(--color-caution)]" />
                                    <span class="text-sm leading-relaxed text-[var(--color-caution)]">{{ $note }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        </div>
    </div>
@endif
