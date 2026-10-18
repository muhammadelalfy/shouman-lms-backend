<?php

declare(strict_types=1);

namespace Modules\Identity\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Identity\Enums\AccountStatus;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => Str::lower(fake()->unique()->safeEmail()),
            'phone' => null,
            'status' => AccountStatus::Active,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password1'),
            'remember_token' => Str::random(10),
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AccountStatus::Disabled,
        ]);
    }

    /**
     * Assign a role after creation. Roles must already be seeded.
     */
    public function withRole(Role $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($role));
    }
}
