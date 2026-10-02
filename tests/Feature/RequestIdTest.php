<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_receive_a_request_id_response_header(): void
    {
        $response = $this->get('/');

        $requestId = $response->headers->get('X-Request-ID');

        $this->assertNotNull($requestId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $requestId
        );
    }

    public function test_valid_request_id_is_preserved(): void
    {
        $response = $this->withHeader('X-Request-ID', 'core-test-123')->get('/');

        $this->assertSame('core-test-123', $response->headers->get('X-Request-ID'));
    }

    public function test_invalid_request_id_is_replaced(): void
    {
        $response = $this->withHeader('X-Request-ID', str_repeat('x', 129))->get('/');

        $this->assertNotSame(str_repeat('x', 129), $response->headers->get('X-Request-ID'));
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
    }
}
