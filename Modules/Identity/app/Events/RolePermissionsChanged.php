<?php

declare(strict_types=1);

namespace Modules\Identity\Events;

/**
 * Dispatched inside the permission-sync transaction so synchronous listeners
 * (the audit trail) commit atomically with the change.
 */
final class RolePermissionsChanged
{
    /**
     * @param  list<string>  $previousPermissions
     * @param  list<string>  $currentPermissions
     */
    public function __construct(
        public int $roleId,
        public string $roleName,
        public array $previousPermissions,
        public array $currentPermissions,
        public ?int $actorId,
    ) {}
}
