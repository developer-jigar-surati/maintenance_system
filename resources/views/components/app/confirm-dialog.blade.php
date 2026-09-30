{{--
    The confirmation dialog.

    One instance for the whole application, opened by the click interceptor in
    resources/js/ui/confirm.js whenever a control carries data-confirm.

    Cancel holds focus when it opens, so the dangerous button is never one
    stray Enter away, and focus returns to whatever was clicked afterwards.
--}}
<div
    x-data="confirmDialog()"
    x-on:confirm-request.window="show($event.detail)"
    x-on:keydown.escape.window="open && close()"
    x-show="open"
    class="fixed inset-0 z-[70] flex items-end justify-center p-0 sm:items-center sm:p-6"
    style="display: none"
>
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/60"
        x-on:click="close()" aria-hidden="true"></div>

    <div
        x-show="open"
        x-transition
        x-trap.noscroll="open"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="confirm-title"
        :aria-describedby="detail ? 'confirm-detail' : null"
        class="relative w-full max-w-md rounded-t-2xl surface-raised p-5 shadow-[var(--shadow-overlay)] sm:rounded-2xl"
    >
        <div class="flex items-start gap-4">
            <span
                class="flex size-11 shrink-0 items-center justify-center rounded-full"
                :class="tone === 'danger'
                    ? 'bg-[var(--color-critical-soft)] text-[var(--color-critical)]'
                    : 'bg-[var(--color-caution-soft)] text-[var(--color-caution)]'"
                aria-hidden="true"
            >
                <x-ui.icon name="alert" class="size-5" />
            </span>

            <div class="min-w-0 flex-1">
                <h2 id="confirm-title" class="text-base font-semibold" x-text="title"></h2>
                <p
                    id="confirm-detail"
                    x-show="detail"
                    class="mt-1.5 text-sm leading-relaxed text-secondary"
                    x-text="detail"
                ></p>
            </div>
        </div>

        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            {{-- Cancel first in the DOM and focused on open: the safe answer
                 should be the easy one. --}}
            <button
                type="button"
                x-ref="cancel"
                x-on:click="close()"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-subtle surface-raised px-4 py-2.5 text-sm font-semibold text-primary transition-colors hover:surface-inset"
                x-text="cancelLabel"
            ></button>

            <button
                type="button"
                x-on:click="confirm()"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:opacity-90"
                :class="tone === 'danger' ? 'bg-[var(--color-critical)]' : 'bg-[var(--color-caution)]'"
                x-text="confirmLabel"
            ></button>
        </div>
    </div>
</div>
