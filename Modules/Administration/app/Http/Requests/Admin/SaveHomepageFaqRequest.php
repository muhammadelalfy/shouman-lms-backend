<?php

declare(strict_types=1);

namespace Modules\Administration\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SaveHomepageFaqRequest extends FormRequest
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
            $controls = str_ends_with($attribute, '.answer') ? '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u' : '/[\x00-\x1f\x7f]/u';
            if (is_string($value) && (! mb_check_encoding($value, 'UTF-8') || str_contains($value, '<') || str_contains($value, '>') || preg_match($controls, $value) !== 0)) {
                $fail('يجب إدخال نص عادي.');
            }
        };

        return [
            'version' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'draft' => ['required', 'array:enabled,title,items'],
            'draft.enabled' => ['required', $boolean],
            'draft.title' => ['required', 'string', 'max:120', $plain],
            'draft.items' => ['present', 'array', 'list', 'max:40'],
            'draft.items.*' => ['required', 'array:id,question,answer,enabled'],
            'draft.items.*.id' => ['required', 'string', 'max:64', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', 'distinct:strict'],
            'draft.items.*.question' => ['required', 'string', 'max:240', $plain],
            'draft.items.*.answer' => ['required', 'string', 'max:3000', $plain],
            'draft.items.*.enabled' => ['required', $boolean],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->isJson()) {
                $body = json_decode($this->getContent());
                if ($body instanceof \stdClass && isset($body->draft) && $body->draft instanceof \stdClass
                    && property_exists($body->draft, 'items') && ! is_array($body->draft->items)) {
                    $validator->errors()->add('draft.items', 'يجب إدخال قائمة.');
                }
            }
            if (array_diff(array_keys($this->all()), ['version', 'draft']) !== []) {
                $validator->errors()->add('payload', 'حقول غير معتمدة.');
            }
        }];
    }
}
