<div>
    <h1 class="text-2xl font-bold tracking-tight">Reset your password</h1>
    <p class="mt-2 text-sm text-secondary">
        Enter your email address and we will send you a link to choose a new one.
    </p>

    @if (session('status'))
        <x-ui.alert tone="positive" class="mt-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form data-validate wire:submit="sendLink" class="mt-8 space-y-5">
        <x-ui.input wire:model="email" name="email" label="Email address" type="email" icon="users" required autofocus />

        <x-ui.button type="submit" class="w-full" size="lg">
            <span wire:loading.remove wire:target="sendLink">Send reset link</span>
            <span wire:loading wire:target="sendLink">Sending&hellip;</span>
        </x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-secondary">
        <a href="{{ route('login') }}" wire:navigate class="font-medium accent-text hover:underline">Back to sign in</a>
    </p>
</div>
