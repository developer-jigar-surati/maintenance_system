@php
    $user = auth()->user();
    $societies = $user?->activeSocieties()->get() ?? collect();
@endphp

<header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-subtle surface-raised/95 px-4 backdrop-blur sm:px-6 lg:px-8">
    <button
        type="button"
        @click="sidebarOpen = true"
        class="-ml-1 rounded-lg p-2 text-secondary hover:surface-inset lg:hidden"
    >
        <x-ui.icon name="menu" />
        <span class="sr-only">Open navigation</span>
    </button>

    <div class="min-w-0 flex-1">
        @isset($heading)
            <h1 class="truncate text-base font-semibold sm:text-lg">{{ $heading }}</h1>
        @endisset
    </div>

    {{-- Society switcher, shown only when the user belongs to more than one. --}}
    @if ($societies->count() > 1)
        <div x-data="{ open: false }" class="relative">
            <button
                type="button"
                @click="open = !open"
                :aria-expanded="open"
                aria-haspopup="menu"
                class="flex items-center gap-2 rounded-xl border border-subtle px-3 py-2 text-sm font-medium hover:surface-inset"
            >
                <x-ui.icon name="switch" class="size-4" />
                <span class="hidden max-w-32 truncate sm:inline">{{ $user->currentSociety?->name }}</span>
                <x-ui.icon name="chevron-down" class="size-4" />
            </button>
            <div
                x-show="open"
                x-transition
                @click.outside="open = false"
                role="menu"
                class="absolute right-0 mt-2 w-64 rounded-xl border border-subtle surface-raised p-1.5 shadow-[var(--shadow-overlay)]"
                style="display: none"
            >
                @foreach ($societies as $option)
                    <form method="POST" action="{{ route('societies.switch', $option) }}">
                        @csrf
                        <button
                            type="submit"
                            role="menuitem"
                            class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm hover:surface-inset"
                        >
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{ $option->name }}</span>
                                <span class="block truncate text-xs text-muted">{{ $option->typeLabel() }}</span>
                            </span>
                            @if ($option->id === $user->current_society_id)
                                <x-ui.icon name="check" class="size-4 shrink-0 accent-text" />
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    @endif

    <x-app.theme-toggle />

    {{-- Account menu --}}
    <div x-data="{ open: false }" class="relative">
        <button
            type="button"
            @click="open = !open"
            :aria-expanded="open"
            aria-haspopup="menu"
            class="flex items-center gap-2 rounded-full p-0.5 hover:surface-inset"
        >
            <span class="flex size-9 items-center justify-center rounded-full accent-soft-bg text-xs font-bold accent-text">
                {{ $user?->initials() }}
            </span>
            <span class="sr-only">Account menu for {{ $user?->name }}</span>
        </button>
        <div
            x-show="open"
            x-transition
            @click.outside="open = false"
            role="menu"
            class="absolute right-0 mt-2 w-56 rounded-xl border border-subtle surface-raised p-1.5 shadow-[var(--shadow-overlay)]"
            style="display: none"
        >
            <div class="border-b border-subtle px-3 py-2">
                <p class="truncate text-sm font-semibold">{{ $user?->name }}</p>
                <p class="truncate text-xs text-muted">{{ $user?->email }}</p>
            </div>
            @if (\Illuminate\Support\Facades\Route::has('profile.edit'))
                <a href="{{ route('profile.edit') }}" role="menuitem" class="block rounded-lg px-3 py-2 text-sm hover:surface-inset">
                    Your profile
                </a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    role="menuitem"
                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-[var(--color-critical)] hover:surface-inset"
                >
                    <x-ui.icon name="logout" class="size-4" />
                    Sign out
                </button>
            </form>
        </div>
    </div>
</header>
