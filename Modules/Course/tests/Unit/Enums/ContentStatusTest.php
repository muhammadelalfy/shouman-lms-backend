<?php

declare(strict_types=1);

namespace Modules\Course\Tests\Unit\Enums;

use Modules\Course\Enums\ContentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContentStatusTest extends TestCase
{
    /**
     * Every pair, so adding a transition by accident fails here.
     *
     * @return array<string, array{ContentStatus, ContentStatus, bool}>
     */
    public static function transitions(): array
    {
        $allowed = [
            'draft>published', 'draft>archived',
            'published>hidden', 'published>archived',
            'hidden>published', 'hidden>archived',
            'archived>hidden',
        ];

        $cases = [];
        foreach (ContentStatus::cases() as $from) {
            foreach (ContentStatus::cases() as $to) {
                $key = "{$from->value}>{$to->value}";
                $cases[$key] = [$from, $to, in_array($key, $allowed, true)];
            }
        }

        return $cases;
    }

    #[DataProvider('transitions')]
    public function test_transition_matrix(ContentStatus $from, ContentStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }
}
