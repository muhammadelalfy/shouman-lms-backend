<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Http\Requests\Admin\ListUsersRequest;
use Modules\Identity\Http\Resources\UserResource;
use Modules\Identity\Models\User;

final class UserController extends Controller
{
    /**
     * Cursor-paginated (no COUNT(*) over millions of rows), newest first.
     */
    public function index(ListUsersRequest $request): AnonymousResourceCollection
    {
        $search = $request->search();

        $users = User::query()
            ->with('roles')
            ->when($request->status(), fn (Builder $query, $status) => $query->where('status', $status))
            ->when($request->role(), fn (Builder $query, $role) => $query->role($role))
            ->when($search !== null, fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereLike('name', "%{$search}%")->orWhereLike('email', "%{$search}%"),
            ))
            ->orderByDesc('id')
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load('roles'));
    }
}
