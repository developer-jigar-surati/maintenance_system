@props(['label' => null, 'name' => null, 'hint' => null, 'error' => null, 'rows' => 4, 'required' => false])

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

    <textarea
        id="{{ $id }}"
        rows="{{ $rows }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($required) required aria-required="true" @endif
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->merge([
            'class' => 'w-full rounded-xl border surface-raised px-3.5 py-2.5 text-sm text-primary '
                .'placeholder:text-muted '.($error ? 'border-[var(--color-critical)]' : 'border-subtle'),
        ]) }}
    >{{ $slot }}</textarea>

    @if ($hint && ! $error)<p class="mt-1.5 text-xs text-muted">{{ $hint }}</p>@endif
    @if ($error)<p id="{{ $id }}-error" class="mt-1.5 text-xs font-medium text-[var(--color-critical)]">{{ $error }}</p>@endif
</div>
