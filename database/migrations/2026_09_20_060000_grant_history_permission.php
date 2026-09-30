<?php

use App\Enums\Permission as PermissionName;
use App\Enums\Role as RoleName;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gives the new history permission to the roles that should hold it, in every
 * society that already exists.
 *
 * The provisioner only seeds a role's permissions the first time, so that
 * re-running it never undoes a committee's own adjustments. That is the right
 * behaviour, and it means a permission added later needs handing out here.
 */
return new class extends Migration
{
    /** Office bearers only: who used to live in a flat is the society's record. */
    private const HOLDERS = [
        RoleName::SOCIETY_ADMIN,
        RoleName::PRESIDENT,
        RoleName::SECRETARY,
    ];

    public function up(): void
    {
        $permission = Permission::findOrCreate(PermissionName::HISTORY_VIEW, 'web');

        // Eager loaded because `givePermissionTo` reads the role's current
        // permissions, and the app runs with lazy loading disabled.
        Role::query()
            ->whereIn('name', self::HOLDERS)
            ->with('permissions')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::where('name', PermissionName::HISTORY_VIEW)->first();

        if ($permission === null) {
            return;
        }

        Role::query()
            ->with('permissions')
            ->get()
            ->each(fn (Role $role) => $role->revokePermissionTo($permission));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
