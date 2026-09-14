<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class RequestIdTest extends TestCase
{
    public function test_api_generates_request_id_if_missing(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertHeader('X-Request-ID');

        $requestId = $response->headers->get('X-Request-ID');
        $this->assertIsString($requestId);
        $this->assertNotEmpty($requestId);
    }

    public function test_api_propagates_existing_request_id(): void
    {
        $customId = 'trace-client-uuid-987654321';

        $response = $this->withHeaders([
            'X-Request-ID' => $customId,
        ])->getJson('/api/health');

        $response->assertOk();
        $response->assertHeader('X-Request-ID', $customId);
    }

    public function test_error_response_contains_request_id_header(): void
    {
        $customId = 'failed-req-trace-456';

        $response = $this->withHeaders([
            'X-Request-ID' => $customId,
        ])->getJson('/api/admin/proxies');

        $response->assertForbidden();
        $response->assertHeader('X-Request-ID', $customId);
    }
}
