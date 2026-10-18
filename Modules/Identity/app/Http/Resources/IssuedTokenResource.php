<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\DTOs\IssuedToken;

/**
 * @property IssuedToken $resource
 */
final class IssuedTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $this->resource->expiresAt?->toIso8601String(),
            'user' => new CurrentUserResource($this->resource->user),
        ];
    }
}
