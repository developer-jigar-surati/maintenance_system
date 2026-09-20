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

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2.5 text-sm',
        'lg' => 'px-5 py-3 text-base',
        // Square icon-only sizes keep a 44px touch target on phones.
        'icon' => 'size-10',
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
