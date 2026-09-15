<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\Contracts\ProxyCheckerInterface;
use App\Domain\Contracts\ProxyRotatorInterface;
use App\Domain\DTO\ProxyDto;
use App\Models\ProxyServer;
use Illuminate\Support\Facades\Log;

class DatabaseProxyRotator implements ProxyRotatorInterface
{
    public function __construct(
        protected ?ProxyCheckerInterface $checker = null
    ) {}

    /**
     * Получение следующего доступного прокси с приоритетом здоровых и быстрых серверов (Health-Aware LRU).
     */
    public function getNextProxy(): ?ProxyDto
    {
        // Сначала прокси без ошибок (fails_count ASC), затем более быстрые (avg_response_time_ms ASC), затем LRU (last_used_at ASC)
        $proxy = ProxyServer::available()
            ->orderBy('fails_count', 'asc')
            ->orderByRaw('CASE WHEN avg_response_time_ms IS NULL THEN 99999 ELSE avg_response_time_ms END ASC')
            ->orderByRaw('CASE WHEN last_used_at IS NULL THEN 0 ELSE 1 END, last_used_at ASC')
            ->first();

        if (! $proxy) {
            return null;
        }

        $proxy->update([
            'last_used_at' => now(),
        ]);

        return $proxy->toDto();
    }

    /**
     * Фиксация успешного запроса и обновление скользящей задержки отклика.
     */
    public function markSuccess(int $proxyId, int $durationMs = 0): void
    {
        $proxy = ProxyServer::find($proxyId);
        if (! $proxy) {
            return;
        }

        $newAvg = $durationMs > 0
            ? ($proxy->avg_response_time_ms ? (int) round(($proxy->avg_response_time_ms * 0.7) + ($durationMs * 0.3)) : $durationMs)
            : $proxy->avg_response_time_ms;

        $proxy->update([
            'success_count' => $proxy->success_count + 1,
            'fails_count' => 0,
            'last_error' => null,
            'avg_response_time_ms' => $newAvg,
        ]);
    }

    /**
     * Отправка прокси в карантин при обнаружении капчи.
     */
    public function markCaptcha(int $proxyId, int $cooldownMinutes = 30): void
    {
        $proxy = ProxyServer::find($proxyId);
        if (! $proxy) {
            return;
        }

        $cooldownUntil = now()->addMinutes($cooldownMinutes);

        $proxy->update([
            'fails_count' => $proxy->fails_count + 1,
            'cooldown_until' => $cooldownUntil,
            'last_error' => "Обнаружена капча: прокси отправлен в карантин до {$cooldownUntil->format('H:i:s d.m.Y')}",
        ]);

        Log::warning('DatabaseProxyRotator: прокси отправлен в карантин из-за капчи', [
            'proxy_id' => $proxy->id,
            'host' => $proxy->host,
            'port' => $proxy->port,
            'cooldown_until' => $cooldownUntil->toIso8601String(),
        ]);
    }

    /**
     * Учет сбоя подключения или ошибки парсинга.
     */
    public function markFailed(int $proxyId, string $errorMessage): void
    {
        $proxy = ProxyServer::find($proxyId);
        if (! $proxy) {
            return;
        }

        $newFails = $proxy->fails_count + 1;
        $updates = [
            'fails_count' => $newFails,
            'last_error' => mb_substr($errorMessage, 0, 500),
        ];

        // При 1-2 ошибках подряд отправляем прокси на 10-минутный карантин, чтобы не задерживать другие запросы
        if ($newFails >= 1 && $newFails < 3) {
            $updates['cooldown_until'] = now()->addMinutes(10);
            Log::warning('DatabaseProxyRotator: временный карантин (сбой подключения через прокси)', [
                'proxy_id' => $proxy->id,
                'host' => $proxy->host,
                'port' => $proxy->port,
                'fails' => $newFails,
            ]);
        } elseif ($newFails >= 3) {
            // При 3 ошибках подряд окончательно отключаем прокси
            $updates['is_active'] = false;
            Log::error('DatabaseProxyRotator: прокси отключен из-за 3 последовательных ошибок', [
                'proxy_id' => $proxy->id,
                'host' => $proxy->host,
                'port' => $proxy->port,
            ]);
        }

        $proxy->update($updates);
    }

    /**
     * Выполнить параллельную проверку доступности пула прокси с обновлением их статусов в базе данных.
     *
     * @return array{total: int, active: int, disabled: int, captcha: int, duration_ms: int}
     */
    public function checkPool(int $timeoutSeconds = 6, bool $onlyActive = true): array
    {
        $startTime = microtime(true);
        $query = ProxyServer::query();
        if ($onlyActive) {
            $query->where('is_active', true);
        }
        $proxies = $query->get();

        $checker = $this->checker ?? app(ProxyCheckerInterface::class);
        $total = $proxies->count();
        $activeCount = 0;
        $disabledCount = 0;
        $captchaCount = 0;

        if ($total > 0) {
            // Проверка пачками по 30 штук для предотвращения переполнения ресурсов пула
            $chunks = $proxies->chunk(30);
            foreach ($chunks as $chunk) {
                $configs = [];
                $chunkProxies = [];
                $idx = 0;
                foreach ($chunk as $proxy) {
                    $configs[$idx] = [
                        'protocol' => $proxy->protocol,
                        'host' => $proxy->host,
                        'port' => $proxy->port,
                        'username' => $proxy->username,
                        'password' => $proxy->password,
                    ];
                    $chunkProxies[$idx] = $proxy;
                    $idx++;
                }

                $results = $checker->pingMany($configs, $timeoutSeconds);

                foreach ($chunkProxies as $index => $proxy) {
                    $result = $results[$index] ?? null;
                    if ($result !== null && $result->isSuccess) {
                        $proxy->update([
                            'is_active' => true,
                            'fails_count' => 0,
                            'cooldown_until' => null,
                            'last_error' => null,
                            'avg_response_time_ms' => $result->responseTimeMs,
                        ]);
                        $activeCount++;
                    } elseif ($result !== null && $result->isCaptcha) {
                        $proxy->update([
                            'is_active' => true,
                            'fails_count' => 1,
                            'cooldown_until' => now()->addMinutes(30),
                            'last_error' => 'Карантин: обнаружена капча при проверке пула',
                            'avg_response_time_ms' => $result->responseTimeMs,
                        ]);
                        $captchaCount++;
                    } else {
                        $errorMsg = $result ? $result->errorMessage : 'Нет ответа от прокси';
                        $proxy->update([
                            'is_active' => false,
                            'fails_count' => $proxy->fails_count + 1,
                            'last_error' => 'Проверка пула не удалась: '.mb_substr((string) $errorMsg, 0, 450),
                        ]);
                        $disabledCount++;
                    }
                }
            }
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'total' => $total,
            'active' => $activeCount,
            'disabled' => $disabledCount,
            'captcha' => $captchaCount,
            'duration_ms' => $durationMs,
        ];
    }
}
