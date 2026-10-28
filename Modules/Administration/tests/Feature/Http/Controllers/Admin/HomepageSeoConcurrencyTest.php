<?php

declare(strict_types=1);

namespace Modules\Administration\Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Administration\Models\AuditLog;
use Modules\Administration\Models\HomepageSection;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Tests\TestCase;

class HomepageSeoConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private const array DISPOSABLE_DATABASES = ['shouman_backend_test_20261021', 'lms_test'];

    protected function setUp(): void
    {
        if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'pgsql'
            || ! in_array(getenv('DB_DATABASE'), self::DISPOSABLE_DATABASES, true)) {
            $this->markTestSkipped('Requires explicitly selected disposable PostgreSQL database in testing.');
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

    public function test_same_version_save_and_publish_cannot_both_succeed(): void
    {
        $user = User::factory()->withRole(Role::SystemAdministrator)->create();
        $token = $user->createToken('seo-race')->plainTextToken;
        $workers = [];
        $labels = ['inbox-race-'.Str::random(10), 'inbox-race-'.Str::random(10)];
        DB::beginTransaction();
        DB::table('homepage_sections')->where('id', 'seo')->lockForUpdate()->first();
        try {
            foreach ($labels as $index => $label) {
                $pipes = [];
                $process = proc_open([
                    PHP_BINARY, module_path('Administration', 'tests/Support/seo-worker.php'),
                    $token, $index === 0 ? 'save' : 'publish', $label,
                ], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                self::assertIsResource($process);
                $workers[] = [$process, $pipes];
            }
            $deadline = microtime(true) + 8;
            do {
                DB::select('select pg_stat_clear_snapshot()');
                $waiting = DB::select("select pid from pg_stat_activity where application_name in (?, ?) and wait_event_type = 'Lock'", $labels);
                if (count($waiting) === 2) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            self::assertCount(2, $waiting, 'Both independent workers must overlap while blocked on the parent-held row lock.');
            DB::commit();
            $results = [];
            foreach ($workers as [$process, $pipes]) {
                $output = stream_get_contents($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process), $error);
                $results[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
            }
            $workers = [];
            $statuses = array_column($results, 'status');
            sort($statuses);
            self::assertSame([200, 409], $statuses);
            self::assertSame(2, HomepageSection::findOrFail('seo')->version);
            self::assertSame(1, AuditLog::where('subject_id', 'seo')->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as [$process, $pipes]) {
                proc_terminate($process);
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                proc_close($process);
            }
        }
    }
}
