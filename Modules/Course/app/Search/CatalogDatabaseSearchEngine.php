<?php

declare(strict_types=1);

namespace Modules\Course\Search;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use InvalidArgumentException;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Engines\DatabaseEngine;
use Modules\Course\Models\Offering;

/** Exposes Scout's SQL composition so the public API retains cursor pagination. */
final class CatalogDatabaseSearchEngine extends DatabaseEngine
{
    /**
     * The caller escapes PostgreSQL LIKE operators before constructing the Scout builder.
     * Visibility, ordering and bounded pagination remain the caller's responsibility.
     *
     * @param  ScoutBuilder<Offering>  $builder
     * @return EloquentBuilder<Offering>
     */
    public function queryFor(ScoutBuilder $builder): EloquentBuilder
    {
        if ($builder->callback !== null || $builder->queryCallback !== null || $builder->semanticSearch || $builder->hybridSearch !== null) {
            throw new InvalidArgumentException('Catalog SQL search supports plain text without callbacks only.');
        }

        return $this->buildSearchQuery($builder);
    }
}
