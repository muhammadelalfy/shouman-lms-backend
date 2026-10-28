<?php

declare(strict_types=1);

namespace Modules\Administration\Csv;

use Closure;
use Modules\Administration\Contracts\CsvResource;

final class CsvRegistry
{
    /** @var array<string, Closure(): CsvResource> */
    private array $factories = [];

    /** @param Closure(): CsvResource $factory */
    public function register(string $resource, Closure $factory): void
    {
        $this->factories[$resource] = $factory;
    }

    public function get(string $resource): CsvResource
    {
        abort_unless(isset($this->factories[$resource]), 404);

        return ($this->factories[$resource])();
    }
}
