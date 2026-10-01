{{--
    Toasts.

    A message about something that already happened, so it never takes focus
    and never blocks the page. It does have to be readable though, which the
    first version was not: no icon, no way to dismiss it early, and a fixed
    five second life that ran out mid-sentence on a long message.

    Timing is derived from the length of the message, and pauses whenever the
    pointer is over the stack or focus is inside it, so a toast cannot vanish
    while it is being read or while its action is being reached for.
--}}
<div
    x-data="toastStack()"
    x-on:notify.window="push($event.detail)"
    class="pointer-events-none fixed inset-x-0 bottom-20 z-[60] flex flex-col items-center gap-2 px-4 sm:bottom-6 sm:left-auto sm:right-6 sm:items-end sm:px-0"
>
    {{--
        Two regions, because a failure should interrupt a screen reader and a
        confirmation should wait its turn.
    --}}
    <div aria-live="polite" aria-atomic="false" class="sr-only" x-ref="politeRegion"></div>
    <div aria-live="assertive" aria-atomic="false" class="sr-only" x-ref="urgentRegion"></div>

    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="toast.visible"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-2 opacity-0 sm:translate-x-4 sm:translate-y-0"
            x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:mouseenter="hold(toast)"
            x-on:mouseleave="release(toast)"
            x-on:focusin="hold(toast)"
            x-on:focusout="release(toast)"
            class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-xl border surface-raised shadow-[var(--shadow-overlay)]"
            :class="{
                'border-[var(--color-positive)]': toast.tone === 'positive',
                'border-[var(--color-critical)]': toast.tone === 'critical',
                'border-[var(--color-caution)]': toast.tone === 'caution',
                'border-subtle': toast.tone === 'info',
            }"
            role="status"
            data-toast
        >
            <div class="flex items-start gap-3 p-3.5">
                <span
                    class="flex size-6 shrink-0 items-center justify-center rounded-full"
                    :class="{
                        'bg-[var(--color-positive-soft)] text-[var(--color-positive)]': toast.tone === 'positive',
                        'bg-[var(--color-critical-soft)] text-[var(--color-critical)]': toast.tone === 'critical',
                        'bg-[var(--color-caution-soft)] text-[var(--color-caution)]': toast.tone === 'caution',
                        'surface-inset text-secondary': toast.tone === 'info',
                    }"
                    aria-hidden="true"
                >
                    {{-- One glyph per tone, so meaning is not carried by colour
                         alone. Bound rather than templated: an <svg> cannot
                         hold a <template>. --}}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round" class="size-3.5">
                        <path :d="iconFor(toast.tone)" />
                    </svg>
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-primary" x-text="toast.message"></p>
                    <p x-show="toast.detail" class="mt-0.5 text-xs text-secondary" x-text="toast.detail"></p>
                </div>

                <button
                    type="button"
                    x-on:click="dismiss(toast)"
                    class="-m-1.5 flex size-11 shrink-0 items-center justify-center rounded-lg text-muted hover:surface-inset hover:text-primary"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" class="size-4" aria-hidden="true"><path d="M6 18L18 6M6 6l12 12" /></svg>
                    <span class="sr-only">Dismiss this message</span>
                </button>
            </div>

            {{-- How long is left, which stops a toast feeling like it vanished
                 for no reason. Decorative: the text above carries the meaning. --}}
            <div class="h-0.5 w-full surface-inset" aria-hidden="true">
                <div
                    class="h-full transition-[width] ease-linear"
                    :class="{
                        'bg-[var(--color-positive)]': toast.tone === 'positive',
                        'bg-[var(--color-critical)]': toast.tone === 'critical',
                        'bg-[var(--color-caution)]': toast.tone === 'caution',
                        'bg-[var(--accent)]': toast.tone === 'info',
                    }"
                    :style="`width: ${toast.remaining}%; transition-duration: ${toast.paused ? '0ms' : '120ms'}`"
                ></div>
            </div>
        </div>
    </template>
</div>
