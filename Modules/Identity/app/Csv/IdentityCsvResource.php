<?php

declare(strict_types=1);

namespace Modules\Identity\Csv;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use Modules\Administration\Contracts\CsvResource;
use Modules\Administration\Csv\CsvFormRequests;
use Modules\Identity\Actions\ChangeAccountStatus;
use Modules\Identity\Actions\SyncRolePermissions;
use Modules\Identity\Actions\SyncUserRoles;
use Modules\Identity\Enums\Permission;
use Modules\Identity\Enums\Role;
use Modules\Identity\Http\Requests\Admin\ListUsersRequest;
use Modules\Identity\Http\Requests\Admin\UpdateRolePermissionsRequest;
use Modules\Identity\Http\Requests\Admin\UpdateUserRolesRequest;
use Modules\Identity\Http\Requests\Admin\UpdateUserStatusRequest;
use Modules\Identity\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

final class IdentityCsvResource implements CsvResource
{
    public function __construct(private readonly string $resource)
    {
        if (! in_array($resource, ['users', 'roles'], true)) {
            throw new InvalidArgumentException('Unknown Identity CSV resource.');
        }
    }

    public function headers(): array
    {
        return $this->resource === 'users' ? ['id', 'status', 'roles'] : ['name', 'permissions'];
    }

    public function authorize(User $actor, bool $import): void
    {
        if ($this->resource === 'roles') {
            Gate::forUser($actor)->authorize('viewAny', RoleModel::class);

            return;
        }

        if (! $import) {
            Gate::forUser($actor)->authorize('viewAny', User::class);

            return;
        }

        if (! $actor->checkPermissionTo(Permission::UsersManageStatus->value)
            && ! $actor->checkPermissionTo(Permission::UsersAssignRoles->value)) {
            throw new AuthorizationException;
        }
    }

    public function exportRows(User $actor, array $filters): iterable
    {
        $this->authorize($actor, false);
        $allowed = $this->resource === 'users' ? ['status', 'role', 'search'] : [];
        foreach (array_keys($filters) as $name) {
            if (! in_array($name, $allowed, true)) {
                throw ValidationException::withMessages([$name => 'Unsupported CSV filter.']);
            }
        }

        if ($this->resource === 'roles') {
            foreach (RoleModel::query()->with('permissions')->orderBy('id')->lazyById() as $role) {
                yield ['name' => $role->name, 'permissions' => $this->json($role->permissions->pluck('name')->sort()->values()->all())];
            }

            return;
        }

        $request = CsvFormRequests::validate(ListUsersRequest::class, $actor, $filters);
        $search = $request->search();
        $users = User::query()->select(['id', 'status'])
            ->with('roles')
            ->when($request->status(), fn (Builder $query, $status) => $query->where('status', $status))
            ->when($request->role(), fn (Builder $query, $role) => $query->role($role))
            ->when($search !== null, fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereLike('name', "%{$search}%")->orWhereLike('email', "%{$search}%"),
            ))
            ->orderBy('id');
        foreach ($users->lazyById() as $user) {
            Gate::forUser($actor)->authorize('view', $user);
            yield ['id' => (string) $user->id, 'status' => $user->status->value, 'roles' => $this->json($user->getRoleNames()->sort()->values()->all())];
        }
    }

    public function importRows(User $actor, array $rows): array
    {
        $actor->refresh();
        $this->authorize($actor, true);
        if ($this->resource === 'users' && $rows !== []) {
            // Match the safeguard's lock order; the outer import transaction retains this lock.
            RoleModel::query()->where('name', Role::SystemAdministrator->value)->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
        }
        $seen = [];
        $updated = 0;
        foreach ($rows as $index => $row) {
            $actor->refresh();
            $this->authorize($actor, true);
            $rowNumber = $index + 2;
            $key = $this->resource === 'users' ? 'id' : 'name';
            $identity = $row[$key];
            if (isset($seen[$identity])) {
                $this->invalid($rowNumber, $key, 'Duplicate identifier.');
            }
            $seen[$identity] = true;
            $changed = $this->resource === 'users'
                ? $this->importUser($actor, $row, $rowNumber)
                : $this->importRole($actor, $row, $rowNumber);
            $updated += (int) $changed;
        }

        return ['created' => 0, 'updated' => $updated];
    }

    /** @param array<string,string> $row */
    private function importUser(User $actor, array $row, int $rowNumber): bool
    {
        if (preg_match('/^[1-9][0-9]*$/D', $row['id']) !== 1
            || filter_var($row['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->invalid($rowNumber, 'id', 'An existing positive integer user id is required.');
        }
        $user = User::query()->with('roles')->lockForUpdate()->find((int) $row['id']);
        if ($user === null) {
            $this->invalid($rowNumber, 'id', 'Unknown user id.');
        }
        $roles = $this->stringList($row['roles'], $rowNumber, 'roles');
        $changed = false;
        if ($row['status'] !== $user->status->value) {
            $request = CsvFormRequests::validate(UpdateUserStatusRequest::class, $actor, ['status' => $row['status']], ['user' => $user], $rowNumber);
            app(ChangeAccountStatus::class)->handle($actor, $user, $request->status());
            $changed = true;
        }
        if ($this->sorted($roles) !== $this->sorted($user->getRoleNames()->all())) {
            $request = CsvFormRequests::validate(UpdateUserRolesRequest::class, $actor, ['roles' => $roles], ['user' => $user], $rowNumber);
            app(SyncUserRoles::class)->handle($actor, $user, $request->roles());
            $changed = true;
        }

        return $changed;
    }

    /** @param array<string,string> $row */
    private function importRole(User $actor, array $row, int $rowNumber): bool
    {
        $name = Role::tryFrom($row['name']);
        $role = $name === null ? null : RoleModel::query()->where('name', $name->value)->where('guard_name', 'web')->lockForUpdate()->first();
        if ($role === null) {
            $this->invalid($rowNumber, 'name', 'An existing registered role is required.');
        }
        $permissions = $this->stringList($row['permissions'], $rowNumber, 'permissions');
        if ($this->sorted($permissions) === $this->sorted($role->permissions->pluck('name')->all())) {
            return false;
        }
        $request = CsvFormRequests::validate(UpdateRolePermissionsRequest::class, $actor, ['permissions' => $permissions], ['role' => $role], $rowNumber);
        app(SyncRolePermissions::class)->handle($actor, $role, $request->permissions());

        return true;
    }

    /** @return list<string> */
    private function stringList(string $value, int $rowNumber, string $field): array
    {
        try {
            $decoded = json_decode($value, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->invalid($rowNumber, $field, 'Expected a JSON string list.');
        }
        if (! is_array($decoded) || ! array_is_list($decoded)) {
            $this->invalid($rowNumber, $field, 'Expected a JSON string list.');
        }
        foreach ($decoded as $index => $entry) {
            if (! is_string($entry)) {
                $this->invalid($rowNumber, $field.'.'.$index, 'Expected a string.');
            }
        }

        return $decoded;
    }

    /**
     * @param  array<string>  $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        $values = array_values($values);
        sort($values);

        return $values;
    }

    /** @param array<string> $values */
    private function json(array $values): string
    {
        return json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function invalid(int $rowNumber, string $field, string $message): never
    {
        throw ValidationException::withMessages(['rows.'.$rowNumber.'.'.$field => $message]);
    }
}
