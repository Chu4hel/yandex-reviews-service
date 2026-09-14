<?php

declare(strict_types=1);

namespace App\Domain\Contracts;

interface CircuitBreakerInterface
{
    /**
     * Проверить, доступна ли целевая служба для выполнения запросов.
     * Возвращает true, если состояние CLOSED или HALF_OPEN.
     * Возвращает false, если цепь разомкнута (OPEN) из-за серии сбоев.
     */
    public function isAvailable(string $serviceKey = 'yandex_maps'): bool;

    /**
     * Зафиксировать успешный ответ службы (сброс счетчика сбоев, перевод в CLOSED).
     */
    public function recordSuccess(string $serviceKey = 'yandex_maps'): void;

    /**
     * Зафиксировать сбой службы (увеличение счетчика сбоев, при превышении порога — перевод в OPEN).
     */
    public function recordFailure(string $serviceKey = 'yandex_maps'): void;

    /**
     * Получить текущее состояние предохранителя: 'closed', 'open', 'half_open'.
     */
    public function getState(string $serviceKey = 'yandex_maps'): string;

    /**
     * Принудительный сброс состояния предохранителя в CLOSED.
     */
    public function reset(string $serviceKey = 'yandex_maps'): void;
}
