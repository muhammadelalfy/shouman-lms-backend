<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** @var list<string> $argv */
require dirname(__DIR__, 4).'/vendor/autoload.php';
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::connection()->getDriverName() !== 'pgsql'
    || ! in_array(DB::selectOne('select current_database() as name')->name, ['shouman_backend_test_20261021', 'lms_test'], true)) {
    throw new RuntimeException('FAQ worker requires disposable PostgreSQL testing database.');
}
DB::select("select set_config('application_name', ?, false)", [$argv[3]]);
DB::statement("SET lock_timeout = '10s'");
$publish = $argv[2] === 'publish';
$body = ['version' => 1];
if (! $publish) {
    $body['draft'] = ['enabled' => true, 'title' => 'نص اختبار التزامن', 'items' => []];
}
$request = Request::create('/api/v1/admin/homepage/faq'.($publish ? '/publish' : ''), $publish ? 'POST' : 'PUT', server: [
    'HTTP_AUTHORIZATION' => 'Bearer '.$argv[1], 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
], content: json_encode($body, JSON_THROW_ON_ERROR));
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
echo json_encode(['status' => $response->getStatusCode()], JSON_THROW_ON_ERROR);
$kernel->terminate($request, $response);
