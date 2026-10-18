<?php

declare(strict_types=1);

namespace Modules\Identity\Enums;

use Modules\Identity\Contracts\PermissionDefinition;

/**
 * Permissions owned by the Identity module. Other modules declare their own
 * enums implementing PermissionDefinition and register them in the
 * PermissionRegistry from their service providers.
 */
enum Permission: string implements PermissionDefinition
{
    case UsersView = 'users.view';
    case UsersManageStatus = 'users.manage-status';
    case UsersAssignRoles = 'users.assign-roles';
    case RolesManagePermissions = 'roles.manage-permissions';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::UsersView, self::UsersManageStatus => [Role::AcademyManager],
            self::UsersAssignRoles, self::RolesManagePermissions => [],
        };
    }
}
