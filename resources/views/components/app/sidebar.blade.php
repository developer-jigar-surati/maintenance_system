@php
    $sections = \App\Support\Navigation::sections();
    $society = auth()->user()?->currentSociety;
@endphp

<aside
    class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full border-r border-subtle surface-raised transition-transform duration-200 ease-out lg:translate-x-0"
    :class="sidebarOpen && 'translate-x-0'"
    aria-label="Main navigation"
>
    <div class="flex h-full flex-col">
        {{-- Society identity --}}
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-subtle px-5">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl accent-bg text-sm font-bold">
                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($society?->name ?? 'S', 0, 1)) }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold">{{ $society?->name ?? config('app.name') }}</span>
                <span class="block truncate text-xs text-muted">{{ $society?->typeLabel() }}</span>
            </span>
            <button
                type="button"
                @click="sidebarOpen = false"
                class="flex size-11 items-center justify-center rounded-lg text-secondary hover:surface-inset lg:hidden"
            >
                <x-ui.icon name="close" class="size-5" />
                <span class="sr-only">Close navigation</span>
            </button>
        </div>

        <nav class="scrollbar-slim flex-1 space-y-6 overflow-y-auto px-3 py-5">
            @foreach ($sections as $section)
                <div>
                    <h2 class="px-3 pb-2 text-[0.6875rem] font-semibold uppercase tracking-wider text-muted">
                        {{ $section['label'] }}
                    </h2>
                    <ul class="space-y-0.5">
                        @foreach ($section['items'] as $item)
                            <li>
                                <a
                                    href="{{ route($item['route']) }}"
                                    @if ($item['active']) aria-current="page" @endif
                                    class="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition-colors
                                        {{ $item['active']
                                            ? 'accent-soft-bg accent-text'
                                            : 'text-secondary hover:surface-inset hover:text-primary' }}"
                                >
                                    <x-ui.icon :name="$item['icon']" class="size-5 shrink-0" />
                                    <span class="truncate">{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        @if ($society?->isOnboarding())
            <div class="mx-3 mb-3 rounded-xl border border-[var(--color-caution)] bg-[var(--color-caution-soft)] p-3">
                <p class="text-xs font-semibold text-primary">Setup in progress</p>
                <p class="mt-1 text-xs text-secondary">Finish onboarding to start billing.</p>
                @if (\Illuminate\Support\Facades\Route::has('onboarding.index'))
                    <a href="{{ route('onboarding.index') }}" class="mt-2 inline-block text-xs font-semibold accent-text hover:underline">
                        Continue setup &rarr;
                    </a>
                @endif
            </div>
        @endif
    </div>
</aside>
