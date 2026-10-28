<?php

declare(strict_types=1);

namespace Modules\Administration\Csv;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Administration\Contracts\CsvResource;
use Modules\Administration\Http\Resources\AuditLogResource;
use Modules\Administration\Models\AuditLog;
use Modules\Identity\Models\User;

final class AuditLogCsvResource implements CsvResource
{
    public function headers(): array
    {
        return ['id', 'actor_id', 'action', 'subject_type', 'subject_id', 'request_id', 'created_at'];
    }

    public function authorize(User $actor, bool $import): void
    {
        abort_if($import, 403);
        Gate::forUser($actor)->authorize('viewAny', AuditLog::class);
    }

    public function exportRows(User $actor, array $filters): iterable
    {
        $this->authorize($actor, false);
        if ($filters !== []) {
            throw ValidationException::withMessages(['filters' => 'هذا المورد لا يدعم مرشحات.']);
        }
        foreach (AuditLog::query()->select($this->headers())->lazyById() as $record) {
            $projected = (new AuditLogResource($record))->resolve(request());
            yield array_map(fn (mixed $value): string => $value === null ? '' : (string) $value, $projected);
        }
    }

    public function importRows(User $actor, array $rows): array
    {
        abort(403);
    }
}
