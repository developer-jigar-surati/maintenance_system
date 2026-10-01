@props([
    'label',
    'value',
    'hint' => null,
    'icon' => null,
    'tone' => 'neutral',
    'trend' => null,
    'href' => null,
])

@php
    $tones = [
        'neutral' => 'surface-inset text-secondary',
        'accent' => 'accent-soft-bg accent-text',
        'positive' => 'bg-[var(--color-positive-soft)] text-[var(--color-positive)]',
        'caution' => 'bg-[var(--color-caution-soft)] text-[var(--color-caution)]',
        'critical' => 'bg-[var(--color-critical-soft)] text-[var(--color-critical)]',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'surface-card block min-w-0 p-4 sm:p-5 '.($href ? 'transition-colors hover:surface-sunken' : '')]) }}
>
    <div class="flex items-start justify-between gap-3">
        <p class="min-w-0 truncate text-xs font-medium uppercase tracking-wide text-muted">{{ $label }}</p>
        @if ($icon)
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ $tones[$tone] ?? $tones['neutral'] }}">
                <x-ui.icon :name="$icon" class="size-4" />
            </span>
        @endif
    </div>

    <p class="numeric mt-2 truncate text-xl font-bold tracking-tight sm:text-2xl">{{ $value }}</p>

    @if ($hint || $trend !== null)
        <p class="mt-1 flex items-center gap-1.5 text-xs text-secondary">
            @if ($trend !== null)
                <span @class([
                    'font-semibold',
                    'text-[var(--color-positive)]' => $trend > 0,
                    'text-[var(--color-critical)]' => $trend < 0,
                ])>{{ $trend > 0 ? '+' : '' }}{{ $trend }}%</span>
            @endif
            {{ $hint }}
        </p>
    @endif
</{{ $tag }}>
