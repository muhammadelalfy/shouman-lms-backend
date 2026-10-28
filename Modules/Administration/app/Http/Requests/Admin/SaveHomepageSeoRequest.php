<?php

declare(strict_types=1);

namespace Modules\Administration\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SaveHomepageSeoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->isJson()) {
            $body = json_decode($this->getContent(), true);
            if (is_array($body) && array_key_exists('draft', $body)) {
                $this->merge(['draft' => $body['draft']]);
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('homepage.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $boolean = function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_bool($value)) {
                $fail('يجب إدخال قيمة منطقية.');
            }
        };
        $plain = function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && (! mb_check_encoding($value, 'UTF-8') || preg_match('/[<>\x00-\x1f\x7f]/u', $value) !== 0)) {
                $fail('يجب إدخال نص عادي.');
            }
        };

        return [
            'version' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'draft' => ['required', 'array:enabled,title,description,robots_index'],
            'draft.enabled' => ['required', $boolean],
            'draft.title' => ['present', 'string', 'max:80', $plain],
            'draft.description' => ['present', 'string', 'max:240', $plain],
            'draft.robots_index' => ['required', $boolean],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['version', 'draft']) !== []) {
                $validator->errors()->add('payload', 'حقول غير معتمدة.');
            }
            $title = $this->input('draft.title');
            if ($this->input('draft.enabled') === true && is_string($title) && preg_match('/\S/u', $title) !== 1) {
                $validator->errors()->add('draft.title', 'العنوان مطلوب للقسم المفعّل.');
            }
        }];
    }
}
