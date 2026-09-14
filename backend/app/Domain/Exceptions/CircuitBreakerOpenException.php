<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

class CircuitBreakerOpenException extends YandexParserException
{
    public function __construct(
        string $message = 'Запросы временно заблокированы предохранителем (Circuit Breaker OPEN) из-за серии последовательных блокировок Яндекс.Карт.',
        int $code = 503,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
