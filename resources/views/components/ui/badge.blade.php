@props(['tone' => 'neutral', 'dot' => false])

@php
    /* Status colours carry a text label as well as a hue, so meaning never
       depends on colour alone. */
    $tones = [
        'neutral' => 'surface-inset text-secondary',
        'accent' => 'accent-soft-bg accent-text',
        'positive' => 'bg-[var(--color-positive-soft)] text-[var(--color-positive)]',
        'caution' => 'bg-[var(--color-caution-soft)] text-[var(--color-caution)]',
        'critical' => 'bg-[var(--color-critical-soft)] text-[var(--color-critical)]',
        'info' => 'bg-[var(--color-info-soft)] text-[var(--color-info)]',
    ];
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 '
        .'text-xs font-semibold '.($tones[$tone] ?? $tones['neutral']),
]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
