<?php

declare(strict_types=1);

namespace Modules\Course\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Course\DTOs\SubjectData;
use Modules\Course\Http\Requests\Admin\Concerns\AuthorizesAgainstOffering;

/**
 * Create and full-replace update of a subject.
 */
final class SubjectRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function toData(): SubjectData
    {
        return new SubjectData(
            title: $this->string('title')->trim()->toString(),
            description: $this->filled('description') ? $this->string('description')->trim()->toString() : null,
        );
    }
}
