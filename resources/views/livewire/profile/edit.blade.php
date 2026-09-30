<div class="mx-auto max-w-2xl">
    <x-ui.page-header title="Your profile" description="Your details and sign-in credentials." />

    <div class="space-y-6">
        <x-ui.card title="Details">
            <form data-validate wire:submit="saveProfile" class="space-y-4">
                <x-ui.input wire:model="name" name="name" label="Name" required minlength="2" maxlength="120" />
                <x-ui.input wire:model="email" name="email" label="Email" type="email" required maxlength="180" />
                <x-ui.input wire:model="phone" name="phone" label="Mobile number" type="tel"
                    hint="You can sign in with either your email or this number." maxlength="20" data-rule="phone" />

                <x-ui.button type="submit">
                    <span wire:loading.remove wire:target="saveProfile">Save changes</span>
                    <span wire:loading wire:target="saveProfile">Saving&hellip;</span>
                </x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card title="Password">
            <form data-validate wire:submit="updatePassword" class="space-y-4">
                <x-ui.input wire:model="currentPassword" name="currentPassword" label="Current password"
                    type="password" autocomplete="current-password" required />
                <x-ui.input wire:model="password" name="password" label="New password" type="password"
                    autocomplete="new-password" hint="At least 8 characters." required />
                <x-ui.input wire:model="password_confirmation" name="password_confirmation"
                    label="Confirm new password" type="password" autocomplete="new-password" required />

                <x-ui.button type="submit">Change password</x-ui.button>
            </form>
        </x-ui.card>

        @if ($societies->isNotEmpty())
            <x-ui.card title="Your societies" padded="false">
                <ul class="divide-y divide-[var(--border-subtle)]">
                    @foreach ($societies as $society)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ $society->name }}</p>
                                <p class="truncate text-xs text-muted">{{ $society->typeLabel() }}</p>
                            </div>
                            @if ($society->id === $user->current_society_id)
                                <x-ui.badge tone="accent">Current</x-ui.badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
    </div>
</div>
