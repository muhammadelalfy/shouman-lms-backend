<?php

declare(strict_types=1);

namespace Modules\Course\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Course\Models\Offering;

/**
 * Public card for listings. Exposes published, visible data only — the query
 * that loads it is responsible for visibility.
 *
 * @mixin Offering
 */
class CatalogOfferingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'is_featured' => $this->is_featured,
            'season_name' => $this->season_name,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'pricing' => $this->pricingSummary(),
            'lesson_count' => (int) $this->resource->getAttribute('published_lessons_count'),
        ];
    }
}
