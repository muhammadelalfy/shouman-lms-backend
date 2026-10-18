<?php

declare(strict_types=1);

namespace Modules\Identity\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';

    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}
