<?php

declare(strict_types=1);

namespace Modules\Course\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Course\Contracts\Publishable;
use Modules\Course\Enums\ContentStatus;
use Modules\Course\Events\OfferingStatusChanged;
use Modules\Course\Models\Offering;
use Modules\Identity\Models\User;

/**
 * Publishes, hides, archives or restores an offering, subject or lesson.
 * The row is locked so publication prerequisites are checked against the
 * state that is actually committed. Requesting the current status is a no-op.
 */
final class ChangeContentStatus
{
    /**
     * @template TContent of Model&Publishable
     *
     * @param  TContent  $content
     * @return TContent
     */
    public function handle(User $actor, Model&Publishable $content, ContentStatus $target): Model&Publishable
    {
        return DB::transaction(function () use ($actor, $content, $target): Model&Publishable {
            $content->newQuery()->whereKey($content->getKey())->lockForUpdate()->value($content->getKeyName());
            $content->refresh();

            if ($content->currentStatus() === $target) {
                return $content;
            }

            $previous = $content->transitionTo($target);
            $content->save();

            if ($content instanceof Offering) {
                event(new OfferingStatusChanged($content, $previous, $target, $actor->id));
            }

            return $content;
        });
    }
}
