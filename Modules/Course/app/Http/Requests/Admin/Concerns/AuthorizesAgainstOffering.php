<?php

declare(strict_types=1);

namespace Modules\Course\Http\Requests\Admin\Concerns;

use Modules\Course\Models\Lesson;
use Modules\Course\Models\Offering;
use Modules\Course\Models\Subject;

/**
 * Subjects and lessons have no permissions of their own; every authoring
 * request is authorized against the offering that owns the content.
 */
trait AuthorizesAgainstOffering
{
    protected function authorizeOffering(string $ability): bool
    {
        $offering = $this->owningOffering();

        return $offering !== null && ($this->user()?->can($ability, $offering) ?? false);
    }

    private function owningOffering(): ?Offering
    {
        $offering = $this->route('offering');
        if ($offering instanceof Offering) {
            return $offering;
        }

        $subject = $this->route('subject');
        if ($subject instanceof Subject) {
            return $subject->offering;
        }

        $lesson = $this->route('lesson');
        if ($lesson instanceof Lesson) {
            return $lesson->subject->offering;
        }

        return null;
    }
}
