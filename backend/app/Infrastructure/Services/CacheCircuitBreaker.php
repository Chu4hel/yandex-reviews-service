<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\Contracts\CircuitBreakerInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheCircuitBreaker implements CircuitBreakerInterface
{
    public function __construct(
        protected int $failureThreshold = 5,
        protected int $cooldownSeconds = 300
    ) {}

    public function isAvailable(string $serviceKey = 'yandex_maps'): bool
    {
        return $this->getState($serviceKey) !== 'open';
    }

    public function recordSuccess(string $serviceKey = 'yandex_maps'): void
    {
        $previousState = $this->getState($serviceKey);
        Cache::forget("cb_{$serviceKey}_failures");
        Cache::forget("cb_{$serviceKey}_open_until");

        if ($previousState !== 'closed') {
            Log::info("CircuitBreaker [{$serviceKey}]: служба успешно восстановила работоспособность, цепь замкнута (CLOSED).");
        }
    }

    public function recordFailure(string $serviceKey = 'yandex_maps'): void
    {
        $state = $this->getState($serviceKey);

        if ($state === 'half_open') {
            // Пробный запрос в режиме HALF_OPEN провалился — повторно размыкаем цепь
            $until = time() + $this->cooldownSeconds;
            Cache::put("cb_{$serviceKey}_open_until", $until, $this->cooldownSeconds + 60);

            Log::warning("CircuitBreaker [{$serviceKey}]: пробный запрос в режиме HALF_OPEN завершился сбоем. Цепь повторно разомкнута (OPEN) до ".date('H:i:s', $until).'.');

            return;
        }

        $failuresKey = "cb_{$serviceKey}_failures";
        $currentFailures = (int) Cache::get($failuresKey, 0) + 1;
        Cache::put($failuresKey, $currentFailures, $this->cooldownSeconds * 2);

        if ($currentFailures >= $this->failureThreshold) {
            $until = time() + $this->cooldownSeconds;
            Cache::put("cb_{$serviceKey}_open_until", $until, $this->cooldownSeconds + 60);

            Log::critical("CircuitBreaker [{$serviceKey}]: зафиксировано {$currentFailures} последовательных сбоев (порог: {$this->failureThreshold}). Предохранитель сработал, цепь РАЗОМКНУТА (OPEN) до ".date('H:i:s', $until).'.');
        }
    }

    public function getState(string $serviceKey = 'yandex_maps'): string
    {
        $openUntil = Cache::get("cb_{$serviceKey}_open_until");

        if ($openUntil === null) {
            return 'closed';
        }

        if (time() < (int) $openUntil) {
            return 'open';
        }

        return 'half_open';
    }

    public function reset(string $serviceKey = 'yandex_maps'): void
    {
        Cache::forget("cb_{$serviceKey}_failures");
        Cache::forget("cb_{$serviceKey}_open_until");
    }
}
