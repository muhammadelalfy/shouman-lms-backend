<?php

declare(strict_types=1);

namespace Modules\Course\Database\Factories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Course\Enums\ContentStatus;
use Modules\Course\Enums\OfferingKind;
use Modules\Course\Enums\OutlineStyle;
use Modules\Course\Enums\Pricing;
use Modules\Course\Enums\SequencingMode;
use Modules\Course\Models\Offering;

/**
 * @extends Factory<Offering>
 */
class OfferingFactory extends Factory
{
    protected $model = Offering::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => OfferingKind::Course,
            'title' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(3),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'goals' => [fake()->sentence(), fake()->sentence()],
            'target_audience' => null,
            'estimated_duration_minutes' => null,
            'is_featured' => false,
            'sequencing_mode' => SequencingMode::Open,
            'outline_style' => OutlineStyle::Subjects,
            'pricing' => Pricing::Free,
            'status' => ContentStatus::Draft,
        ];
    }

    public function kind(OfferingKind $kind): static
    {
        return $this->state(fn () => ['kind' => $kind]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ContentStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => [
            'status' => ContentStatus::Hidden,
            'published_at' => now()->subWeek(),
        ]);
    }

    public function paid(int $priceAmount = 150_000, ?int $discountedPriceAmount = null): static
    {
        return $this->state(fn () => [
            'pricing' => Pricing::Paid,
            'price_amount' => $priceAmount,
            'discounted_price_amount' => $discountedPriceAmount,
            'currency' => 'EGP',
        ]);
    }

    public function visibleBetween(?DateTimeInterface $from, ?DateTimeInterface $until): static
    {
        return $this->state(fn () => ['visible_from' => $from, 'visible_until' => $until]);
    }
}
