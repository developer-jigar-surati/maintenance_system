@props(['title' => null, 'description' => null, 'padded' => true])

<section {{ $attributes->merge(['class' => 'surface-card']) }}>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-subtle px-5 py-4">
            <div class="min-w-0">
                @if ($title)<h2 class="text-sm font-semibold">{{ $title }}</h2>@endif
                @if ($description)<p class="mt-0.5 text-xs text-secondary">{{ $description }}</p>@endif
            </div>
            @isset($actions)<div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>@endisset
        </div>
    @endif

    <div class="{{ $padded ? 'p-5' : '' }}">{{ $slot }}</div>
</section>
