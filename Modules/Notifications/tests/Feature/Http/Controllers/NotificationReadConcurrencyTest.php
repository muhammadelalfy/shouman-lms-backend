<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Models\User;
use Tests\TestCase;

class NotificationReadConcurrencyTest extends TestCase
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

    public function test_overlapping_connections_preserve_first_read_timestamp(): void
    {
        $user = User::factory()->create();
        $row = $user->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => 'synthetic-race-only',
            'data' => ['schema_version' => 1, 'title' => 'عنوان', 'body' => 'نص'],
        ]);
        $workers = [];
        $labels = ['inbox-race-'.Str::random(10), 'inbox-race-'.Str::random(10)];
        DB::beginTransaction();
        DB::table('notifications')->where('id', $row->id)->lockForUpdate()->first();
        try {
            foreach ($labels as $index => $label) {
                $pipes = [];
                $process = proc_open([
                    PHP_BINARY, module_path('Notifications', 'tests/Support/read-worker.php'),
                    (string) $user->id, $row->id, $label, '2026-10-21 09:0'.$index.':00',
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
            self::assertSame($results[0], $results[1]);
            self::assertContains($results[0]['read_at'], ['2026-10-21T09:00:00.000000Z', '2026-10-21T09:01:00.000000Z']);
            self::assertSame($results[0]['read_at'], $row->refresh()->getAttribute('read_at')->toISOString());
            self::assertSame($results[0]['updated_at'], $row->refresh()->getAttribute('updated_at')->toISOString());
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
