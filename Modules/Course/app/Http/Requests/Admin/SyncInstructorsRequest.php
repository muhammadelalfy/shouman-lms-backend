<?php

declare(strict_types=1);

namespace Modules\Course\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Course\Http\Requests\Admin\Concerns\AuthorizesAgainstOffering;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

final class SyncInstructorsRequest extends FormRequest
{
    use AuthorizesAgainstOffering;

    public function authorize(): bool
    {
        return $this->authorizeOffering('update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['present', 'array', 'max:20', $this->allInstructors(...)],
            'user_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function userIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('user_ids', [])));
    }

    /**
     * One query for the whole list instead of one exists-check per id.
     */
    private function allInstructors(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === []) {
            return;
        }

        $ids = array_values(array_unique(array_map('intval', array_filter($value, 'is_numeric'))));
        $instructors = User::query()->role(Role::Instructor)->whereKey($ids)->count();

        if (count($ids) !== count($value) || $instructors !== count($ids)) {
            $fail('course.validation.instructors_only')->translate();
        }
    }
}
