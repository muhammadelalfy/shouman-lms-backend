<?php

declare(strict_types=1);

namespace Modules\Course\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Course\Models\Lesson;

/**
 * @mixin Lesson
 */
final class LessonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject_id' => $this->subject_id,
            'title' => $this->title,
            'description' => $this->description,
            'summary' => $this->summary,
            'key_points' => $this->key_points ?? [],
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'position' => $this->position,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
