<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Modules\Identity\Actions\SyncUserRoles;
use Modules\Identity\Http\Requests\Admin\UpdateUserRolesRequest;
use Modules\Identity\Http\Resources\UserResource;
use Modules\Identity\Models\User;

final class UserRolesController extends Controller
{
    public function update(UpdateUserRolesRequest $request, User $user, SyncUserRoles $syncRoles): UserResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new UserResource($syncRoles->handle($actor, $user, $request->roles())->load('roles'));
    }
}
