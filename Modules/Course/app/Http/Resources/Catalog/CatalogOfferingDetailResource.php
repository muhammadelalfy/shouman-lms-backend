<?php

declare(strict_types=1);

namespace Modules\Course\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Modules\Course\Models\Lesson;
use Modules\Course\Models\Offering;
use Modules\Course\Models\Subject;
use Modules\Identity\Models\User;

/**
 * Public track/course page (FR-PUB-2/3). The outline lists only published
 * subjects and lessons, and lesson titles only; lesson content is served to
 * enrolled students by the Learning module.
 *
 * @mixin Offering
 */
final class CatalogOfferingDetailResource extends CatalogOfferingResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lessons = $this->subjects->flatMap(fn (Subject $subject) => $subject->lessons);

        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'goals' => $this->goals ?? [],
            'target_audience' => $this->target_audience,
            'sequencing_mode' => $this->sequencing_mode->value,
            'outline_style' => $this->outline_style->value,
            'lesson_count' => $lessons->count(),
            'total_lesson_minutes' => (int) $lessons->sum(fn (Lesson $lesson): int => $lesson->estimated_duration_minutes ?? 0),
            'instructors' => $this->instructors->map(fn (User $instructor): array => [
                'id' => $instructor->id,
                'name' => $instructor->name,
            ])->values(),
            'outline' => $this->subjects->map(fn (Subject $subject): array => [
                'id' => $subject->id,
                'title' => $subject->title,
                'description' => $subject->description,
                'lessons' => $subject->lessons->map(fn (Lesson $lesson): array => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'estimated_duration_minutes' => $lesson->estimated_duration_minutes,
                ])->values(),
            ])->values(),
        ];
    }
}
