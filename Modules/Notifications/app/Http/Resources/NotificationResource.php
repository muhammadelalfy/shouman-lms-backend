<?php

declare(strict_types=1);

namespace Modules\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * @mixin DatabaseNotification
 *
 * @property mixed $data
 * @property Carbon $created_at
 * @property Carbon|null $read_at
 */
final class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = $this->data;
        $valid = is_array($data) && ($data['schema_version'] ?? null) === 1
            && $this->validText($data['title'] ?? null, 200)
            && $this->validText($data['body'] ?? null, 2000);

        return [
            'id' => $this->id,
            'title' => $valid ? $data['title'] : null,
            'body' => $valid ? $data['body'] : null,
            'action_url' => $valid ? match ($data['action'] ?? null) {
                'account' => '/account',
                'catalog' => '/catalog',
                default => null,
            } : null,
            'created_at' => $this->created_at->toISOString(),
            'read_at' => $this->read_at?->toISOString(),
        ];
    }

    private function validText(mixed $value, int $maximum): bool
    {
        return is_string($value) && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') >= 1 && mb_strlen($value, 'UTF-8') <= $maximum;
    }
}
