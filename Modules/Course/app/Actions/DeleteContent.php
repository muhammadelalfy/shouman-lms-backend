<?php

declare(strict_types=1);

namespace Modules\Course\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Course\Contracts\Publishable;

/**
 * Hard-deletes never-published content (with its children, via foreign-key
 * cascades). Anything ever published must be hidden or archived instead.
 */
final class DeleteContent
{
    public function handle(Model&Publishable $content): void
    {
        DB::transaction(function () use ($content): void {
            $content->newQuery()->whereKey($content->getKey())->lockForUpdate()->value($content->getKeyName());
            $content->refresh();

            $content->assertNeverPublished();
            $content->delete();
        });
    }
}
