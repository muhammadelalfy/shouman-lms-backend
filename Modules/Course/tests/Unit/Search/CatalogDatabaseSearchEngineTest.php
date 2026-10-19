<?php

declare(strict_types=1);

namespace Modules\Course\Tests\Unit\Search;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Scout\Engines\DatabaseEngine;
use Modules\Course\Models\Offering;
use Modules\Course\Search\CatalogDatabaseSearchEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogDatabaseSearchEngineTest extends TestCase
{
    public function test_fresh_model_exposes_only_the_three_text_fields_including_nulls(): void
    {
        $this->assertSame(['title' => null, 'summary' => null, 'description' => null], (new Offering)->toSearchableArray());
        $populated = (new Offering)->forceFill(['id' => 123, 'title' => 'Public title', 'summary' => null, 'description' => 'Public description', 'slug' => 'private-slug', 'goals' => ['private']]);
        $this->assertSame(['title' => 'Public title', 'summary' => null, 'description' => 'Public description'], $populated->toSearchableArray());
        config(['scout.driver' => 'algolia']);
        $this->assertInstanceOf(DatabaseEngine::class, (new Offering)->searchableUsing());
    }

    #[DataProvider('unsupportedModes')]
    public function test_adapter_rejects_callbacks_and_non_text_modes(string $mode): void
    {
        $builder = Offering::search('needle');
        $builder->{$mode} = match ($mode) {
            'semanticSearch' => true,
            'hybridSearch' => ['text' => 1, 'semantic' => 1],
            default => static fn () => null,
        };
        $this->expectException(InvalidArgumentException::class);
        (new CatalogDatabaseSearchEngine)->queryFor($builder);
    }

    /** @return array<string, array{string}> */
    public static function unsupportedModes(): array
    {
        return ['search callback' => ['callback'], 'query callback' => ['queryCallback'], 'semantic' => ['semanticSearch'], 'hybrid' => ['hybridSearch']];
    }

    public function test_adapter_returns_a_bound_unexecuted_sql_query_for_later_cursor_pagination(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $query = (new CatalogDatabaseSearchEngine)->queryFor(Offering::search('needle'));
        $this->assertSame([], DB::getQueryLog());
        $this->assertSame(['%needle%', '%needle%', '%needle%'], $query->getBindings());
        $this->assertSame(['title', 'summary', 'description'], array_keys($query->getModel()->toSearchableArray()));
        $this->assertNull($query->getQuery()->limit);
        $this->assertNull($query->getQuery()->orders);
        DB::disableQueryLog();
    }
}
