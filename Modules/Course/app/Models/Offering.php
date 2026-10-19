<?php

declare(strict_types=1);

namespace Modules\Course\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Laravel\Scout\Engines\DatabaseEngine;
use Laravel\Scout\Searchable;
use Modules\Course\Contracts\Publishable;
use Modules\Course\Database\Factories\OfferingFactory;
use Modules\Course\Enums\ContentStatus;
use Modules\Course\Enums\OfferingKind;
use Modules\Course\Enums\OutlineStyle;
use Modules\Course\Enums\Pricing;
use Modules\Course\Enums\SequencingMode;
use Modules\Course\Exceptions\ContentRuleViolation;
use Modules\Course\Models\Concerns\HasPublicationLifecycle;
use Modules\Course\Policies\OfferingPolicy;
use Modules\Identity\Models\User;

/**
 * A track, initiative, intensive or seasonal program, or course: the unit a
 * student enrolls in, pays for, progresses through and is certified on.
 *
 * @property int $id
 * @property OfferingKind $kind
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property list<string>|null $goals
 * @property string|null $target_audience
 * @property int|null $estimated_duration_minutes
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $visible_from
 * @property Carbon|null $visible_until
 * @property bool $is_featured
 * @property string|null $season_name
 * @property SequencingMode $sequencing_mode
 * @property OutlineStyle $outline_style
 * @property Pricing $pricing
 * @property int|null $price_amount
 * @property int|null $discounted_price_amount
 * @property string|null $currency
 * @property ContentStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'kind', 'title', 'slug', 'summary', 'description', 'goals', 'target_audience', 'estimated_duration_minutes',
    'starts_at', 'ends_at', 'visible_from', 'visible_until', 'is_featured', 'season_name',
    'sequencing_mode', 'outline_style', 'pricing', 'price_amount', 'discounted_price_amount', 'currency',
])]
#[UseFactory(OfferingFactory::class)]
#[UsePolicy(OfferingPolicy::class)]
class Offering extends Model implements Publishable
{
    /** @use HasFactory<OfferingFactory> */
    use HasFactory, HasPublicationLifecycle, Searchable;

    /** @return array{title: string|null, summary: string|null, description: string|null} */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'summary' => $this->summary,
            'description' => $this->description,
        ];
    }

    public function searchableUsing(): DatabaseEngine
    {
        return app(DatabaseEngine::class);
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'pricing' => 'free',
        'sequencing_mode' => 'open',
        'outline_style' => 'subjects',
        'is_featured' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => OfferingKind::class,
            'status' => ContentStatus::class,
            'pricing' => Pricing::class,
            'sequencing_mode' => SequencingMode::class,
            'outline_style' => OutlineStyle::class,
            'goals' => 'array',
            'is_featured' => 'boolean',
            'estimated_duration_minutes' => 'integer',
            'price_amount' => 'integer',
            'discounted_price_amount' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'visible_from' => 'datetime',
            'visible_until' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Subject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasManyThrough<Lesson, Subject, $this>
     */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Subject::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'offering_instructors')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * Published and inside the optional display window (business rule 2).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function publiclyVisible(Builder $query): void
    {
        $now = now();

        $query->where('status', ContentStatus::Published)
            ->where(fn (Builder $q) => $q->whereNull('visible_from')->orWhere('visible_from', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('visible_until')->orWhere('visible_until', '>', $now));
    }

    public function assertPublishable(): void
    {
        $hasPublishedContent = $this->subjects()
            ->where('status', ContentStatus::Published)
            ->whereHas('lessons', fn (Builder $lessons) => $lessons->where('status', ContentStatus::Published))
            ->exists();

        if (! $hasPublishedContent) {
            throw ContentRuleViolation::offeringWithoutPublishedContent();
        }
    }

    public function finalPriceAmount(): ?int
    {
        if ($this->pricing === Pricing::Free) {
            return null;
        }

        return $this->discounted_price_amount ?? $this->price_amount;
    }

    /**
     * Price block shared by admin and public representations. Amounts are
     * integer minor units (e.g. piasters); clients format them.
     *
     * @return array{model: string, price_amount: int|null, discounted_price_amount: int|null, final_price_amount: int|null, currency: string|null}
     */
    public function pricingSummary(): array
    {
        return [
            'model' => $this->pricing->value,
            'price_amount' => $this->price_amount,
            'discounted_price_amount' => $this->discounted_price_amount,
            'final_price_amount' => $this->finalPriceAmount(),
            'currency' => $this->currency,
        ];
    }

    /**
     * Fields whose change must be audited (financial impact).
     *
     * @return array{pricing: string, price_amount: int|null, discounted_price_amount: int|null, currency: string|null}
     */
    public function pricingSnapshot(): array
    {
        return [
            'pricing' => $this->pricing->value,
            'price_amount' => $this->price_amount,
            'discounted_price_amount' => $this->discounted_price_amount,
            'currency' => $this->currency,
        ];
    }
}
