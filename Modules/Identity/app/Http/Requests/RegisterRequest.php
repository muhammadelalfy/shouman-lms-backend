<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Identity\DTOs\RegisterStudentData;
use Modules\Identity\Http\Requests\Concerns\NormalizesEmail;
use Modules\Identity\Models\User;
use Modules\Identity\Rules\PhoneNumber;

final class RegisterRequest extends FormRequest
{
    use NormalizesEmail;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique(User::class, 'email')],
            'phone' => ['nullable', 'string', new PhoneNumber, Rule::unique(User::class, 'phone')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    public function toData(): RegisterStudentData
    {
        return new RegisterStudentData(
            name: $this->string('name')->trim()->toString(),
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            phone: $this->filled('phone') ? $this->string('phone')->toString() : null,
        );
    }
}
