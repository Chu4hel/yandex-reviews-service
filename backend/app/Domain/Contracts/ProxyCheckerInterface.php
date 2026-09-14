<?php

declare(strict_types=1);

namespace App\Domain\Contracts;

use App\Domain\DTO\ProxyPingResult;

interface ProxyCheckerInterface
{
    /**
     * Выполнить тестовый пинг-запрос через указанный прокси-сервер.
     *
     * @param array{protocol: string, host: string, port: int, username: ?string, password: ?string} $proxyConfig
     */
    public function ping(array $proxyConfig, int $timeoutSeconds = 5): ProxyPingResult;

    /**
     * Выполнить параллельный тестовый пинг-запрос для нескольких прокси-серверов.
     *
     * @param list<array{protocol: string, host: string, port: int, username: ?string, password: ?string}> $proxyConfigs
     * @return array<int, ProxyPingResult>
     */
    public function pingMany(array $proxyConfigs, int $timeoutSeconds = 5): array;
}
