@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>

    <script>
        (function () {
            try {
                var stored = localStorage.getItem('theme-preference') || 'system';
                var dark = stored === 'dark' || (stored === 'system' &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen surface text-primary antialiased">
    <div class="grid min-h-screen lg:grid-cols-2">
        {{-- Brand panel: decorative, so it is hidden from assistive tech and
             dropped entirely on small screens where it would only push the
             form below the fold. --}}
        <div
            class="relative hidden flex-col justify-between overflow-hidden p-12 lg:flex"
            style="background: linear-gradient(140deg, var(--color-brand-700), var(--color-brand-950) 70%)"
            aria-hidden="true"
        >
            <div class="relative z-10 flex items-center gap-3 text-white">
                <span class="flex size-10 items-center justify-center rounded-xl bg-white/15 text-lg font-bold backdrop-blur">
                    {{ \Illuminate\Support\Str::substr(config('app.name'), 0, 1) }}
                </span>
                <span class="text-lg font-semibold">{{ config('app.name') }}</span>
            </div>

            <div class="relative z-10 max-w-md text-white">
                <p class="text-3xl font-bold leading-tight tracking-tight">
                    Every rupee accounted for.<br>Every decision on record.
                </p>
                <p class="mt-4 text-sm leading-relaxed text-white/70">
                    Maintenance billing, digital receipts, meetings and minutes, helpdesk
                    and gate management &mdash; for apartments, villas, townships and
                    commercial complexes alike.
                </p>
            </div>

            <div class="relative z-10 flex gap-8 text-white/70">
                @foreach ([['Automated', 'billing runs'], ['Digital', 'receipts'], ['Audited', 'books']] as [$big, $small])
                    <div>
                        <p class="text-sm font-semibold text-white">{{ $big }}</p>
                        <p class="text-xs">{{ $small }}</p>
                    </div>
                @endforeach
            </div>

            <div class="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full bg-white/5"></div>
            <div class="pointer-events-none absolute -bottom-32 -left-16 size-80 rounded-full bg-white/5"></div>
        </div>

        <main class="flex items-center justify-center px-5 py-12 sm:px-8">
            <div class="w-full max-w-sm">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <span class="flex size-10 items-center justify-center rounded-xl accent-bg text-lg font-bold">
                        {{ \Illuminate\Support\Str::substr(config('app.name'), 0, 1) }}
                    </span>
                    <span class="text-lg font-semibold">{{ config('app.name') }}</span>
                </div>

                {{ $slot }}
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
