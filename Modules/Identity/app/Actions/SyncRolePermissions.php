<?php

declare(strict_types=1);

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Enums\Role as RoleName;
use Modules\Identity\Events\RolePermissionsChanged;
use Modules\Identity\Exceptions\IdentityRuleViolation;
use Modules\Identity\Models\User;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;

final class SyncRolePermissions
{
    /**
     * @param  list<string>  $permissions  Names already validated against the PermissionRegistry.
     */
    public function handle(User $actor, Role $role, array $permissions): Role
    {
        if (RoleName::tryFrom($role->name)?->hasImmutablePermissions() ?? false) {
            throw IdentityRuleViolation::immutableRolePermissions();
        }

        $previous = array_values($role->permissions->map(fn (PermissionModel $p): string => $p->name)->sort()->all());
        $current = array_values(array_unique($permissions));
        sort($current);

        if ($previous === $current) {
            return $role;
        }

        return DB::transaction(function () use ($actor, $role, $previous, $current): Role {
            $role->syncPermissions($current);

            event(new RolePermissionsChanged((int) $role->getKey(), $role->name, $previous, $current, $actor->id));

            return $role->load('permissions');
        });
    }
}
