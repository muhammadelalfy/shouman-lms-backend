<?php

declare(strict_types=1);

namespace Modules\Course\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Course\Enums\OfferingKind;
use Modules\Course\Enums\Pricing;

/**
 * Public catalog listing; open to guests.
 */
final class ListCatalogOfferingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['nullable', Rule::enum(OfferingKind::class)],
            'pricing' => ['nullable', Rule::enum(Pricing::class)],
            'featured' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    public function kind(): ?OfferingKind
    {
        return $this->enum('kind', OfferingKind::class);
    }

    public function pricing(): ?Pricing
    {
        return $this->enum('pricing', Pricing::class);
    }

    public function featuredOnly(): bool
    {
        return $this->boolean('featured');
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 12);
    }
}
