<?php

declare(strict_types=1);

namespace Modules\Administration\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Administration\Contracts\CsvResource;
use Modules\Administration\Csv\CsvCodec;
use Modules\Administration\Csv\CsvRegistry;
use Modules\Administration\Http\Controllers\Admin\CsvController;
use Modules\Identity\Models\User;
use Tests\TestCase;

final class CsvExportLimitTest extends TestCase
{
    public function test_over_limit_export_fails_before_returning_any_download_response(): void
    {
        $adapter = new class implements CsvResource
        {
            public function headers(): array
            {
                return ['id'];
            }

            public function authorize(User $actor, bool $import): void {}

            public function exportRows(User $actor, array $filters): iterable
            {
                for ($i = 1; $i <= 5001; $i++) {
                    yield ['id' => (string) $i];
                }
            }

            public function importRows(User $actor, array $rows): array
            {
                return ['created' => 0, 'updated' => 0];
            }
        };
        $registry = new CsvRegistry;
        $registry->register('offerings', fn (): CsvResource => $adapter);
        $request = Request::create('/csv');
        $request->setUserResolver(fn (): User => new User);
        $this->expectException(ValidationException::class);
        (new CsvController($registry, new CsvCodec))->export($request, 'offerings');
    }
}
