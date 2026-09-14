<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\Contracts\ProxyRotatorInterface;
use App\Domain\DTO\ProxyDto;
use App\Models\ProxyServer;
use Illuminate\Support\Facades\Log;

class DatabaseProxyRotator implements ProxyRotatorInterface
{
    /**
     * Получение следующего доступного прокси по алгоритму LRU (наименее недавно использованный).
     */
    public function getNextProxy(): ?ProxyDto
    {
        // Приоритет новым прокси без обращений, затем сортировка по давности использования
        $proxy = ProxyServer::available()
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

        // При 5 ошибках подряд отправляем прокси на 15-минутное охлаждение
        if ($newFails >= 5 && $newFails < 15) {
            $updates['cooldown_until'] = now()->addMinutes(15);
            Log::warning('DatabaseProxyRotator: временный карантин (5 ошибок подряд)', [
                'proxy_id' => $proxy->id,
                'host' => $proxy->host,
                'port' => $proxy->port,
                'fails' => $newFails,
            ]);
        } elseif ($newFails >= 15) {
            // При 15 ошибках подряд окончательно деактивируем сервер
            $updates['is_active'] = false;
            Log::error('DatabaseProxyRotator: прокси отключен из-за 15 ошибок подряд', [
                'proxy_id' => $proxy->id,
                'host' => $proxy->host,
                'port' => $proxy->port,
            ]);
        }

        $proxy->update($updates);
    }
}
