<?php

declare(strict_types=1);

namespace Modules\Administration\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only record of an important administrative operation (FR-ADM-7).
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $request_id
 * @property Carbon $created_at
 */
#[Fillable(['actor_id', 'action', 'subject_type', 'subject_id', 'old_values', 'new_values', 'ip_address', 'request_id'])]
final class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Audit log entries are immutable.'));
        self::deleting(fn () => throw new LogicException('Audit log entries are immutable.'));
    }
}
