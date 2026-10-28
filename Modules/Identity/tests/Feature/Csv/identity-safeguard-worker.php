<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** @var list<string> $argv */
$disposableDatabases = ['shouman_backend_test_20261021', 'lms_test'];
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'pgsql'
    || ! in_array(getenv('DB_DATABASE'), $disposableDatabases, true)) {
    throw new RuntimeException('Identity worker requires an explicitly selected disposable PostgreSQL database.');
}
require dirname(__DIR__, 5).'/vendor/autoload.php';
$app = require dirname(__DIR__, 5).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::connection()->getDriverName() !== 'pgsql') {
    throw new RuntimeException('Identity worker requires the explicitly selected disposable PostgreSQL database.');
}
$database = DB::selectOne('select current_database() as name')->name;
if (! in_array($database, $disposableDatabases, true) || $database !== getenv('DB_DATABASE')) {
    throw new RuntimeException('Identity worker requires the explicitly selected disposable PostgreSQL database.');
}
DB::select("select set_config('application_name', ?, false)", [$argv[3]]);
DB::statement("SET lock_timeout = '10s'");
$request = Request::create('/api/v1/admin/users/'.$argv[2].'/roles', 'PUT', server: [
    'HTTP_AUTHORIZATION' => 'Bearer '.$argv[1], 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
], content: '{"roles":["student"]}');
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
echo json_encode(['status' => $response->getStatusCode()], JSON_THROW_ON_ERROR);
$kernel->terminate($request, $response);
