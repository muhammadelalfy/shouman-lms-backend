<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Modules\Identity\Models\User;

/**
 * The authenticated user, including effective permissions so the frontend can
 * render authorization-aware UI (the backend remains the authority).
 *
 * @mixin User
 */
final class CurrentUserResource extends UserResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'roles' => $this->getRoleNames()->values(),
            'permissions' => $this->getAllPermissions()->pluck('name')->sort()->values(),
        ];
    }
}
