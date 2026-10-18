<?php

declare(strict_types=1);

namespace Modules\Course\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Course\Http\Requests\Admin\Concerns\AuthorizesAgainstOffering;

/**
 * Ordered ids of every child (subjects of an offering, lessons of a subject).
 * Whether they are exactly the current children is checked under lock.
 */
final class ReorderRequest extends FormRequest
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
            'ids' => ['present', 'array', 'max:500'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function orderedIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('ids', [])));
    }
}
