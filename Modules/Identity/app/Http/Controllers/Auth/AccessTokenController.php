<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Identity\Actions\IssueAccessToken;
use Modules\Identity\Http\Requests\LoginRequest;
use Modules\Identity\Http\Resources\IssuedTokenResource;
use Modules\Identity\Models\User;

final class AccessTokenController extends Controller
{
    /**
     * Log in: exchange credentials for a personal access token.
     */
    public function store(LoginRequest $request, IssueAccessToken $issueToken): IssuedTokenResource
    {
        return new IssuedTokenResource($issueToken->handle(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->deviceName(),
        ));
    }

    /**
     * Log out: revoke the token used for this request only.
     */
    public function destroy(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()->delete();

        return response()->noContent();
    }
}
