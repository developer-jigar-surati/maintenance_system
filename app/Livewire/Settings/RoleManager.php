<?php

namespace App\Livewire\Settings;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleName;
use App\Support\SocietyContext;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Per-society role configuration.
 *
 * Roles are scoped to the society, so a committee can widen or tighten what a
 * manager or guard may do without affecting any other society on the platform.
 */
#[Layout('components.layouts.app')]
class RoleManager extends Component
{
    public ?int $editingRoleId = null;

    public array $selected = [];

    public function edit(int $roleId): void
    {
        Gate::authorize(PermissionEnum::SOCIETY_MANAGE);

        $role = $this->roleQuery()->findOrFail($roleId);

        $this->editingRoleId = $role->id;
        $this->selected = $role->permissions->pluck('name')->all();

        $this->dispatch('open-modal', 'edit-role');
    }

    public function save(): void
    {
        Gate::authorize(PermissionEnum::SOCIETY_MANAGE);

        $role = $this->roleQuery()->findOrFail($this->editingRoleId);

        // Never let a society lock itself out of its own administration.
        if ($role->name === RoleName::SOCIETY_ADMIN) {
            $this->dispatch('notify',
                message: 'The society administrator role always keeps full access.',
                tone: 'critical');

            return;
        }

        $valid = array_values(array_intersect($this->selected, PermissionEnum::all()));

        $role->syncPermissions($valid);

        $this->dispatch('close-modal', 'edit-role');
        $this->dispatch('notify', message: 'Permissions updated.', tone: 'positive');
    }

    public function resetToDefaults(int $roleId): void
    {
        Gate::authorize(PermissionEnum::SOCIETY_MANAGE);

        $role = $this->roleQuery()->findOrFail($roleId);
        $role->syncPermissions(PermissionEnum::defaultsForRole($role->name));

        $this->dispatch('notify', message: "Reset {$role->name} to its defaults.", tone: 'positive');
    }

    private function roleQuery()
    {
        return RoleModel::query()
            ->where('society_id', app(SocietyContext::class)->check()->id)
            ->with('permissions');
    }

    public function render()
    {
        $society = app(SocietyContext::class)->check();
        setPermissionsTeamId($society->id);

        $roles = $this->roleQuery()
            ->withCount('users')
            ->get()
            ->sortBy(fn ($r) => array_search($r->name, RoleName::all(), true));

        // Grouped by module prefix, so the editor reads as a permission matrix
        // rather than a flat list of sixty checkboxes.
        $grouped = collect(PermissionEnum::all())
            ->groupBy(fn (string $name) => \Illuminate\Support\Str::before($name, '.'))
            ->map(fn ($items) => $items->values());

        return view('livewire.settings.role-manager', [
            'roles' => $roles,
            'groupedPermissions' => $grouped,
            'editingRole' => $this->editingRoleId ? $roles->firstWhere('id', $this->editingRoleId) : null,
        ])->title('Roles & access');
    }
}
