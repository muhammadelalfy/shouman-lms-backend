<?php

declare(strict_types=1);

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Enums\Role;
use Modules\Identity\Events\UserRolesChanged;
use Modules\Identity\Models\User;
use Modules\Identity\Services\SystemAdministratorSafeguard;

/**
 * Replaces a user's roles. Authorization (including who may grant or revoke
 * the System Administrator role) is enforced by UserPolicy.
 */
final class SyncUserRoles
{
    public function __construct(private readonly SystemAdministratorSafeguard $safeguard) {}

    /**
     * @param  list<Role>  $roles
     */
    public function handle(User $actor, User $user, array $roles): User
    {
        $previous = $this->sortedNames(array_filter(Role::cases(), fn (Role $role): bool => $user->hasRole($role)));
        $current = $this->sortedNames($roles);

        if ($previous === $current) {
            return $user;
        }

        $mutation = fn (): User => DB::transaction(function () use ($actor, $user, $roles, $previous, $current): User {
            $user->syncRoles($roles);

            event(new UserRolesChanged($user, $previous, $current, $actor->id));

            return $user;
        });

        $removesAdministrator = in_array(Role::SystemAdministrator->value, $previous, true)
            && ! in_array(Role::SystemAdministrator->value, $current, true);

        if ($removesAdministrator) {
            return $this->safeguard->guard(function () use ($user, $mutation): User {
                $this->safeguard->assertAnotherActiveAdministratorExists($user);

                return $mutation();
            });
        }

        return $mutation();
    }

    /**
     * @param  array<Role>  $roles
     * @return list<string>
     */
    private function sortedNames(array $roles): array
    {
        $names = array_values(array_unique(array_map(fn (Role $role): string => $role->value, $roles)));
        sort($names);

        return $names;
    }
}
