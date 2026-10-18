<?php

declare(strict_types=1);

namespace Modules\Course\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Course\DTOs\LessonData;
use Modules\Course\Models\Lesson;
use Modules\Course\Models\Subject;

/**
 * Appends a draft lesson. The subject is locked so concurrent appends get
 * distinct positions.
 */
final class AddLesson
{
    public function handle(Subject $subject, LessonData $data): Lesson
    {
        return DB::transaction(function () use ($subject, $data): Lesson {
            $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->value('id');

            $lesson = $subject->lessons()->make($data->attributes());
            $lesson->position = (int) $subject->lessons()->max('position') + 1;
            $lesson->save();

            return $lesson;
        });
    }
}
