<?php

namespace Tests\Feature\Http\Middleware;

use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AssignRequestIdTest extends TestCase
{
    public function test_well_formed_incoming_id_is_propagated_to_response_and_context(): void
    {
        $this->withHeader('X-Request-Id', 'next-req_0123.abc')
            ->get('/health')
            ->assertOk()
            ->assertHeader('X-Request-Id', 'next-req_0123.abc');

        $this->assertSame('next-req_0123.abc', Context::get('request_id'));
    }

    #[TestWith(['short'])]
    #[TestWith(['has spaces in it'])]
    #[TestWith(["inject\nheader-value"])]
    public function test_malformed_incoming_id_is_replaced_with_a_uuid(string $incoming): void
    {
        $response = $this->withHeader('X-Request-Id', $incoming)->get('/health')->assertOk();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            (string) $response->headers->get('X-Request-Id'),
        );
    }

    public function test_missing_id_is_generated(): void
    {
        $response = $this->get('/health')->assertOk();

        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }
}
