<?php

declare(strict_types=1);

namespace Modules\Administration\Policies;

use Modules\Administration\Enums\AdministrationPermission;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

final class AuditLogPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(Role::SystemAdministrator) && $actor->checkPermissionTo(AdministrationPermission::AuditView->value);
    }
}
