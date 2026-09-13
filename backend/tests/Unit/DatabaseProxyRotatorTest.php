<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Services\DatabaseProxyRotator;
use App\Models\ProxyServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseProxyRotatorTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseProxyRotator $rotator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rotator = new DatabaseProxyRotator;
    }

    public function test_returns_null_when_no_proxies_configured(): void
    {
        $this->assertNull($this->rotator->getNextProxy());
    }

    public function test_rotates_proxies_using_least_recently_used_order(): void
    {
        $p1 = ProxyServer::create([
            'protocol' => 'http',
            'host' => '1.1.1.1',
            'port' => 8080,
            'is_active' => true,
            'last_used_at' => now()->subMinutes(10),
        ]);

        $p2 = ProxyServer::create([
            'protocol' => 'http',
            'host' => '2.2.2.2',
            'port' => 8080,
            'is_active' => true,
            'last_used_at' => null, // Never used, higher priority
        ]);

        // First call should pick p2 because it was never used
        $chosen1 = $this->rotator->getNextProxy();
        $this->assertNotNull($chosen1);
        $this->assertSame($p2->id, $chosen1->id);

        // Next call should pick p1 (used 10 min ago vs p2 used just now)
        $chosen2 = $this->rotator->getNextProxy();
        $this->assertNotNull($chosen2);
        $this->assertSame($p1->id, $chosen2->id);
    }

    public function test_mark_success_updates_metrics_and_resets_fails(): void
    {
        $proxy = ProxyServer::create([
            'protocol' => 'http',
            'host' => '1.1.1.1',
            'port' => 8080,
            'is_active' => true,
            'fails_count' => 3,
            'success_count' => 10,
            'last_error' => 'Previous timeout',
        ]);

        $this->rotator->markSuccess($proxy->id, 250);

        $fresh = $proxy->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(11, $fresh->success_count);
        $this->assertSame(0, $fresh->fails_count);
        $this->assertNull($fresh->last_error);
        $this->assertSame(250, $fresh->avg_response_time_ms);
    }

    public function test_mark_captcha_puts_proxy_into_cooldown_and_skips_it(): void
    {
        $proxy = ProxyServer::create([
            'protocol' => 'http',
            'host' => '1.1.1.1',
            'port' => 8080,
            'is_active' => true,
        ]);

        $this->rotator->markCaptcha($proxy->id, 30);

        $fresh = $proxy->fresh();
        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->isCoolingDown());
        $this->assertSame(1, $fresh->fails_count);
        $this->assertStringContainsString('капча', (string) $fresh->last_error);

        // getNextProxy should return null because only proxy is cooling down
        $this->assertNull($this->rotator->getNextProxy());
    }

    public function test_mark_failed_puts_into_quarantine_after_5_consecutive_errors(): void
    {
        $proxy = ProxyServer::create([
            'protocol' => 'http',
            'host' => '1.1.1.1',
            'port' => 8080,
            'is_active' => true,
            'fails_count' => 4,
        ]);

        $this->rotator->markFailed($proxy->id, 'Connection timeout');

        $fresh = $proxy->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(5, $fresh->fails_count);
        $this->assertTrue($fresh->isCoolingDown());
    }

    public function test_mark_failed_deactivates_proxy_after_15_consecutive_errors(): void
    {
        $proxy = ProxyServer::create([
            'protocol' => 'http',
            'host' => '1.1.1.1',
            'port' => 8080,
            'is_active' => true,
            'fails_count' => 14,
        ]);

        $this->rotator->markFailed($proxy->id, 'Host unreachable');

        $fresh = $proxy->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(15, $fresh->fails_count);
        $this->assertFalse($fresh->is_active);
    }
}
