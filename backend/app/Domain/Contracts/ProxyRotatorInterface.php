<?php

declare(strict_types=1);

namespace App\Domain\Contracts;

use App\Domain\DTO\ProxyDto;

interface ProxyRotatorInterface
{
    /**
     * Get the next available proxy from the pool according to rotation policy (LRU / Round-Robin).
     * Returns null if no active proxies are configured or all are in cooldown.
     */
    public function getNextProxy(): ?ProxyDto;

    /**
     * Mark proxy as successfully used, record duration and reset fails counter.
     */
    public function markSuccess(int $proxyId, int $durationMs = 0): void;

    /**
     * Put proxy into quarantine cooldown (e.g. 30 minutes) due to captcha or rate limit.
     */
    public function markCaptcha(int $proxyId, int $cooldownMinutes = 30): void;

    /**
     * Record a network or HTTP error for the proxy.
     */
    public function markFailed(int $proxyId, string $errorMessage): void;
}
