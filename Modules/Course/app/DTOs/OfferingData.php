<?php

declare(strict_types=1);

namespace Modules\Course\DTOs;

use Carbon\CarbonImmutable;
use Modules\Course\Enums\OutlineStyle;
use Modules\Course\Enums\Pricing;
use Modules\Course\Enums\SequencingMode;

/**
 * Every editable attribute of an offering (full-replacement semantics).
 * A null slug means "generate on create, keep current on update".
 */
final readonly class OfferingData
{
    /**
     * @param  list<string>  $goals
     */
    public function __construct(
        public string $title,
        public ?string $slug,
        public ?string $summary,
        public ?string $description,
        public array $goals,
        public ?string $targetAudience,
        public ?int $estimatedDurationMinutes,
        public ?CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public ?CarbonImmutable $visibleFrom,
        public ?CarbonImmutable $visibleUntil,
        public bool $isFeatured,
        public ?string $seasonName,
        public SequencingMode $sequencingMode,
        public OutlineStyle $outlineStyle,
        public Pricing $pricing,
        public ?int $priceAmount,
        public ?int $discountedPriceAmount,
        public ?string $currency,
    ) {}

    /**
     * Model attributes excluding the slug. Free offerings never carry prices.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $isPaid = $this->pricing === Pricing::Paid;

        return [
            'title' => $this->title,
            'summary' => $this->summary,
            'description' => $this->description,
            'goals' => $this->goals === [] ? null : $this->goals,
            'target_audience' => $this->targetAudience,
            'estimated_duration_minutes' => $this->estimatedDurationMinutes,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'visible_from' => $this->visibleFrom,
            'visible_until' => $this->visibleUntil,
            'is_featured' => $this->isFeatured,
            'season_name' => $this->seasonName,
            'sequencing_mode' => $this->sequencingMode,
            'outline_style' => $this->outlineStyle,
            'pricing' => $this->pricing,
            'price_amount' => $isPaid ? $this->priceAmount : null,
            'discounted_price_amount' => $isPaid ? $this->discountedPriceAmount : null,
            'currency' => $isPaid ? $this->currency : null,
        ];
    }
}
