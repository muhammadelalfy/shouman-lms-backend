<?php

declare(strict_types=1);

namespace Modules\Administration\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Administration\Models\AuditLog;

final class ListAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AuditLog::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! $this->validCursor($value)) {
                    $fail('The cursor is invalid.');
                }
            }],
        ];
    }

    public function perPage(): int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : 25;
    }

    private function validCursor(string $encoded): bool
    {
        if (! preg_match('/\A[A-Za-z0-9_-]+\z/D', $encoded)) {
            return false;
        }
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        if ($decoded === false || rtrim(strtr(base64_encode($decoded), '+/', '-_'), '=') !== $encoded) {
            return false;
        }
        $cursor = json_decode($decoded);
        if (! $cursor instanceof \stdClass) {
            return false;
        }
        $fields = get_object_vars($cursor);
        $keys = array_keys($fields);
        sort($keys);
        if ($keys !== ['_pointsToNextItems', 'id'] || ! is_bool($fields['_pointsToNextItems'])
            || (! is_int($fields['id']) && ! is_string($fields['id']))) {
            return false;
        }
        $id = (string) $fields['id'];

        return preg_match('/\A[1-9][0-9]*\z/D', $id) === 1
            && (strlen($id) < 19 || (strlen($id) === 19 && strcmp($id, '9223372036854775807') <= 0));
    }
}
