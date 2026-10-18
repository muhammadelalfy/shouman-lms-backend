<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Modules\Identity\Actions\SyncRolePermissions;
use Modules\Identity\Http\Requests\Admin\UpdateRolePermissionsRequest;
use Modules\Identity\Http\Resources\RoleResource;
use Modules\Identity\Models\User;
use Spatie\Permission\Models\Role;

final class RolePermissionsController extends Controller
{
    public function update(UpdateRolePermissionsRequest $request, Role $role, SyncRolePermissions $syncPermissions): RoleResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new RoleResource($syncPermissions->handle($actor, $role, $request->permissions())->load('permissions'));
    }
}
