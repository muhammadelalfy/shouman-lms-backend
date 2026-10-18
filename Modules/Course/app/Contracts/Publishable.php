<?php

declare(strict_types=1);

namespace Modules\Course\Contracts;

use Modules\Course\Enums\ContentStatus;

/**
 * Content with the shared publication lifecycle (offerings, subjects, lessons).
 */
interface Publishable
{
    public function currentStatus(): ContentStatus;

    /**
     * Apply a status transition, enforcing the state machine and the
     * content's own publication prerequisites. Does not persist.
     *
     * @return ContentStatus The status before the transition.
     */
    public function transitionTo(ContentStatus $target): ContentStatus;

    /**
     * Throw if the content may not become published in its current shape.
     */
    public function assertPublishable(): void;

    /**
     * Throw if the content was ever published (it may then only be hidden or
     * archived, because enrollments, progress and certificates can reference it).
     */
    public function assertNeverPublished(): void;
}
