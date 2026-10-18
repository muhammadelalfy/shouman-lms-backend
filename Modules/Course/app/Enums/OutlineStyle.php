<?php

declare(strict_types=1);

namespace Modules\Course\Enums;

/**
 * Presentation of the outline (proposal §14: "course → subject → lessons" or
 * "course → lessons directly"). Lessons always belong to a subject; the
 * `Lessons` style shows them flat and limits the offering to one subject.
 */
enum OutlineStyle: string
{
    case Subjects = 'subjects';
    case Lessons = 'lessons';
}
