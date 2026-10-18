<?php

declare(strict_types=1);

namespace Modules\Identity\Contracts;

use Modules\Identity\Enums\Role;

/**
 * Implemented by each module's backed permission enum so the module can
 * register its permissions without Identity depending on it.
 */
interface PermissionDefinition
{
    /**
     * Roles granted this permission when it (or the role) is first created.
     * The System Administrator always holds every permission regardless.
     *
     * @return list<Role>
     */
    public function defaultRoles(): array;
}
