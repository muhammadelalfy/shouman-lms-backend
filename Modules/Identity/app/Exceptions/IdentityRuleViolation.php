<?php

declare(strict_types=1);

namespace Modules\Identity\Exceptions;

use App\Exceptions\BusinessRuleViolation;

final class IdentityRuleViolation extends BusinessRuleViolation
{
    public static function lastSystemAdministrator(): self
    {
        return new self(__('identity.last_system_administrator'), 'last_system_administrator');
    }

    public static function immutableRolePermissions(): self
    {
        return new self(__('identity.immutable_role_permissions'), 'immutable_role_permissions');
    }
}
