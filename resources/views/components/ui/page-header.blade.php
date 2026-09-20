@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-end justify-between gap-4']) }}>
    <div class="min-w-0">
        <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-secondary">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
