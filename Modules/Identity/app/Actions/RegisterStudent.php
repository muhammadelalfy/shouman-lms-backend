<?php

declare(strict_types=1);

namespace Modules\Identity\Actions;

use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Identity\DTOs\RegisterStudentData;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

final class RegisterStudent
{
    public function handle(RegisterStudentData $data): User
    {
        try {
            $user = DB::transaction(function () use ($data): User {
                $user = User::query()->create([
                    'name' => $data->name,
                    'email' => $data->email,
                    'phone' => $data->phone,
                    'password' => $data->password,
                ]);

                $user->assignRole(Role::Student);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent registration won the race after validation passed.
            $field = $this->conflictingField($data);

            throw ValidationException::withMessages([
                $field => __('validation.unique', ['attribute' => __('validation.attributes.'.$field)]),
            ]);
        }

        event(new Registered($user));

        return $user;
    }

    private function conflictingField(RegisterStudentData $data): string
    {
        return $data->phone !== null && User::query()->where('phone', $data->phone)->exists() ? 'phone' : 'email';
    }
}
