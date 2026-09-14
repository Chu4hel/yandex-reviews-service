<?php

declare(strict_types=1);

namespace App\Domain\Contracts;

use App\Domain\DTO\ProxyDto;

interface ProxyRotatorInterface
{
    /**
     * Получить следующий доступный прокси из пула согласно политике ротации (LRU / Round-Robin).
     * Возвращает null, если активные прокси не настроены или все находятся в режиме карантина.
     */
    public function getNextProxy(): ?ProxyDto;

    /**
     * Зафиксировать успешное использование прокси, сохранить время задержки и сбросить счетчик ошибок.
     */
    public function markSuccess(int $proxyId, int $durationMs = 0): void;

    /**
     * Отправить прокси на карантин (cooldown) из-за детекции капчи или превышения лимитов запросов.
     */
    public function markCaptcha(int $proxyId, int $cooldownMinutes = 30): void;

    /**
     * Зафиксировать сетевую или HTTP-ошибку соединения через прокси.
     */
    public function markFailed(int $proxyId, string $errorMessage): void;
}
