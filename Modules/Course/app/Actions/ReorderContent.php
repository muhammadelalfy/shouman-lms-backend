<?php

declare(strict_types=1);

namespace Modules\Course\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rewrites child positions (subjects of an offering, lessons of a subject)
 * from an ordered id list that must name exactly the current children.
 * The parent row is locked so concurrent reorders and inserts serialize.
 */
final class ReorderContent
{
    /**
     * @template TChild of Model
     * @template TParent of Model
     *
     * @param  HasMany<TChild, TParent>  $children
     * @param  list<int>  $orderedIds
     */
    public function handle(HasMany $children, array $orderedIds): void
    {
        DB::transaction(function () use ($children, $orderedIds): void {
            $parent = $children->getParent();
            $parent->newQuery()->whereKey($parent->getKey())->lockForUpdate()->value($parent->getKeyName());

            $currentIds = $children->getQuery()->pluck('id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
            $requestedIds = $orderedIds;
            sort($requestedIds);

            if ($currentIds !== $requestedIds) {
                throw ValidationException::withMessages(['ids' => __('course.validation.reorder_mismatch')]);
            }

            foreach ($orderedIds as $index => $id) {
                $children->getRelated()->newQuery()->whereKey($id)->update(['position' => $index + 1]);
            }
        });
    }
}
