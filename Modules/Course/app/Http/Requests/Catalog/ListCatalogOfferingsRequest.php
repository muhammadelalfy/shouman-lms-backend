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
            'q' => ['nullable', 'string', 'max:120'],
            'kind' => ['nullable', Rule::enum(OfferingKind::class)],
            'pricing' => ['nullable', Rule::enum(Pricing::class)],
            'featured' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $query = $this->input('q');

        if (is_string($query)) {
            $whitespace = '[\x{0009}-\x{000D}\x{0020}\x{0085}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]';
            $query = preg_replace('/\A'.$whitespace.'+|'.$whitespace.'+\z/u', '', $query) ?? $query;
            $this->merge(['q' => $query === '' ? null : $query]);
        }
    }

    public function searchQuery(): ?string
    {
        $query = $this->validated('q');

        return is_string($query) ? $query : null;
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
