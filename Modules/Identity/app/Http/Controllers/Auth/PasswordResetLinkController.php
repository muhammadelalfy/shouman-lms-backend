<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Modules\Identity\Http\Requests\ForgotPasswordRequest;

final class PasswordResetLinkController extends Controller
{
    /**
     * Always answers the same way so the endpoint cannot be used to discover
     * which emails have accounts.
     */
    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        Password::broker()->sendResetLink(['email' => $request->string('email')->toString()]);

        return response()->json(['message' => __('identity.reset_link_requested')], 202);
    }
}
