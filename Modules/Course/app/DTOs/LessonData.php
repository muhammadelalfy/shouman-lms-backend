<?php

declare(strict_types=1);

namespace Modules\Course\DTOs;

final readonly class LessonData
{
    /**
     * @param  list<string>  $keyPoints
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public ?string $summary,
        public array $keyPoints,
        public ?int $estimatedDurationMinutes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'summary' => $this->summary,
            'key_points' => $this->keyPoints === [] ? null : $this->keyPoints,
            'estimated_duration_minutes' => $this->estimatedDurationMinutes,
        ];
    }
}
