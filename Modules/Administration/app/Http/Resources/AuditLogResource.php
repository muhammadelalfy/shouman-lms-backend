<?php

declare(strict_types=1);

namespace Modules\Administration\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Administration\Models\AuditLog;
use Modules\Identity\Enums\Role;

/** @mixin AuditLog */
final class AuditLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $type = match ($this->action) {
            'identity.user.status_changed', 'identity.user.roles_changed' => 'user',
            'identity.role.permissions_changed' => 'role',
            'course.offering.status_changed', 'course.offering.pricing_changed' => 'offering',
            default => null,
        };
        $known = $type !== null && $type === $this->subject_type;
        $subject = null;
        if ($known) {
            $id = $this->subject_id;
            if ($type === 'role') {
                $subject = Role::tryFrom($id)?->value;
            } elseif (preg_match('/\A[1-9][0-9]*\z/D', $id) === 1
                && (strlen($id) < 19 || (strlen($id) === 19 && strcmp($id, '9223372036854775807') <= 0))) {
                $subject = $id;
            }
        }

        return [
            'id' => $this->id,
            'actor_id' => $this->actor_id,
            'action' => $known ? $this->action : null,
            'subject_type' => $known ? $type : null,
            'subject_id' => $subject,
            'request_id' => is_string($this->request_id) && preg_match('/\A[A-Za-z0-9\-_.]{8,64}\z/D', $this->request_id) === 1 ? $this->request_id : null,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
