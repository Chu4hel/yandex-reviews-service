<?php

declare(strict_types=1);

namespace App\Domain\DTO;

class ProxyPingResult
{
    public function __construct(
        public readonly bool $isSuccess,
        public readonly int $responseTimeMs,
        public readonly ?int $httpStatus = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $isCaptcha = false,
    ) {}

    public static function success(int $responseTimeMs, int $httpStatus = 200): self
    {
        return new self(
            isSuccess: true,
            responseTimeMs: $responseTimeMs,
            httpStatus: $httpStatus,
            errorMessage: null,
            isCaptcha: false,
        );
    }

    public static function captcha(int $responseTimeMs, int $httpStatus = 200): self
    {
        return new self(
            isSuccess: false,
            responseTimeMs: $responseTimeMs,
            httpStatus: $httpStatus,
            errorMessage: 'Обнаружена капча (SmartCaptcha) при тестовом пинге',
            isCaptcha: true,
        );
    }

    public static function failure(string $errorMessage, int $responseTimeMs = 0, ?int $httpStatus = null): self
    {
        return new self(
            isSuccess: false,
            responseTimeMs: $responseTimeMs,
            httpStatus: $httpStatus,
            errorMessage: $errorMessage,
            isCaptcha: false,
        );
    }
}
