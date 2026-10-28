<?php

declare(strict_types=1);

namespace Modules\Administration\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class PublishHomepageSeoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('homepage.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['version' => ['required', 'integer', 'min:1', 'max:2147483647']];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['version']) !== []) {
                $validator->errors()->add('payload', 'حقول غير معتمدة.');
            }
        }];
    }
}
