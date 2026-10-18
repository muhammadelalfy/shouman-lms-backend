<?php

declare(strict_types=1);

namespace Modules\Course\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Course\Enums\ContentStatus;
use Modules\Course\Models\Lesson;
use Modules\Course\Models\Subject;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'title' => fake()->sentence(3),
            'description' => null,
            'summary' => fake()->paragraph(),
            'key_points' => [fake()->sentence(), fake()->sentence()],
            'estimated_duration_minutes' => 15,
            'position' => 1,
            'status' => ContentStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ContentStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function position(int $position): static
    {
        return $this->state(fn () => ['position' => $position]);
    }
}
