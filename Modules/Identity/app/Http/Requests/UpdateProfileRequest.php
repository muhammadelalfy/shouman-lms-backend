<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Models\User;
use Modules\Identity\Rules\PhoneNumber;

/**
 * Only name and phone are self-editable. Email changes require a verified
 * re-confirmation flow that is not part of the current scope.
 */
final class UpdateProfileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', new PhoneNumber, Rule::unique(User::class, 'phone')->ignore($this->user())],
        ];
    }
}
