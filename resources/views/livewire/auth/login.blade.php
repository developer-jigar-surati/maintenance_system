<div>
    <h1 class="text-2xl font-bold tracking-tight">Welcome back</h1>
    <p class="mt-2 text-sm text-secondary">Sign in to your society account.</p>

    @if (session('status'))
        <x-ui.alert tone="positive" class="mt-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form data-validate wire:submit="login" class="mt-8 space-y-5">
        <x-ui.input
            wire:model="identifier"
            name="identifier"
            label="Email or mobile number"
            type="text"
            icon="users"
            autocomplete="username"
            required
            autofocus
        />

        <div>
            <x-ui.input
                wire:model="password"
                name="password"
                label="Password"
                type="password"
                autocomplete="current-password"
                required
            />
            <div class="mt-2 text-right">
                <a href="{{ route('password.request') }}" wire:navigate class="text-xs font-medium accent-text hover:underline">
                    Forgot your password?
                </a>
            </div>
        </div>

        <label class="flex items-center gap-2.5 text-sm">
            <input
                type="checkbox"
                wire:model="remember"
                class="size-4 rounded border-strong accent-[var(--accent)]"
            >
            <span>Keep me signed in</span>
        </label>

        <x-ui.button type="submit" class="w-full" size="lg">
            <span wire:loading.remove wire:target="login">Sign in</span>
            <span wire:loading wire:target="login">Signing in&hellip;</span>
        </x-ui.button>
    </form>
</div>
