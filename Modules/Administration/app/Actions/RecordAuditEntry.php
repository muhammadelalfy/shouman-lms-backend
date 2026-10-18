<?php

declare(strict_types=1);

namespace Modules\Administration\Actions;

use Illuminate\Support\Facades\Context;
use Modules\Administration\Models\AuditLog;

final class RecordAuditEntry
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function handle(
        ?int $actorId,
        string $action,
        string $subjectType,
        string|int $subjectId,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        $ip = Context::getHidden('client_ip');
        $requestId = Context::get('request_id');

        return AuditLog::query()->create([
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => (string) $subjectId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => is_string($ip) ? $ip : null,
            'request_id' => is_string($requestId) ? $requestId : null,
        ]);
    }
}
