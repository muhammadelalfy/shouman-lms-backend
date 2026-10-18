<?php

declare(strict_types=1);

namespace Modules\Identity\Events;

use Modules\Identity\Models\User;

/**
 * Dispatched inside the role-sync transaction so synchronous listeners
 * (the audit trail) commit atomically with the change.
 */
final class UserRolesChanged
{
    /**
     * @param  list<string>  $previousRoles
     * @param  list<string>  $currentRoles
     */
    public function __construct(
        public User $user,
        public array $previousRoles,
        public array $currentRoles,
        public ?int $actorId,
    ) {}
}
