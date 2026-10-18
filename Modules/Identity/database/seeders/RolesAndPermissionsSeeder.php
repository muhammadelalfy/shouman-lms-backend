<?php

declare(strict_types=1);

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Authorization\PermissionRegistry;
use Modules\Identity\Enums\Role as RoleName;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: safe to run on every deployment.
 *
 * A permission's default roles are granted only when that permission or that
 * role is created for the first time. New modules therefore work on existing
 * installations, while grants a System Administrator later revoked are never
 * restored. The System Administrator role is always re-synced to every
 * registered permission.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    private const string GUARD = 'web';

    public function run(PermissionRegistry $registry, PermissionRegistrar $registrar): void
    {
        $registrar->forgetCachedPermissions();

        /** @var array<string, Role> $roles */
        $roles = [];
        /** @var array<string, bool> $newRoles */
        $newRoles = [];

        foreach (RoleName::cases() as $roleName) {
            /** @var Role $role */
            $role = Role::query()->firstOrCreate(['name' => $roleName->value, 'guard_name' => self::GUARD]);
            $roles[$roleName->value] = $role;
            $newRoles[$roleName->value] = $role->wasRecentlyCreated;
        }

        foreach ($registry->names() as $name) {
            /** @var Permission $permission */
            $permission = Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);

            foreach ($registry->defaultRolesFor($name) as $roleName) {
                if ($permission->wasRecentlyCreated || $newRoles[$roleName->value]) {
                    $roles[$roleName->value]->givePermissionTo($permission);
                }
            }
        }

        $roles[RoleName::SystemAdministrator->value]->syncPermissions($registry->names());

        $registrar->forgetCachedPermissions();
    }
}
