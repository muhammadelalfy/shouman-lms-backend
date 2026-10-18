<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Enums\AccountStatus;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

final class ListUsersRequest extends FormRequest
{
    public const int DEFAULT_PER_PAGE = 25;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(AccountStatus::class)],
            'role' => ['nullable', Rule::enum(Role::class)],
            'search' => ['nullable', 'string', 'min:2', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    public function status(): ?AccountStatus
    {
        return $this->enum('status', AccountStatus::class);
    }

    public function role(): ?Role
    {
        return $this->enum('role', Role::class);
    }

    public function search(): ?string
    {
        return $this->filled('search') ? $this->string('search')->trim()->toString() : null;
    }

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }
}
