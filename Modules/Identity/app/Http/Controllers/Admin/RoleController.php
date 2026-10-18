<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Http\Resources\RoleResource;
use Spatie\Permission\Models\Role;

final class RoleController extends Controller
{
    /**
     * The role set is small and fixed by the Role enum, so no pagination.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Role::class);

        return RoleResource::collection(Role::query()->with('permissions')->orderBy('id')->get());
    }
}
