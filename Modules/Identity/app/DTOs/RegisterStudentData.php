<?php

declare(strict_types=1);

namespace Modules\Identity\DTOs;

final readonly class RegisterStudentData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $phone = null,
    ) {}
}
