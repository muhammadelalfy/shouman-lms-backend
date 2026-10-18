<?php

declare(strict_types=1);

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Enums\AccountStatus;
use Modules\Identity\Enums\Role;
use Modules\Identity\Events\AccountStatusChanged;
use Modules\Identity\Models\User;
use Modules\Identity\Services\SystemAdministratorSafeguard;

/**
 * Activates or disables an account. Disabling revokes every token at once.
 * Authorization (who may change whose status) is enforced by UserPolicy.
 */
final class ChangeAccountStatus
{
    public function __construct(private readonly SystemAdministratorSafeguard $safeguard) {}

    public function handle(User $actor, User $user, AccountStatus $status): User
    {
        if ($user->status === $status) {
            return $user;
        }

        $mutation = fn (): User => DB::transaction(function () use ($actor, $user, $status): User {
            $previous = $user->status;

            $user->status = $status;
            $user->save();

            if ($status === AccountStatus::Disabled) {
                $user->tokens()->delete();
            }

            event(new AccountStatusChanged($user, $previous, $status, $actor->id));

            return $user;
        });

        if ($status === AccountStatus::Disabled && $user->hasRole(Role::SystemAdministrator)) {
            return $this->safeguard->guard(function () use ($user, $mutation): User {
                $this->safeguard->assertAnotherActiveAdministratorExists($user);

                return $mutation();
            });
        }

        return $mutation();
    }
}
