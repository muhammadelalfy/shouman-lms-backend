<?php

declare(strict_types=1);

namespace Modules\Course\Events;

use Modules\Course\Enums\ContentStatus;
use Modules\Course\Models\Offering;

/**
 * Dispatched inside the status-change transaction so synchronous listeners
 * (the audit trail) commit atomically with the change.
 */
final class OfferingStatusChanged
{
    public function __construct(
        public Offering $offering,
        public ContentStatus $previous,
        public ContentStatus $current,
        public ?int $actorId,
    ) {}
}
