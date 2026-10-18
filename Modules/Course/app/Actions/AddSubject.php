<?php

declare(strict_types=1);

namespace Modules\Course\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Course\DTOs\SubjectData;
use Modules\Course\Enums\OutlineStyle;
use Modules\Course\Exceptions\ContentRuleViolation;
use Modules\Course\Models\Offering;
use Modules\Course\Models\Subject;

/**
 * Appends a draft subject. The parent is locked so concurrent appends get
 * distinct positions and the single-subject outline rule cannot be raced.
 */
final class AddSubject
{
    public function handle(Offering $offering, SubjectData $data): Subject
    {
        return DB::transaction(function () use ($offering, $data): Subject {
            $offering->newQuery()->whereKey($offering->getKey())->lockForUpdate()->value('id');
            $offering->refresh();

            $existing = $offering->subjects()->count();
            if ($offering->outline_style === OutlineStyle::Lessons && $existing >= 1) {
                throw ContentRuleViolation::singleSubjectOutline();
            }

            $subject = $offering->subjects()->make($data->attributes());
            $subject->position = (int) $offering->subjects()->max('position') + 1;
            $subject->save();

            return $subject;
        });
    }
}
