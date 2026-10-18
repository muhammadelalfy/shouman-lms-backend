<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Concerns;

use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

trait CreatesUsers
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createUser(Role $role = Role::Student, array $attributes = []): User
    {
        return User::factory()->withRole($role)->create($attributes);
    }
}
