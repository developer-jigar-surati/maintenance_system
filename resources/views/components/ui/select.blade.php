@props([
    'label' => null,
    'name' => null,
    'options' => [],
    'placeholder' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
])

@php
    $id = $attributes->get('id') ?? $name ?? 'field-'.\Illuminate\Support\Str::random(6);
    $error ??= $name ? ($errors->first($name) ?: null) : null;
@endphp

<div class="w-full">
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium">
            {{ $label }}
            @if ($required)<span class="text-[var(--color-critical)]" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <div class="relative">
        <select
            id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($required) required aria-required="true" @endif
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->merge([
                'class' => 'w-full appearance-none rounded-xl border surface-raised py-2.5 pl-3.5 pr-10 text-sm '
                    .'text-primary transition-colors '
                    .($error ? 'border-[var(--color-critical)]' : 'border-subtle'),
            ]) }}
        >
            @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif

            @if (trim($slot) !== '')
                {{ $slot }}
            @else
                @foreach ($options as $value => $text)
                    <option value="{{ $value }}">{{ $text }}</option>
                @endforeach
            @endif
        </select>
        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-muted">
            <x-ui.icon name="chevron-down" class="size-4" />
        </span>
    </div>

    @if ($hint && ! $error)<p class="mt-1.5 text-xs text-muted">{{ $hint }}</p>@endif
    @if ($error)<p id="{{ $id }}-error" class="mt-1.5 text-xs font-medium text-[var(--color-critical)]">{{ $error }}</p>@endif
</div>
