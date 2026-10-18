<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Modules\Identity\Actions\ChangeAccountStatus;
use Modules\Identity\Http\Requests\Admin\UpdateUserStatusRequest;
use Modules\Identity\Http\Resources\UserResource;
use Modules\Identity\Models\User;

final class UserStatusController extends Controller
{
    public function update(UpdateUserStatusRequest $request, User $user, ChangeAccountStatus $changeStatus): UserResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new UserResource($changeStatus->handle($actor, $user, $request->status())->load('roles'));
    }
}
