<?php

declare(strict_types=1);

namespace Modules\Identity\Policies;

use Modules\Identity\Enums\Permission;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->checkPermissionTo(Permission::UsersView->value);
    }

    public function view(User $actor, User $subject): bool
    {
        return $actor->checkPermissionTo(Permission::UsersView->value);
    }

    /**
     * Nobody changes their own status, and only a System Administrator may
     * change the status of another System Administrator.
     */
    public function updateStatus(User $actor, User $subject): bool
    {
        if ($actor->is($subject) || ! $actor->checkPermissionTo(Permission::UsersManageStatus->value)) {
            return false;
        }

        return ! $subject->hasRole(Role::SystemAdministrator) || $actor->hasRole(Role::SystemAdministrator);
    }

    /**
     * Only a System Administrator may grant or revoke the System Administrator
     * role or change their own roles; this blocks privilege escalation by any
     * role that was delegated the assign-roles permission.
     *
     * @param  list<Role>  $requestedRoles
     */
    public function assignRoles(User $actor, User $subject, array $requestedRoles): bool
    {
        if (! $actor->checkPermissionTo(Permission::UsersAssignRoles->value)) {
            return false;
        }

        if ($actor->hasRole(Role::SystemAdministrator)) {
            return true;
        }

        $touchesAdministrator = in_array(Role::SystemAdministrator, $requestedRoles, true)
            || $subject->hasRole(Role::SystemAdministrator);

        return ! $actor->is($subject) && ! $touchesAdministrator;
    }
}
