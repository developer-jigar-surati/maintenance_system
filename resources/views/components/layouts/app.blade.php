@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#1e1b33" media="(prefers-color-scheme: dark)">

    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>

    {{--
        Applied before the stylesheet so the correct theme is painted on the
        first frame. Without this the page flashes light before switching.
    --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('theme-preference') || 'system';
                var dark = stored === 'dark' || (stored === 'system' &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen surface text-primary antialiased">
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
        {{-- Mobile scrim --}}
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-black/50 lg:hidden"
            aria-hidden="true"
            style="display: none"
        ></div>

        <x-app.sidebar />

        <div class="lg:pl-72">
            <x-app.topbar />

            <main id="main-content" tabindex="-1" class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                @if (session('status'))
                    <x-ui.alert tone="positive" class="mb-6">{{ session('status') }}</x-ui.alert>
                @endif

                @if (session('error'))
                    <x-ui.alert tone="critical" class="mb-6">{{ session('error') }}</x-ui.alert>
                @endif

                {{ $slot }}
            </main>
        </div>

        {{-- Bottom navigation: thumb-reachable shortcuts on phones. --}}
        <x-app.bottom-nav />
    </div>

    {{-- Help for the screen you are on, opened from the topbar. --}}
    <x-app.help-panel />

    {{-- Toasts are announced politely so screen readers hear them. --}}
    <div
        aria-live="polite"
        aria-atomic="true"
        class="pointer-events-none fixed inset-x-0 bottom-20 z-50 flex flex-col items-center gap-2 px-4 sm:bottom-6"
        x-data="{ messages: [] }"
        @notify.window="
            const id = Date.now();
            messages.push({ id, text: $event.detail.message, tone: $event.detail.tone || 'info' });
            setTimeout(() => messages = messages.filter(m => m.id !== id), 5000);
        "
    >
        <template x-for="message in messages" :key="message.id">
            <div
                x-transition
                class="pointer-events-auto max-w-sm rounded-xl border px-4 py-3 text-sm font-medium shadow-[var(--shadow-overlay)] surface-raised"
                :class="{
                    'border-[var(--color-positive)] text-[var(--color-positive)]': message.tone === 'positive',
                    'border-[var(--color-critical)] text-[var(--color-critical)]': message.tone === 'critical',
                    'border-subtle text-primary': message.tone === 'info',
                }"
                x-text="message.text"
            ></div>
        </template>
    </div>

    @livewireScripts
</body>
</html>
