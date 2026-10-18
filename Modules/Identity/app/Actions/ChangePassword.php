<?php

declare(strict_types=1);

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;

/**
 * Changes the password of an authenticated user and signs out every other
 * device; the token used for this request stays valid.
 */
final class ChangePassword
{
    public function handle(User $user, string $newPassword, int $currentTokenId): void
    {
        DB::transaction(function () use ($user, $newPassword, $currentTokenId): void {
            $user->forceFill(['password' => $newPassword])->save();

            $user->tokens()->whereKeyNot($currentTokenId)->delete();
        });
    }
}
