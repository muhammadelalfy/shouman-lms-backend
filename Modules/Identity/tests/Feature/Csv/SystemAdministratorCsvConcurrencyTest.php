<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Csv;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Actions\SyncUserRoles;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Tests\TestCase;

class SystemAdministratorCsvConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private const array DISPOSABLE_DATABASES = ['shouman_backend_test_20261021', 'lms_test'];

    protected function setUp(): void
    {
        if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'pgsql'
            || ! in_array(getenv('DB_DATABASE'), self::DISPOSABLE_DATABASES, true)) {
            $this->markTestSkipped('Requires explicitly selected disposable PostgreSQL database.');
        }
        parent::setUp();
    }

    protected function beforeRefreshingDatabase(): void
    {
        self::assertTrue($this->app->environment('testing'));
        self::assertSame('pgsql', DB::connection()->getDriverName());
        $database = DB::selectOne('select current_database() as name')->name;
        self::assertContains($database, self::DISPOSABLE_DATABASES);
        self::assertSame(getenv('DB_DATABASE'), $database);
    }

    public function test_admin_serialization_survives_nested_action_until_outer_commit(): void
    {
        $first = User::factory()->withRole(Role::SystemAdministrator)->create();
        $second = User::factory()->withRole(Role::SystemAdministrator)->create();
        $token = $second->createToken('concurrency-test')->plainTextToken;
        $label = 'identity-csv-'.Str::random(10);
        $process = null;
        $pipes = [];
        DB::beginTransaction();
        try {
            app(SyncUserRoles::class)->handle($first, $first, [Role::Student]);
            $process = proc_open([
                PHP_BINARY, __DIR__.'/identity-safeguard-worker.php', $token, (string) $second->id, $label,
            ], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            self::assertIsResource($process);
            $waiting = [];
            $deadline = microtime(true) + 4;
            do {
                DB::select('select pg_stat_clear_snapshot()');
                $waiting = DB::select("select pid from pg_stat_activity where application_name = ? and wait_event_type = 'Lock'", [$label]);
                if ($waiting !== []) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            self::assertCount(1, $waiting, 'An ordinary JSON role mutation must wait for the outer import transaction.');
            DB::commit();
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $pipes = [];
            self::assertSame(0, proc_close($process), $error);
            $process = null;
            self::assertSame(['status' => 409], json_decode($output, true, flags: JSON_THROW_ON_ERROR));
            self::assertSame(1, User::query()->active()->role(Role::SystemAdministrator)->count());
            self::assertTrue($second->fresh()?->hasRole(Role::SystemAdministrator));
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                proc_close($process);
            }
        }
    }
}
