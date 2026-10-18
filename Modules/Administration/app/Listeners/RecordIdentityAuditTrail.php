<?php

declare(strict_types=1);

namespace Modules\Administration\Listeners;

use Illuminate\Events\Dispatcher;
use Modules\Administration\Actions\RecordAuditEntry;
use Modules\Identity\Events\AccountStatusChanged;
use Modules\Identity\Events\RolePermissionsChanged;
use Modules\Identity\Events\UserRolesChanged;

/**
 * Synchronous on purpose: Identity dispatches these events inside its
 * transaction, so the audit row commits or rolls back with the change.
 */
final class RecordIdentityAuditTrail
{
    public function __construct(private readonly RecordAuditEntry $record) {}

    public function onAccountStatusChanged(AccountStatusChanged $event): void
    {
        $this->record->handle(
            actorId: $event->actorId,
            action: 'identity.user.status_changed',
            subjectType: 'user',
            subjectId: $event->user->id,
            oldValues: ['status' => $event->previous->value],
            newValues: ['status' => $event->current->value],
        );
    }

    public function onUserRolesChanged(UserRolesChanged $event): void
    {
        $this->record->handle(
            actorId: $event->actorId,
            action: 'identity.user.roles_changed',
            subjectType: 'user',
            subjectId: $event->user->id,
            oldValues: ['roles' => $event->previousRoles],
            newValues: ['roles' => $event->currentRoles],
        );
    }

    public function onRolePermissionsChanged(RolePermissionsChanged $event): void
    {
        $this->record->handle(
            actorId: $event->actorId,
            action: 'identity.role.permissions_changed',
            subjectType: 'role',
            subjectId: $event->roleName,
            oldValues: ['permissions' => $event->previousPermissions],
            newValues: ['permissions' => $event->currentPermissions],
        );
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            AccountStatusChanged::class => 'onAccountStatusChanged',
            UserRolesChanged::class => 'onUserRolesChanged',
            RolePermissionsChanged::class => 'onRolePermissionsChanged',
        ];
    }
}
