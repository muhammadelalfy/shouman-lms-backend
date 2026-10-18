<?php

declare(strict_types=1);

namespace Modules\Course\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Course\Models\Offering;

/**
 * @mixin Offering
 */
final class OfferingResource extends JsonResource
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
            'description' => $this->description,
            'goals' => $this->goals ?? [],
            'target_audience' => $this->target_audience,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'visible_from' => $this->visible_from?->toIso8601String(),
            'visible_until' => $this->visible_until?->toIso8601String(),
            'is_featured' => $this->is_featured,
            'season_name' => $this->season_name,
            'sequencing_mode' => $this->sequencing_mode->value,
            'outline_style' => $this->outline_style->value,
            'pricing' => $this->pricingSummary(),
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'subjects_count' => $this->whenCounted('subjects'),
            'subjects' => SubjectResource::collection($this->whenLoaded('subjects')),
            'instructors' => InstructorResource::collection($this->whenLoaded('instructors')),
        ];
    }
}
