<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;
use Spatie\Permission\PermissionRegistrar;

/** @var list<string> $argv */
require dirname(__DIR__, 4).'/vendor/autoload.php';
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::connection()->getDriverName() !== 'pgsql'
    || ! in_array(getenv('DB_DATABASE'), ['shouman_backend_test_20261021', 'lms_test'], true)
    || DB::selectOne('select current_database() as name')->name !== getenv('DB_DATABASE')) {
    throw new RuntimeException('CSV observer requires a disposable testing database.');
}
config(['cache.stores.csv_isolation' => ['driver' => 'file', 'path' => $argv[2]], 'permission.cache.store' => 'csv_isolation']);
app(PermissionRegistrar::class)->initializeCache();
$user = User::findOrFail((int) $argv[1]);
echo json_encode(['allowed' => $user->checkPermissionTo('offerings.manage')], JSON_THROW_ON_ERROR);
