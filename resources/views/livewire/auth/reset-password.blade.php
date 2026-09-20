<div>
    <h1 class="text-2xl font-bold tracking-tight">Choose a new password</h1>
    <p class="mt-2 text-sm text-secondary">Pick something you have not used before.</p>

    <form wire:submit="resetPassword" class="mt-8 space-y-5">
        <x-ui.input wire:model="email" name="email" label="Email address" type="email" required />
        <x-ui.input
            wire:model="password"
            name="password"
            label="New password"
            type="password"
            autocomplete="new-password"
            hint="At least 8 characters."
            required
        />
        <x-ui.input
            wire:model="password_confirmation"
            name="password_confirmation"
            label="Confirm new password"
            type="password"
            autocomplete="new-password"
            required
        />

        <x-ui.button type="submit" class="w-full" size="lg">Reset password</x-ui.button>
    </form>
</div>
