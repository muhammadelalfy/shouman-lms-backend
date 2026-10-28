<?php

declare(strict_types=1);

namespace Modules\Administration\Contracts;

use Modules\Identity\Models\User;

interface CsvResource
{
    /** @return list<string> */
    public function headers(): array;

    public function authorize(User $actor, bool $import): void;

    /**
     * @param  array<string, mixed>  $filters
     * @return iterable<array<string, string|null>>
     */
    public function exportRows(User $actor, array $filters): iterable;

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{created: int, updated: int}
     */
    public function importRows(User $actor, array $rows): array;
}
