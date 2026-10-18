<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Authorization\PermissionRegistry;
use Spatie\Permission\Models\Role;

final class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role && ($this->user()?->can('updatePermissions', $role) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(PermissionRegistry $registry): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in($registry->names())],
        ];
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        /** @var list<string> $permissions */
        $permissions = $this->input('permissions', []);

        return $permissions;
    }
}
