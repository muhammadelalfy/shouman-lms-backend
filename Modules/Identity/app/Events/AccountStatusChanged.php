<?php

declare(strict_types=1);

namespace Modules\Identity\Events;

use Modules\Identity\Enums\AccountStatus;
use Modules\Identity\Models\User;

/**
 * Dispatched inside the status-change transaction so synchronous listeners
 * (the audit trail) commit atomically with the change.
 */
final class AccountStatusChanged
{
    public function __construct(
        public User $user,
        public AccountStatus $previous,
        public AccountStatus $current,
        public ?int $actorId,
    ) {}
}
