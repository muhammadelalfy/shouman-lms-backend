<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Enums\Role as RoleName;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * @mixin Role
 */
final class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->permissions->map(fn (Permission $p): string => $p->name)->sort()->values(),
            ),
            'permissions_locked' => RoleName::tryFrom($this->name)?->hasImmutablePermissions() ?? false,
        ];
    }
}
