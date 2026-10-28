<?php

declare(strict_types=1);

namespace Modules\Administration\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Administration\Content\HomepageNeedsDefaults;

final class SaveHomepageNeedsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('homepage.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $link = function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! preg_match('~\A/(?:catalog|tracks|courses|programs|seasonal-programs|initiatives)(?:[/?#][^\x00-\x20\x7f]*)?\z~u', $value)) {
                $fail('اختر رابطًا داخليًا من الفهرس أو المسارات أو الدورات.');

                return;
            }
            $decoded = $value;
            for ($i = 0; $i < 4; $i++) {
                $decoded = rawurldecode($decoded);
            }
            if (preg_match('//u', $decoded) !== 1 || preg_match('/[\x00-\x1f\x7f\\\\]/u', $decoded) !== 0 || str_contains($decoded, '..') || str_contains($decoded, '%')) {
                $fail('الرابط غير صالح.');
            }
        };

        return [
            'version' => ['required', 'integer', 'min:1'],
            'draft' => ['required', 'array:enabled,title,subtitle,cta_label,cta_href,cards'],
            'draft.enabled' => ['required', 'boolean'],
            'draft.title' => ['required', 'string', 'max:80'],
            'draft.subtitle' => ['present', 'nullable', 'string', 'max:240'],
            'draft.cta_label' => ['required', 'string', 'max:80'],
            'draft.cta_href' => ['required', 'string', 'max:1000', $link],
            'draft.cards' => ['present', 'array', 'list', 'max:24'],
            'draft.cards.*' => ['required', 'array:id,title,description,image_key,href,style,enabled'],
            'draft.cards.*.id' => ['required', 'string', 'max:64', 'regex:/\A[a-z0-9-]+\z/D', 'distinct:strict'],
            'draft.cards.*.title' => ['required', 'string', 'max:80'],
            'draft.cards.*.description' => ['present', 'nullable', 'string', 'max:180'],
            'draft.cards.*.image_key' => ['required', Rule::in(HomepageNeedsDefaults::IMAGE_KEYS)],
            'draft.cards.*.href' => ['required', 'string', 'max:1000', $link],
            'draft.cards.*.style' => ['required', Rule::in(['icon', 'photo'])],
            'draft.cards.*.enabled' => ['required', 'boolean'],
        ];
    }
}
