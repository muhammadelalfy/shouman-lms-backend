<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

final class UpdateUserRolesRequest extends FormRequest
{
    /**
     * Baseline check before validation; the requested roles are only known
     * to be valid afterwards, so passedValidation() re-checks with them.
     */
    public function authorize(): bool
    {
        return $this->canAssign([]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'distinct', Rule::enum(Role::class)],
        ];
    }

    protected function passedValidation(): void
    {
        if (! $this->canAssign($this->roles())) {
            $this->failedAuthorization();
        }
    }

    /**
     * @param  list<Role>  $roles
     */
    private function canAssign(array $roles): bool
    {
        $subject = $this->route('user');

        return $subject instanceof User && ($this->user()?->can('assignRoles', [$subject, $roles]) ?? false);
    }

    /**
     * @return list<Role>
     */
    public function roles(): array
    {
        /** @var list<string> $roles */
        $roles = $this->input('roles', []);

        return array_map(fn (string $role): Role => Role::from($role), $roles);
    }
}
