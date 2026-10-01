@props(['tone' => 'info', 'title' => null])

@php
    $tones = [
        'info' => ['bg-[var(--color-info-soft)] text-[var(--color-info)]', 'info'],
        'positive' => ['bg-[var(--color-positive-soft)] text-[var(--color-positive)]', 'check'],
        'caution' => ['bg-[var(--color-caution-soft)] text-[var(--color-caution)]', 'alert'],
        'critical' => ['bg-[var(--color-critical-soft)] text-[var(--color-critical)]', 'alert'],
    ];
    [$classes, $icon] = $tones[$tone] ?? $tones['info'];
@endphp

<div
    role="{{ in_array($tone, ['critical', 'caution'], true) ? 'alert' : 'status' }}"
    {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl px-4 py-3 text-sm '.$classes]) }}
>
    <x-ui.icon :name="$icon" class="mt-0.5 size-5 shrink-0" />
    <div class="min-w-0 flex-1">
        @if ($title)<p class="font-semibold">{{ $title }}</p>@endif
        <div class="{{ $title ? 'mt-0.5' : '' }}">{{ $slot }}</div>
    </div>
</div>
