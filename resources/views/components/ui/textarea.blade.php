@props(['label' => null, 'name' => null, 'hint' => null, 'error' => null, 'rows' => 4, 'required' => false])

@php
    $id = $attributes->get('id') ?? $name ?? 'field-'.\Illuminate\Support\Str::random(6);
    $error ??= $name ? ($errors->first($name) ?: null) : null;

    /*
     * Width belongs to the field, not to the control inside it. A caller
     * writing class="w-auto min-w-36" means the field should be that wide, so
     * sizing classes are moved out to the wrapper and everything else stays
     * on the control. Left on the control, the wrapper's own w-full still won
     * and the field took the whole row.
     */
    $classes = collect(explode(' ', (string) $attributes->get('class', '')))->filter();
    $sizing = $classes->filter(fn ($c) => preg_match('/^(?:[a-z]+:)?(?:w|min-w|max-w|flex|basis|grow|shrink|col-span)-/', $c));
    $rest = $classes->reject(fn ($c) => $sizing->contains($c));
@endphp

<div @class(['min-w-0', $sizing->isEmpty() ? 'w-full' : $sizing->implode(' ')])>
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
        {{ $attributes->except('class') }}
        @class([
            'w-full rounded-xl border surface-raised px-3.5 py-2.5 text-sm text-primary',
            'placeholder:text-muted transition-colors',
            $error ? 'border-[var(--color-critical)]' : 'border-subtle',
            $rest->implode(' '),
        ])
    >{{ $slot }}</textarea>

    @if ($hint && ! $error)<p class="mt-1.5 text-xs text-muted">{{ $hint }}</p>@endif
    @if ($error)<p id="{{ $id }}-error" class="mt-1.5 text-xs font-medium text-[var(--color-critical)]">{{ $error }}</p>@endif
</div>
