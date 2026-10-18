<?php

declare(strict_types=1);

namespace Modules\Course\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Course\Models\Subject;

/**
 * @mixin Subject
 */
final class SubjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'offering_id' => $this->offering_id,
            'title' => $this->title,
            'description' => $this->description,
            'position' => $this->position,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
        ];
    }
}
