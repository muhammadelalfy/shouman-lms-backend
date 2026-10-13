<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Readiness probe: can this instance serve traffic? Reports only pass/fail per
 * dependency, never connection details (CLAUDE.md §108). Liveness is /health.
 */
final class ReadinessController extends Controller
{
    public function __invoke(ConnectionInterface $database, CacheFactory $cache): JsonResponse
    {
        $checks = [
            'database' => $this->probe('database', fn () => $database->select('select 1')),
            'cache' => $this->probe('cache', fn () => $cache->store()->get('readiness-probe')),
        ];

        $ready = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $ready ? 'ready' : 'unavailable',
            'checks' => array_map(fn (bool $ok): string => $ok ? 'ok' : 'failing', $checks),
        ], $ready ? 200 : 503);
    }

    private function probe(string $dependency, callable $check): bool
    {
        try {
            $check();

            return true;
        } catch (Throwable $e) {
            Log::warning('Readiness check failed.', ['dependency' => $dependency, 'exception' => $e::class]);

            return false;
        }
    }
}

