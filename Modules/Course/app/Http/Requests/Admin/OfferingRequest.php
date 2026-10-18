<?php

declare(strict_types=1);

namespace Modules\Course\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Course\DTOs\OfferingData;
use Modules\Course\Enums\OfferingKind;
use Modules\Course\Enums\OutlineStyle;
use Modules\Course\Enums\Pricing;
use Modules\Course\Enums\SequencingMode;
use Modules\Course\Models\Offering;

/**
 * Shared full-replacement rules for creating and updating offerings
 * (feature analysis E3, business rules 6–10).
 */
abstract class OfferingRequest extends FormRequest
{
    /**
     * Unicode slug: lowercase Latin, Arabic letters and digits joined by single hyphens.
     */
    private const string SLUG_PATTERN = '/^[\p{Ll}\p{Lo}\p{N}]+(?:-[\p{Ll}\p{Lo}\p{N}]+)*$/u';

    /**
     * The kind of the offering being created or edited, or null when the
     * submitted kind is invalid (its own rule reports that).
     */
    abstract protected function offeringKind(): ?OfferingKind;

    abstract protected function existingOffering(): ?Offering;

    /**
     * @return array<string, mixed>
     */
    protected function offeringRules(): array
    {
        $kind = $this->offeringKind();

        return [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:'.self::SLUG_PATTERN, Rule::unique(Offering::class, 'slug')->ignore($this->existingOffering())],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'goals' => ['nullable', 'array', 'max:20'],
            'goals.*' => ['string', 'min:1', 'max:300'],
            'target_audience' => ['nullable', 'string', 'max:500'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => array_filter(['nullable', 'date', $this->filled('starts_at') ? 'after:starts_at' : null]),
            'visible_from' => ['nullable', 'date'],
            'visible_until' => array_filter(['nullable', 'date', $this->filled('visible_from') ? 'after:visible_from' : null]),
            'is_featured' => ['required', 'boolean'],
            'season_name' => ['nullable', 'string', 'max:100', Rule::prohibitedIf($kind !== OfferingKind::SeasonalProgram)],
            'sequencing_mode' => ['required', Rule::enum(SequencingMode::class)],
            'outline_style' => ['required', Rule::enum(OutlineStyle::class)],
            'pricing' => ['required', Rule::enum(Pricing::class)],
            'price_amount' => ['nullable', 'required_if:pricing,paid', 'prohibited_if:pricing,free', 'integer', 'min:1', 'max:100000000'],
            'discounted_price_amount' => array_filter([
                'nullable', 'prohibited_if:pricing,free', 'integer', 'min:0',
                $this->filled('price_amount') ? 'lt:price_amount' : null,
            ]),
            'currency' => ['nullable', 'required_if:pricing,paid', 'prohibited_if:pricing,free', Rule::in((array) config('course.currencies'))],
        ];
    }

    public function toData(): OfferingData
    {
        /** @var list<string> $goals */
        $goals = array_values((array) $this->input('goals', []));

        return new OfferingData(
            title: $this->string('title')->trim()->toString(),
            slug: $this->filled('slug') ? $this->string('slug')->toString() : null,
            summary: $this->nullableString('summary'),
            description: $this->nullableString('description'),
            goals: $goals,
            targetAudience: $this->nullableString('target_audience'),
            estimatedDurationMinutes: $this->filled('estimated_duration_minutes') ? $this->integer('estimated_duration_minutes') : null,
            startsAt: $this->date('starts_at')?->toImmutable(),
            endsAt: $this->date('ends_at')?->toImmutable(),
            visibleFrom: $this->date('visible_from')?->toImmutable(),
            visibleUntil: $this->date('visible_until')?->toImmutable(),
            isFeatured: $this->boolean('is_featured'),
            seasonName: $this->nullableString('season_name'),
            sequencingMode: SequencingMode::from($this->string('sequencing_mode')->toString()),
            outlineStyle: OutlineStyle::from($this->string('outline_style')->toString()),
            pricing: Pricing::from($this->string('pricing')->toString()),
            priceAmount: $this->filled('price_amount') ? $this->integer('price_amount') : null,
            discountedPriceAmount: $this->filled('discounted_price_amount') ? $this->integer('discounted_price_amount') : null,
            currency: $this->nullableString('currency'),
        );
    }

    private function nullableString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->trim()->toString() : null;
    }
}
