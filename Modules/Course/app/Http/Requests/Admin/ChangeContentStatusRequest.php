<?php

declare(strict_types=1);

namespace Modules\Course\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Course\Enums\ContentStatus;
use Modules\Course\Http\Requests\Admin\Concerns\AuthorizesAgainstOffering;

final class ChangeContentStatusRequest extends FormRequest
{
    use AuthorizesAgainstOffering;

    public function authorize(): bool
    {
        return $this->authorizeOffering('publish');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ContentStatus::class)],
        ];
    }

    public function status(): ContentStatus
    {
        return ContentStatus::from($this->string('status')->toString());
    }
}
