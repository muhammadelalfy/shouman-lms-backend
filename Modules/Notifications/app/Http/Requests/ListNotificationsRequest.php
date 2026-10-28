<?php

declare(strict_types=1);

namespace Modules\Notifications\Http\Requests;

use Closure;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['all', 'unread', 'read'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! $this->validCursor($value)) {
                    $fail('The cursor is invalid.');
                }
            }],
        ];
    }

    public function status(): string
    {
        return $this->input('status') ?? 'all';
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
        if ($keys !== ['_pointsToNextItems', 'created_at', 'id'] || ! is_bool($fields['_pointsToNextItems'])
            || ! is_string($fields['id']) || ! Str::isUuid($fields['id']) || ! is_string($fields['created_at'])) {
            return false;
        }
        $timestamp = $fields['created_at'];
        if (str_starts_with($timestamp, '0000-') || ! preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\z/D', $timestamp)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $timestamp);

        return $date !== false && $date->format('Y-m-d H:i:s') === $timestamp;
    }
}
