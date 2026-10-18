<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Enums\AccountStatus;
use Modules\Identity\Models\User;

final class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('user');

        return $subject instanceof User && ($this->user()?->can('updateStatus', $subject) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AccountStatus::class)],
        ];
    }

    public function status(): AccountStatus
    {
        return AccountStatus::from($this->string('status')->toString());
    }
}
