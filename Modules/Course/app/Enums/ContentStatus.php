<?php

declare(strict_types=1);

namespace Modules\Course\Enums;

/**
 * Publication lifecycle shared by offerings, subjects and lessons.
 *
 *   draft ──► published ◄──► hidden
 *     │           │            │  ▲
 *     └──────► archived ◄──────┘  │
 *                 └───────────────┘ (restore as hidden)
 */
enum ContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Hidden = 'hidden';
    case Archived = 'archived';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, match ($this) {
            self::Draft => [self::Published, self::Archived],
            self::Published => [self::Hidden, self::Archived],
            self::Hidden => [self::Published, self::Archived],
            self::Archived => [self::Hidden],
        }, true);
    }
}
