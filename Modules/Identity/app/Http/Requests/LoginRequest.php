<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Identity\Http\Requests\Concerns\NormalizesEmail;

final class LoginRequest extends FormRequest
{
    use NormalizesEmail;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function deviceName(): string
    {
        return $this->filled('device_name') ? $this->string('device_name')->toString() : 'web';
    }
}
