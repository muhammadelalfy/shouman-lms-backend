<?php

namespace Tests\Feature\Http\Controllers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ReadinessControllerTest extends TestCase
{
    public function test_returns_200_when_database_and_cache_respond(): void
    {
        $this->getJson('/ready')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ready',
                'checks' => ['database' => 'ok', 'cache' => 'ok'],
            ]);
    }

    public function test_returns_503_without_leaking_details_when_database_is_unreachable(): void
    {
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.host' => '127.0.0.1',
            'database.connections.pgsql.port' => 1,
        ]);
        DB::purge('pgsql');
        $this->mock(ConnectionInterface::class)->shouldNotReceive('select');

        $this->getJson('/ready')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'unavailable',
                'checks' => ['database' => 'failing', 'cache' => 'ok'],
            ]);
    }

    public function test_returns_503_when_postgres_port_is_open_but_sql_fails(): void
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        self::assertIsResource($listener);
        $address = stream_socket_get_name($listener, false);
        self::assertIsString($address);
        $port = (int) substr($address, strrpos($address, ':') + 1);
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.host' => '127.0.0.1',
            'database.connections.pgsql.port' => $port,
        ]);
        $this->mock(ConnectionInterface::class)
            ->shouldReceive('select')
            ->once()
            ->with('select 1')
            ->andThrow(new RuntimeException('Query failed.'));

        try {
            $this->getJson('/ready')
                ->assertStatus(503)
                ->assertExactJson([
                    'status' => 'unavailable',
                    'checks' => ['database' => 'failing', 'cache' => 'ok'],
                ]);
        } finally {
            fclose($listener);
        }
    }
}
