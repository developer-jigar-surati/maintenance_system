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
    /*
     * A field with no name borrows the property it is bound to for its id.
     *
     * It used to fall back to a random string, which changed on every single
     * re-render: the id under the label moved while somebody was typing in
     * the field, and a bound control inside a loop was never the same element
     * twice. Deriving it from the binding keeps it stable and unique.
     */
    $bound = collect($attributes->getAttributes())
        ->keys()
        ->first(fn ($key) => str_starts_with($key, 'wire:model'));

    $id = $attributes->get('id')
        ?? $name
        ?? ($bound ? 'field-'.\Illuminate\Support\Str::slug((string) $attributes->get($bound)) : 'field-'.\Illuminate\Support\Str::random(6));
    $error ??= $name ? ($errors->first($name) ?: null) : null;
    $describedBy = collect([
        $hint ? $id.'-hint' : null,
        $error ? $id.'-error' : null,
    ])->filter()->implode(' ');

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
            {{ $attributes->except('class') }}
            @class([
                'min-h-11 w-full rounded-xl border surface-raised px-3.5 py-2.5 text-sm text-primary',
                'placeholder:text-muted transition-colors',
                'pl-10' => $icon,
                $error ? 'border-[var(--color-critical)]' : 'border-subtle',
                $rest->implode(' '),
            ])
        >
    </div>

    @if ($hint && ! $error)
        <p id="{{ $id }}-hint" class="mt-1.5 text-xs text-muted">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="mt-1.5 text-xs font-medium text-[var(--color-critical)]">{{ $error }}</p>
    @endif
</div>
