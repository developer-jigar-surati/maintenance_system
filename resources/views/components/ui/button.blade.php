@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconTrailing' => null,
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition-colors '
        .'disabled:cursor-not-allowed disabled:opacity-50';

    $variants = [
        'primary' => 'accent-bg hover:opacity-90 shadow-[var(--shadow-card)]',
        'secondary' => 'border border-subtle surface-raised text-primary hover:surface-inset',
        'ghost' => 'text-secondary hover:surface-inset hover:text-primary',
        'danger' => 'bg-[var(--color-critical)] text-white hover:opacity-90',
        'positive' => 'bg-[var(--color-positive)] text-white hover:opacity-90',
    ];

    /*
     * Minimum heights rather than padding alone, so a target never depends on
     * how tall its label happens to render. WCAG 2.2 asks for 24px; the
     * default here is 44, which is the size a thumb actually hits. `sm` is
     * reserved for dense table rows, where 36px is still comfortably above
     * the requirement.
     */
    $sizes = [
        'sm' => 'min-h-9 px-3 py-1.5 text-xs',
        'md' => 'min-h-11 px-4 py-2.5 text-sm',
        'lg' => 'min-h-13 px-5 py-3 text-base',
        'icon' => 'size-11',
    ];

    $classes = trim($base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="size-4 shrink-0" />@endif
        {{ $slot }}
        @if ($iconTrailing)<x-ui.icon :name="$iconTrailing" class="size-4 shrink-0" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="size-4 shrink-0" />@endif
        {{ $slot }}
        @if ($iconTrailing)<x-ui.icon :name="$iconTrailing" class="size-4 shrink-0" />@endif
    </button>
@endif
