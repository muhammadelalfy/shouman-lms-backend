<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;
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
            'database' => $this->probe('database', function () use ($database): void {
                if (! $this->postgresListenerReachable()) {
                    throw new RuntimeException('PostgreSQL listener unavailable.');
                }

                $database->select('select 1');
            }),
            'cache' => $this->probe('cache', fn () => $cache->store()->get('readiness-probe')),
        ];

        $ready = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $ready ? 'ready' : 'unavailable',
            'checks' => array_map(fn (bool $ok): string => $ok ? 'ok' : 'failing', $checks),
        ], $ready ? 200 : 503);
    }

    private function postgresListenerReachable(): bool
    {
        if (config('database.default') !== 'pgsql' || config('database.connections.pgsql.url')) {
            return true;
        }

        $host = config('database.connections.pgsql.host');
        $port = config('database.connections.pgsql.port');

        if (! is_string($host) || preg_match('/^[a-z0-9.-]+$/i', $host) !== 1 || ! is_numeric($port)) {
            return true;
        }

        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errorCode, $errorMessage, 1.0);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
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
