<?php

declare(strict_types=1);

namespace Modules\Identity\Authorization;

use BackedEnum;
use Modules\Identity\Contracts\PermissionDefinition;
use Modules\Identity\Enums\Role;

/**
 * Every permission the application knows, contributed by each module's
 * service provider at boot. Populated once per worker and read-only
 * afterwards, so it is safe as a singleton under Octane.
 */
final class PermissionRegistry
{
    /**
     * @var array<string, list<Role>>
     */
    private array $permissions = [];

    /**
     * @param  class-string<BackedEnum&PermissionDefinition>  $enum
     */
    public function register(string $enum): void
    {
        foreach ($enum::cases() as $permission) {
            $this->permissions[(string) $permission->value] = $permission->defaultRoles();
        }
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $names = array_keys($this->permissions);
        sort($names);

        return $names;
    }

    /**
     * @return list<Role>
     */
    public function defaultRolesFor(string $permission): array
    {
        return $this->permissions[$permission] ?? [];
    }
}
