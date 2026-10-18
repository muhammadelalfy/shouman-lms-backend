<?php

declare(strict_types=1);

namespace Modules\Course\Models\Concerns;

use Modules\Course\Enums\ContentStatus;
use Modules\Course\Exceptions\ContentRuleViolation;

/**
 * Implements Publishable for models with `status` (ContentStatus) and
 * `published_at` columns. Using models implement assertPublishable().
 */
trait HasPublicationLifecycle
{
    public function currentStatus(): ContentStatus
    {
        return $this->status;
    }

    public function transitionTo(ContentStatus $target): ContentStatus
    {
        $current = $this->status;

        if (! $current->canTransitionTo($target)) {
            throw ContentRuleViolation::invalidStatusTransition($current, $target);
        }

        if ($target === ContentStatus::Published) {
            $this->assertPublishable();
            $this->published_at ??= now();
        }

        $this->status = $target;

        return $current;
    }

    public function assertNeverPublished(): void
    {
        if ($this->published_at !== null) {
            throw ContentRuleViolation::alreadyPublished();
        }
    }
}
