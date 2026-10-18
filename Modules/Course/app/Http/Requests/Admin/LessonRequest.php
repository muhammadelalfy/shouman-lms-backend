<?php

declare(strict_types=1);

namespace Modules\Course\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Course\DTOs\LessonData;
use Modules\Course\Http\Requests\Admin\Concerns\AuthorizesAgainstOffering;

/**
 * Create and full-replace update of a lesson's text content. Media is
 * attached through the Media module.
 */
final class LessonRequest extends FormRequest
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
            'summary' => ['nullable', 'string', 'max:20000'],
            'key_points' => ['nullable', 'array', 'max:30'],
            'key_points.*' => ['string', 'min:1', 'max:300'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function toData(): LessonData
    {
        /** @var list<string> $keyPoints */
        $keyPoints = array_values((array) $this->input('key_points', []));

        return new LessonData(
            title: $this->string('title')->trim()->toString(),
            description: $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            summary: $this->filled('summary') ? $this->string('summary')->trim()->toString() : null,
            keyPoints: $keyPoints,
            estimatedDurationMinutes: $this->filled('estimated_duration_minutes') ? $this->integer('estimated_duration_minutes') : null,
        );
    }
}
