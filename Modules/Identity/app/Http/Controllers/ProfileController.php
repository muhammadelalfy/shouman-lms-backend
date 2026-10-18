<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Identity\Http\Requests\UpdateProfileRequest;
use Modules\Identity\Http\Resources\CurrentUserResource;
use Modules\Identity\Models\User;

final class ProfileController extends Controller
{
    public function show(Request $request): CurrentUserResource
    {
        return new CurrentUserResource($this->user($request));
    }

    public function update(UpdateProfileRequest $request): CurrentUserResource
    {
        $user = $this->user($request);
        $user->update($request->validated());

        return new CurrentUserResource($user);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
