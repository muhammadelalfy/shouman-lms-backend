<?php

declare(strict_types=1);

namespace Modules\Course\DTOs;

final readonly class SubjectData
{
    public function __construct(
        public string $title,
        public ?string $description,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
        ];
    }
}
