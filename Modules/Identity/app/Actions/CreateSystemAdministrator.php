<?php

declare(strict_types=1);

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

/**
 * Bootstraps a System Administrator from the console. There is deliberately
 * no HTTP path to create administrators without an existing administrator.
 */
final class CreateSystemAdministrator
{
    public function handle(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(Role::SystemAdministrator);

            return $user;
        });
    }
}
