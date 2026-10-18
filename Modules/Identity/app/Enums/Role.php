<?php

declare(strict_types=1);

namespace Modules\Identity\Enums;

enum Role: string
{
    case Student = 'student';
    case Instructor = 'instructor';
    case Supervisor = 'supervisor';
    case Evaluator = 'evaluator';
    case AcademyManager = 'academy-manager';
    case SystemAdministrator = 'system-administrator';

    /**
     * Whether the role's permission set is locked to "every permission".
     */
    public function hasImmutablePermissions(): bool
    {
        return $this === self::SystemAdministrator;
    }
}
