<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test basic liveness health check endpoint.
     */
    public function test_health_check_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'service' => 'Yandex Reviews Service API',
            ])
            ->assertJsonStructure([
                'status',
                'service',
                'timestamp',
            ]);
    }

    /**
     * Test deep readiness probe for Kubernetes / SRE monitoring.
     */
    public function test_readiness_probe_returns_subsystem_health(): void
    {
        $response = $this->getJson('/api/health/ready');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'service',
                'timestamp',
                'checks' => [
                    'database' => [
                        'status',
                    ],
                    'queue' => [
                        'status',
                        'driver',
                        'pending_jobs',
                        'failed_jobs',
                    ],
                    'proxy_pool' => [
                        'status',
                        'total_configured',
                        'available_for_use',
                    ],
                ],
            ]);
    }

    /**
     * Test deep parameter on /api/health.
     */
    public function test_deep_parameter_invokes_readiness(): void
    {
        $response = $this->getJson('/api/health?deep=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'checks' => [
                    'database',
                    'queue',
                ],
            ]);
    }
}
