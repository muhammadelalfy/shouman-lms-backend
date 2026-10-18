<?php

declare(strict_types=1);

namespace Modules\Identity\Policies;

use Modules\Identity\Enums\Permission;
use Modules\Identity\Models\User;
use Spatie\Permission\Models\Role;

final class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->checkPermissionTo(Permission::RolesManagePermissions->value);
    }

    public function updatePermissions(User $actor, Role $role): bool
    {
        return $actor->checkPermissionTo(Permission::RolesManagePermissions->value);
    }
}
