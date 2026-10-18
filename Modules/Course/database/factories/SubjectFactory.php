<?php

declare(strict_types=1);

namespace Modules\Course\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Course\Enums\ContentStatus;
use Modules\Course\Models\Offering;
use Modules\Course\Models\Subject;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offering_id' => Offering::factory(),
            'title' => fake()->sentence(3),
            'description' => null,
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
