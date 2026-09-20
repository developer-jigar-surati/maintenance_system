@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'hint' => null,
    'error' => null,
    'icon' => null,
    'required' => false,
])

@php
    $id = $attributes->get('id') ?? $name ?? 'field-'.\Illuminate\Support\Str::random(6);
    $error ??= $name ? ($errors->first($name) ?: null) : null;
    $describedBy = collect([
        $hint ? $id.'-hint' : null,
        $error ? $id.'-error' : null,
    ])->filter()->implode(' ');
@endphp

<div class="w-full">
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium">
            {{ $label }}
            @if ($required)<span class="text-[var(--color-critical)]" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <div class="relative">
        @if ($icon)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted">
                <x-ui.icon :name="$icon" class="size-4" />
            </span>
        @endif

        <input
            type="{{ $type }}"
            id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($required) required aria-required="true" @endif
            @if ($error) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->merge([
                'class' => 'w-full rounded-xl border surface-raised px-3.5 py-2.5 text-sm text-primary '
                    .'placeholder:text-muted transition-colors '
                    .($icon ? 'pl-10 ' : '')
                    .($error ? 'border-[var(--color-critical)]' : 'border-subtle'),
            ]) }}
        >
    </div>

    @if ($hint && ! $error)
        <p id="{{ $id }}-hint" class="mt-1.5 text-xs text-muted">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="mt-1.5 text-xs font-medium text-[var(--color-critical)]">{{ $error }}</p>
    @endif
</div>
