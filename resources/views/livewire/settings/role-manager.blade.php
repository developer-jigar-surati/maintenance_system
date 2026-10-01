<div>
    <x-ui.page-header title="Roles &amp; access"
        description="What each role may do in this society. Changes apply here only." />

    @if ($roles->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="key" title="No roles set up yet"
                description="Roles are created when a society is provisioned." />
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($roles as $role)
                <div class="surface-card flex flex-col p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">{{ \App\Enums\Role::label($role->name) }}</h2>
                            <p class="text-xs text-muted">
                                {{ $role->users_count }} {{ \Illuminate\Support\Str::plural('person', $role->users_count) }}
                            </p>
                        </div>
                        <x-ui.badge tone="neutral">
                            {{ $role->permissions->count() }} {{ \Illuminate\Support\Str::plural('permission', $role->permissions->count()) }}
                        </x-ui.badge>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1">
                        @foreach ($role->permissions->take(6) as $permission)
                            <span class="rounded-md surface-inset px-1.5 py-0.5 text-[0.6875rem] text-secondary">
                                {{ $permission->name }}
                            </span>
                        @endforeach
                        @if ($role->permissions->count() > 6)
                            <span class="rounded-md surface-inset px-1.5 py-0.5 text-[0.6875rem] text-muted">
                                +{{ $role->permissions->count() - 6 }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-auto flex gap-2 pt-4">
                        <x-ui.button size="sm" variant="secondary" wire:click="edit({{ $role->id }})">Edit</x-ui.button>
                        <x-ui.button size="sm" variant="ghost" wire:click="resetToDefaults({{ $role->id }})"
                            data-confirm="Reset this role to its defaults?"
                            data-confirm-detail="Every permission your committee has added or removed on this role is discarded."
                            data-confirm-action="Reset it">Reset</x-ui.button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <x-ui.modal name="edit-role"
        :title="$editingRole ? 'Permissions for '.\App\Enums\Role::label($editingRole->name) : 'Edit role'"
        max-width="2xl">
        <form data-validate wire:submit="save" id="edit-role-form" class="space-y-5">
            @foreach ($groupedPermissions as $module => $permissions)
                <fieldset>
                    <legend class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">
                        {{ ucwords(str_replace('_', ' ', $module)) }}
                    </legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($permissions as $permission)
                            <label class="flex items-center gap-2.5 rounded-lg border border-subtle px-3 py-2 text-sm">
                                <input type="checkbox" wire:model="selected" value="{{ $permission }}"
                                    class="size-4 rounded border-strong accent-[var(--accent)]">
                                <span class="truncate">{{ \Illuminate\Support\Str::after($permission, '.') }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'edit-role')">Cancel</x-ui.button>
            <x-ui.button type="submit" form="edit-role-form">Save permissions</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
