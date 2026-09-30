@php
    $guide = \App\Support\ModuleGuide::forCurrentRoute();
@endphp

@if ($guide)
    {{--
        The button only. The panel itself lives in the layout: the topbar uses
        `backdrop-blur`, and a `backdrop-filter` ancestor becomes the
        containing block for `position: fixed` descendants, which would pin a
        full-height panel inside a 64px-tall header.
    --}}
    <button
        type="button"
        x-data
        x-on:click="$dispatch('open-help')"
        class="flex size-11 items-center justify-center rounded-full border border-subtle text-secondary hover:surface-inset hover:text-primary"
    >
        <x-ui.icon name="info" class="size-5" />
        <span class="sr-only">How {{ $guide['title'] }} works</span>
    </button>
@endif
