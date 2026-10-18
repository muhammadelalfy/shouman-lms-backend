<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Identity\Actions\IssueAccessToken;
use Modules\Identity\Actions\RegisterStudent;
use Modules\Identity\Http\Requests\RegisterRequest;
use Modules\Identity\Http\Resources\IssuedTokenResource;

final class RegisteredUserController extends Controller
{
    public function store(RegisterRequest $request, RegisterStudent $register, IssueAccessToken $issueToken): JsonResponse
    {
        $user = $register->handle($request->toData());

        return (new IssuedTokenResource($issueToken->forUser($user, 'web')))
            ->response()
            ->setStatusCode(201);
    }
}
