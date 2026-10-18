<?php

declare(strict_types=1);

namespace Modules\Identity\Actions;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Modules\Identity\Models\User;

/**
 * Resets a password using a broker token and revokes every existing token.
 */
final class ResetForgottenPassword
{
    /**
     * @param  array{email: string, token: string, password: string}  $credentials
     * @return string One of the PasswordBroker status constants.
     */
    public function handle(array $credentials): string
    {
        $status = Password::broker()->reset($credentials, function (User $user, string $password): void {
            DB::transaction(function () use ($user, $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
            });

            event(new PasswordReset($user));
        });

        return is_string($status) ? $status : PasswordBroker::INVALID_TOKEN;
    }
}
