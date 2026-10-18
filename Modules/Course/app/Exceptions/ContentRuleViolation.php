<?php

declare(strict_types=1);

namespace Modules\Course\Exceptions;

use App\Exceptions\BusinessRuleViolation;
use Modules\Course\Enums\ContentStatus;

final class ContentRuleViolation extends BusinessRuleViolation
{
    public static function invalidStatusTransition(ContentStatus $from, ContentStatus $to): self
    {
        return new self(
            __('course.violations.invalid_status_transition', [
                'from' => __('course.statuses.'.$from->value),
                'to' => __('course.statuses.'.$to->value),
            ]),
            'invalid_status_transition',
        );
    }

    public static function offeringWithoutPublishedContent(): self
    {
        return new self(__('course.violations.offering_without_published_content'), 'offering_without_published_content');
    }

    public static function subjectWithoutPublishedLessons(): self
    {
        return new self(__('course.violations.subject_without_published_lessons'), 'subject_without_published_lessons');
    }

    public static function alreadyPublished(): self
    {
        return new self(__('course.violations.already_published'), 'already_published');
    }

    public static function singleSubjectOutline(): self
    {
        return new self(__('course.violations.single_subject_outline'), 'single_subject_outline');
    }
}
