<?php

declare(strict_types=1);

namespace Modules\Administration\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Enums\Role;
use Modules\Identity\Events\RolePermissionsChanged;
use Modules\Identity\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class CsvPermissionIsolationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'pgsql'
            || ! in_array(getenv('DB_DATABASE'), ['shouman_backend_test_20261021', 'lms_test'], true)) {
            $this->markTestSkipped('Requires a disposable PostgreSQL database.');
        }
        parent::setUp();
    }

    protected function beforeRefreshingDatabase(): void
    {
        self::assertTrue($this->app->environment('testing'));
        self::assertSame('pgsql', DB::connection()->getDriverName());
        self::assertSame(getenv('DB_DATABASE'), DB::selectOne('select current_database() as name')->name);
        self::assertContains(getenv('DB_DATABASE'), ['shouman_backend_test_20261021', 'lms_test']);
    }

    public function test_tentative_role_grants_never_reach_another_process_through_shared_cache(): void
    {
        $actor = User::factory()->withRole(Role::SystemAdministrator)->create();
        $student = User::factory()->withRole(Role::Student)->create();
        $directory = sys_get_temp_dir().'/csv-permission-'.bin2hex(random_bytes(10));
        config(['cache.stores.csv_isolation' => ['driver' => 'file', 'path' => $directory], 'permission.cache.store' => 'csv_isolation']);
        $shared = app(PermissionRegistrar::class);
        $shared->initializeCache();
        self::assertFalse($student->checkPermissionTo('offerings.manage'));
        $observations = 0;
        $listener = function (RolePermissionsChanged $event) use ($student, $directory, &$observations): void {
            if ($event->roleName !== Role::Student->value) {
                return;
            }
            self::assertTrue($student->fresh()->checkPermissionTo('offerings.manage'));
            $pipes = [];
            $process = proc_open([PHP_BINARY, module_path('Administration', 'tests/Support/csv-permission-observer.php'), (string) $student->id, $directory], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            self::assertIsResource($process);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $error);
            self::assertSame(['allowed' => false], json_decode($output, true, flags: JSON_THROW_ON_ERROR));
            $observations++;
        };
        Event::listen(RolePermissionsChanged::class, $listener);
        try {
            Sanctum::actingAs($actor);
            $csv = "name,permissions\r\nstudent,\"[\"\"offerings.manage\"\"]\"\r\nunknown,[]\r\n";
            $this->post('/api/v1/admin/csv/roles/import', ['file' => UploadedFile::fake()->createWithContent('roles.csv', $csv)], ['Accept' => 'application/json'])->assertUnprocessable();
            self::assertSame(1, $observations);
            self::assertSame($shared, app(PermissionRegistrar::class));
            self::assertFalse($student->fresh()->checkPermissionTo('offerings.manage'));
        } finally {
            Event::forget(RolePermissionsChanged::class);
            config(['permission.cache.store' => 'array']);
            $shared->initializeCache();
            File::deleteDirectory($directory);
        }
    }
}
