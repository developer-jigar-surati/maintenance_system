@props(['name', 'title' => null, 'maxWidth' => 'lg'])

@php
    $widths = ['sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-lg', 'xl' => 'max-w-xl', '2xl' => 'max-w-2xl'];
@endphp

{{--
    Focus is trapped while open and the page behind is inert to scrolling, so
    keyboard users cannot tab out into hidden content.
--}}
<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') { open = true; $nextTick(() => $refs.panel?.focus()) }"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-bind:class="open || 'hidden'"
    class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-label="{{ $title }}" @endif
    style="display: none"
>
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" x-on:click="open = false"></div>

    <div
        x-show="open"
        x-transition
        x-trap.noscroll="open"
        x-ref="panel"
        tabindex="-1"
        class="relative w-full {{ $widths[$maxWidth] ?? $widths['lg'] }} rounded-t-2xl surface-raised shadow-[var(--shadow-overlay)] sm:rounded-2xl"
    >
        @if ($title)
            <div class="flex items-center justify-between gap-3 border-b border-subtle px-5 py-4">
                <h2 class="text-base font-semibold">{{ $title }}</h2>
                <button type="button" x-on:click="open = false" class="flex size-11 items-center justify-center rounded-lg text-secondary hover:surface-inset">
                    <x-ui.icon name="close" class="size-5" />
                    <span class="sr-only">Close</span>
                </button>
            </div>
        @endif

        <div class="max-h-[70vh] overflow-y-auto p-5">{{ $slot }}</div>

        @isset($footer)
            <div class="flex flex-wrap justify-end gap-2 border-t border-subtle px-5 py-4">{{ $footer }}</div>
        @endisset
    </div>
</div>
