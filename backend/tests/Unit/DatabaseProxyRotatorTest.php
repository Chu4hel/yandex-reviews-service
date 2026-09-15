<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Contracts\ProxyCheckerInterface;
use App\Domain\DTO\ProxyPingResult;
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

    public function test_mark_failed_puts_into_quarantine_after_first_error(): void
    {
        $proxy = ProxyServer::create([
            'protocol' => 'http',
            'host' => '1.1.1.1',
            'port' => 8080,
            'is_active' => true,
            'fails_count' => 0,
        ]);

        $this->rotator->markFailed($proxy->id, 'Connection timeout');

        $fresh = $proxy->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(1, $fresh->fails_count);
        $this->assertTrue($fresh->isCoolingDown());
    }

    public function test_mark_failed_deactivates_proxy_after_3_consecutive_errors(): void
    {
        $proxy = ProxyServer::create([
            'protocol' => 'http',
            'host' => '1.1.1.1',
            'port' => 8080,
            'is_active' => true,
            'fails_count' => 2,
        ]);

        $this->rotator->markFailed($proxy->id, 'Host unreachable');

        $fresh = $proxy->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(3, $fresh->fails_count);
        $this->assertFalse($fresh->is_active);
    }

    public function test_health_aware_selection_prioritizes_healthy_and_fast_proxies(): void
    {
        // Прокси с 1 ошибкой (не в кулдауне, например кулдаун истек)
        $pError = ProxyServer::create([
            'protocol' => 'http',
            'host' => '1.1.1.1',
            'port' => 8080,
            'is_active' => true,
            'fails_count' => 1,
            'avg_response_time_ms' => 50,
            'cooldown_until' => now()->subMinute(),
        ]);

        // Здоровый прокси, но медленный
        $pSlow = ProxyServer::create([
            'protocol' => 'http',
            'host' => '2.2.2.2',
            'port' => 8080,
            'is_active' => true,
            'fails_count' => 0,
            'avg_response_time_ms' => 500,
        ]);

        // Здоровый и быстрый прокси
        $pFast = ProxyServer::create([
            'protocol' => 'http',
            'host' => '3.3.3.3',
            'port' => 8080,
            'is_active' => true,
            'fails_count' => 0,
            'avg_response_time_ms' => 80,
        ]);

        $chosen = $this->rotator->getNextProxy();
        $this->assertNotNull($chosen);
        $this->assertSame($pFast->id, $chosen->id);
    }

    public function test_check_pool_updates_proxies_and_returns_stats(): void
    {
        $p1 = ProxyServer::create([
            'protocol' => 'http',
            'host' => '10.0.0.1',
            'port' => 8080,
            'is_active' => true,
        ]);

        $p2 = ProxyServer::create([
            'protocol' => 'http',
            'host' => '10.0.0.2',
            'port' => 8080,
            'is_active' => true,
        ]);

        $mockChecker = $this->createMock(ProxyCheckerInterface::class);
        $mockChecker->expects($this->once())
            ->method('pingMany')
            ->willReturn([
                0 => ProxyPingResult::success(120, 200),
                1 => ProxyPingResult::failure('Connection refused', 500),
            ]);

        $rotator = new DatabaseProxyRotator($mockChecker);
        $stats = $rotator->checkPool(6, true);

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['active']);
        $this->assertSame(1, $stats['disabled']);

        $fresh1 = $p1->fresh();
        $this->assertTrue($fresh1->is_active);
        $this->assertSame(120, $fresh1->avg_response_time_ms);

        $fresh2 = $p2->fresh();
        $this->assertFalse($fresh2->is_active);
        $this->assertStringContainsString('Connection refused', (string) $fresh2->last_error);
    }
}
