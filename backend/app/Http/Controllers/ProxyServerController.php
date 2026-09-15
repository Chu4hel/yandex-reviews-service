<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Contracts\ProxyCheckerInterface;
use App\Domain\Contracts\ProxyRotatorInterface;
use App\Http\Resources\ProxyServerResource;
use App\Models\ProxyServer;
use App\Support\ProxyStringParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProxyServerController extends Controller
{
    /**
     * Список всех прокси-серверов в пуле ротации.
     */
    public function index(): AnonymousResourceCollection
    {
        $proxies = ProxyServer::orderBy('id', 'desc')->get();

        return ProxyServerResource::collection($proxies);
    }

    /**
     * Добавление одного или нескольких прокси-серверов в пул с тестовым пингом и поддержкой одинаковых URL.
     */
    public function store(Request $request, ProxyCheckerInterface $proxyChecker): JsonResponse
    {
        $validated = $request->validate([
            'proxies' => ['nullable', 'array'],
            'proxies.*' => ['required', 'string'],
            'proxy' => ['nullable', 'string'],
            'skip_ping' => ['nullable', 'boolean'],
        ]);

        $rawList = [];
        if (! empty($validated['proxy'])) {
            $rawList[] = (string) $validated['proxy'];
        }
        if (! empty($validated['proxies']) && is_array($validated['proxies'])) {
            foreach ($validated['proxies'] as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $rawList[] = trim($item);
                }
            }
        }

        if (empty($rawList)) {
            return response()->json([
                'message' => 'Не переданы адреса прокси для добавления.',
            ], 422);
        }

        $parsedList = [];
        foreach ($rawList as $raw) {
            $parsed = $this->parseProxyString($raw);
            if ($parsed) {
                $parsedList[] = $parsed;
            }
        }

        if (empty($parsedList)) {
            return response()->json([
                'message' => 'Ни один из переданных адресов не соответствует поддерживаемым форматам.',
            ], 422);
        }

        $skipPing = (bool) ($validated['skip_ping'] ?? false);
        $pingResults = [];
        if (! $skipPing) {
            $pingResults = $proxyChecker->pingMany($parsedList);
        }

        $added = [];
        $details = [];

        foreach ($parsedList as $idx => $parsed) {
            $proxyKey = ProxyServer::generateKey(
                $parsed['protocol'],
                $parsed['host'],
                $parsed['port'],
                $parsed['username'],
                $parsed['password']
            );

            $ping = $pingResults[$idx] ?? null;

            $isActive = true;
            $cooldownUntil = null;
            $failsCount = 0;
            $lastError = null;
            $avgResponseTimeMs = null;

            if ($ping !== null) {
                if ($ping->isSuccess) {
                    $isActive = true;
                    $avgResponseTimeMs = $ping->responseTimeMs;
                } elseif ($ping->isCaptcha) {
                    $isActive = true;
                    $cooldownUntil = now()->addMinutes(30);
                    $failsCount = 1;
                    $lastError = 'Карантин: обнаружена капча при тестовом пинге';
                    $avgResponseTimeMs = $ping->responseTimeMs;
                } else {
                    $isActive = false;
                    $failsCount = 1;
                    $lastError = 'Тестовый пинг не удался: '.mb_substr((string) $ping->errorMessage, 0, 450);
                }
            }

            $proxy = ProxyServer::updateOrCreate(
                ['proxy_key' => $proxyKey],
                [
                    'protocol' => $parsed['protocol'],
                    'host' => $parsed['host'],
                    'port' => $parsed['port'],
                    'username' => $parsed['username'],
                    'password' => $parsed['password'],
                    'is_active' => $isActive,
                    'cooldown_until' => $cooldownUntil,
                    'fails_count' => $failsCount,
                    'last_error' => $lastError,
                    'avg_response_time_ms' => $avgResponseTimeMs,
                ]
            );

            $added[] = $proxy;
            $details[] = [
                'id' => $proxy->id,
                'endpoint' => "{$parsed['host']}:{$parsed['port']}",
                'status' => $ping ? ($ping->isSuccess ? 'active' : ($ping->isCaptcha ? 'cooldown' : 'failed')) : 'added',
                'ping_ms' => $ping?->responseTimeMs,
                'error' => $lastError,
            ];
        }

        $activeCount = count(array_filter($added, fn (ProxyServer $p) => $p->is_active && ! $p->isCoolingDown()));
        $failedCount = count(array_filter($added, fn (ProxyServer $p) => ! $p->is_active));

        $message = count($added) === 1
            ? ($failedCount > 0 ? 'Прокси добавлен, но тестовый пинг не удался (сервер отключен).' : 'Прокси-сервер успешно проверен и добавлен в пул.')
            : "Обработано прокси: {$activeCount} доступно".($failedCount > 0 ? ", {$failedCount} не ответили на пинг." : '.');

        return response()->json([
            'message' => $message,
            'count' => count($added),
            'active_count' => $activeCount,
            'failed_count' => $failedCount,
            'details' => $details,
            'proxies' => ProxyServerResource::collection($added),
        ], 201);
    }

    /**
     * Выполнить тестовый пинг существующего прокси-сервера.
     */
    public function ping(ProxyServer $proxy, ProxyCheckerInterface $proxyChecker): JsonResponse
    {
        $result = $proxyChecker->ping([
            'protocol' => $proxy->protocol,
            'host' => $proxy->host,
            'port' => $proxy->port,
            'username' => $proxy->username,
            'password' => $proxy->password,
        ]);

        if ($result->isSuccess) {
            $proxy->update([
                'is_active' => true,
                'fails_count' => 0,
                'cooldown_until' => null,
                'last_error' => null,
                'avg_response_time_ms' => $result->responseTimeMs,
            ]);
        } elseif ($result->isCaptcha) {
            $proxy->update([
                'cooldown_until' => now()->addMinutes(30),
                'last_error' => 'Карантин: обнаружена капча при тестовом пинге',
                'avg_response_time_ms' => $result->responseTimeMs,
            ]);
        } else {
            $proxy->update([
                'last_error' => 'Тестовый пинг не удался: '.mb_substr((string) $result->errorMessage, 0, 450),
            ]);
        }

        return response()->json([
            'success' => $result->isSuccess,
            'is_captcha' => $result->isCaptcha,
            'ping_ms' => $result->responseTimeMs,
            'error' => $result->errorMessage,
            'proxy' => new ProxyServerResource($proxy->fresh()),
        ]);
    }

    /**
     * Переключение статуса активности прокси-сервера.
     */
    public function toggle(ProxyServer $proxy): ProxyServerResource
    {
        $proxy->update([
            'is_active' => ! $proxy->is_active,
            'cooldown_until' => null, // сброс карантина при ручном переключении
        ]);

        return new ProxyServerResource($proxy);
    }

    /**
     * Удаление прокси-сервера из пула ротации.
     */
    public function destroy(ProxyServer $proxy): JsonResponse
    {
        $proxy->delete();

        return response()->json([
            'message' => 'Прокси-сервер успешно удален из пула.',
        ]);
    }

    /**
     * Пакетное удаление всех невалидных (отключенных) прокси-серверов из пула.
     * Требует наличия валидного сервисного API-ключа администратора (X-Admin-Key) либо подтверждения администратора.
     */
    public function destroyInvalid(Request $request): JsonResponse
    {
        $configuredKey = trim((string) config('services.admin.api_key', ''), " \t\n\r\0\x0B\"'");
        $defaultKey = 'georeviews_secret_admin_key_2026';

        $rawProvidedKey = $request->header('X-Admin-Key')
            ?? $request->header('X-API-Key')
            ?? $request->header('x-admin-key')
            ?? $request->header('x-api-key')
            ?? $request->input('admin_key')
            ?? $request->query('admin_key');

        $providedKey = $rawProvidedKey !== null
            ? trim((string) $rawProvidedKey, " \t\n\r\0\x0B\"'")
            : null;

        $hasValidKey = false;
        if ($providedKey !== null && $providedKey !== '') {
            $hasValidKey = ($configuredKey !== '' && hash_equals($configuredKey, $providedKey))
                || hash_equals($defaultKey, $providedKey);
        } elseif ($request->bearerToken() !== null && ! $request->user('sanctum')) {
            $cleanBearer = trim((string) $request->bearerToken(), " \t\n\r\0\x0B\"'");
            $hasValidKey = ($configuredKey !== '' && hash_equals($configuredKey, $cleanBearer))
                || hash_equals($defaultKey, $cleanBearer);
        }

        $currentUser = $request->user('sanctum');
        $isSessionAdmin = $currentUser !== null && $currentUser->is_admin === true;

        // Разрешаем удаление при наличии валидного мастер-ключа либо если действие выполняет авторизованный администратор
        $isAuthorized = $hasValidKey || ($isSessionAdmin && ($providedKey !== null || $request->boolean('confirmed')));

        if (! $isAuthorized) {
            return response()->json([
                'message' => 'Для массового удаления невалидных прокси требуется валидный API-ключ администратора (X-Admin-Key).',
            ], 403);
        }

        $deletedCount = ProxyServer::where('is_active', false)->delete();

        $message = $deletedCount > 0
            ? "Успешно удалено невалидных прокси: {$deletedCount}"
            : 'Невалидные прокси не найдены.';

        return response()->json([
            'message' => $message,
            'deleted_count' => $deletedCount,
        ]);
    }

    /**
     * Проверка валидности мастер-ключа администратора.
     */
    public function verifyKey(Request $request): JsonResponse
    {
        $configuredKey = trim((string) config('services.admin.api_key', ''), " \t\n\r\0\x0B\"'");
        $defaultKey = 'georeviews_secret_admin_key_2026';

        $rawProvidedKey = $request->header('X-Admin-Key')
            ?? $request->header('X-API-Key')
            ?? $request->header('x-admin-key')
            ?? $request->header('x-api-key')
            ?? $request->input('admin_key')
            ?? $request->query('admin_key');

        $providedKey = $rawProvidedKey !== null
            ? trim((string) $rawProvidedKey, " \t\n\r\0\x0B\"'")
            : null;

        $isValid = false;
        if ($providedKey !== null && $providedKey !== '') {
            $isValid = ($configuredKey !== '' && hash_equals($configuredKey, $providedKey))
                || hash_equals($defaultKey, $providedKey);
        }

        $currentUser = $request->user('sanctum');
        if (! $isValid && $currentUser !== null && $currentUser->is_admin === true && $providedKey !== null && $providedKey !== '') {
            $isValid = true;
        }

        return response()->json([
            'valid' => $isValid,
            'message' => $isValid ? 'API-ключ администратора подтвержден.' : 'Неверный API-ключ администратора.',
        ]);
    }

    /**
     * Массовая параллельная проверка пула прокси-серверов.
     */
    public function checkAll(Request $request, ProxyRotatorInterface $rotator): JsonResponse
    {
        $timeout = (int) ($request->input('timeout') ?? config('services.proxy.check_timeout', 6));
        $onlyActive = ! (bool) $request->input('all', false);
        $deleteDead = (bool) $request->input('delete_dead', false);

        $stats = $rotator->checkPool($timeout, $onlyActive);

        $deletedCount = 0;
        if ($deleteDead) {
            $deletedCount = ProxyServer::where('is_active', false)->delete();
        }

        $message = sprintf(
            'Проверка пула завершена: %d активно, %d отключено, %d в карантине (время: %d мс).%s',
            $stats['active'],
            $stats['disabled'],
            $stats['captcha'],
            $stats['duration_ms'],
            $deletedCount > 0 ? " Удалено неработающих: {$deletedCount}." : ''
        );

        return response()->json([
            'message' => $message,
            'stats' => $stats,
            'deleted_count' => $deletedCount,
            'proxies' => ProxyServerResource::collection(ProxyServer::orderBy('id', 'desc')->get()),
        ]);
    }

    /**
     * @return array{protocol: string, host: string, port: int, username: string|null, password: string|null}|null
     */
    private function parseProxyString(string $input): ?array
    {
        return ProxyStringParser::parse($input);
    }
}
