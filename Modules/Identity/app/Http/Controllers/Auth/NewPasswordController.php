<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\ResetForgottenPassword;
use Modules\Identity\Http\Requests\ResetPasswordRequest;

final class NewPasswordController extends Controller
{
    public function store(ResetPasswordRequest $request, ResetForgottenPassword $reset): JsonResponse
    {
        $status = $reset->handle($request->credentials());

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => __($status)]);
    }
}
