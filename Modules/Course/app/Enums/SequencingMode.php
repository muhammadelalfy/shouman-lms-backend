<?php

declare(strict_types=1);

namespace Modules\Course\Enums;

/**
 * How the next subject unlocks (proposal §10). Stored here; enforced by the
 * Learning module when it evaluates a student's progress.
 */
enum SequencingMode: string
{
    case Open = 'open';
    case AfterPreviousCompleted = 'after_previous_completed';
    case AfterPreviousQuizPassed = 'after_previous_quiz_passed';
}
