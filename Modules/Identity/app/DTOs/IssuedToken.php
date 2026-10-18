<?php

declare(strict_types=1);

namespace Modules\Identity\DTOs;

use Carbon\CarbonInterface;
use Modules\Identity\Models\User;

final readonly class IssuedToken
{
    public function __construct(
        public User $user,
        public string $plainTextToken,
        public ?CarbonInterface $expiresAt,
    ) {}
}
