<?php

declare(strict_types=1);

namespace Modules\Administration\Enums;

use Modules\Identity\Contracts\PermissionDefinition;
use Modules\Identity\Enums\Role;

enum AdministrationPermission: string implements PermissionDefinition
{
    case AuditView = 'audit.view';
    case HomepageManage = 'homepage.manage';

    /** @return list<Role> */
    public function defaultRoles(): array
    {
        return [];
    }
}
