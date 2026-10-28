<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;
use Modules\Notifications\Actions\MarkNotificationRead;

/** @var list<string> $argv */
require dirname(__DIR__, 4).'/vendor/autoload.php';
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::connection()->getDriverName() !== 'pgsql') {
    throw new RuntimeException('Race worker requires PostgreSQL in testing.');
}
$database = DB::selectOne('select current_database() as name')->name;
if (! in_array($database, ['shouman_backend_test_20261021', 'lms_test'], true)
    || $database !== getenv('DB_DATABASE')) {
    throw new RuntimeException('Race worker requires the dedicated disposable database.');
}
DB::select("select set_config('application_name', ?, false)", [$argv[3]]);
DB::statement("SET lock_timeout = '10s'");
Carbon::setTestNow($argv[4]);
$row = app(MarkNotificationRead::class)->handle(User::findOrFail($argv[1]), $argv[2]);
echo json_encode(['read_at' => $row->getAttribute('read_at')->toISOString(), 'updated_at' => $row->getAttribute('updated_at')->toISOString()], JSON_THROW_ON_ERROR);
