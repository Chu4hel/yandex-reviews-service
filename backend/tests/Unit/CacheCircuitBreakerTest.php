<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Services\CacheCircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheCircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_initial_state_is_closed_and_available(): void
    {
        $breaker = new CacheCircuitBreaker(failureThreshold: 3, cooldownSeconds: 60);

        $this->assertSame('closed', $breaker->getState('test_svc'));
        $this->assertTrue($breaker->isAvailable('test_svc'));
    }

    public function test_trips_to_open_after_reaching_failure_threshold(): void
    {
        $breaker = new CacheCircuitBreaker(failureThreshold: 3, cooldownSeconds: 60);

        $breaker->recordFailure('test_svc');
        $this->assertSame('closed', $breaker->getState('test_svc'));
        $this->assertTrue($breaker->isAvailable('test_svc'));

        $breaker->recordFailure('test_svc');
        $this->assertSame('closed', $breaker->getState('test_svc'));

        // 3-й сбой достигает порога
        $breaker->recordFailure('test_svc');
        $this->assertSame('open', $breaker->getState('test_svc'));
        $this->assertFalse($breaker->isAvailable('test_svc'));
    }

    public function test_success_resets_failures_and_keeps_closed(): void
    {
        $breaker = new CacheCircuitBreaker(failureThreshold: 3, cooldownSeconds: 60);

        $breaker->recordFailure('test_svc');
        $breaker->recordFailure('test_svc');
        $this->assertTrue($breaker->isAvailable('test_svc'));

        $breaker->recordSuccess('test_svc');
        $this->assertSame('closed', $breaker->getState('test_svc'));

        // После сброса требуется снова 3 сбоя для размыкания
        $breaker->recordFailure('test_svc');
        $this->assertSame('closed', $breaker->getState('test_svc'));
    }

    public function test_manual_reset_clears_open_state(): void
    {
        $breaker = new CacheCircuitBreaker(failureThreshold: 2, cooldownSeconds: 60);

        $breaker->recordFailure('test_svc');
        $breaker->recordFailure('test_svc');
        $this->assertSame('open', $breaker->getState('test_svc'));

        $breaker->reset('test_svc');
        $this->assertSame('closed', $breaker->getState('test_svc'));
        $this->assertTrue($breaker->isAvailable('test_svc'));
    }
}
