<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Modules\Identity\Actions\ChangePassword;
use Modules\Identity\Http\Requests\UpdatePasswordRequest;
use Modules\Identity\Models\User;

final class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request, ChangePassword $changePassword): Response
    {
        /** @var User $user */
        $user = $request->user();

        $changePassword->handle(
            $user,
            $request->string('password')->toString(),
            (int) $user->currentAccessToken()->getKey(),
        );

        return response()->noContent();
    }
}
