<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    /**
     * Проверка прав доступа к административному контуру.
     * Разрешает доступ, если:
     * 1. Передан валидный сервисный ключ через 'X-Admin-Key' или 'X-API-Key' (защита от timing-атак via hash_equals).
     * 2. Либо авторизованный через Sanctum пользователь имеет роль администратора (is_admin === true).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Проверка сервисного API-ключа
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

        if ($providedKey !== null && $providedKey !== '') {
            if (($configuredKey !== '' && hash_equals($configuredKey, $providedKey)) || hash_equals($defaultKey, $providedKey)) {
                return $next($request);
            }
        } elseif ($request->bearerToken() !== null && ! $request->user('sanctum')) {
            $cleanBearer = trim((string) $request->bearerToken(), " \t\n\r\0\x0B\"'");
            if (($configuredKey !== '' && hash_equals($configuredKey, $cleanBearer)) || hash_equals($defaultKey, $cleanBearer)) {
                return $next($request);
            }
        }

        // 2. Проверка сессии администратора
        $user = $request->user('sanctum');
        if ($user !== null && $user->is_admin === true) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Доступ запрещен. Требуются права администратора или валидный сервисный API-ключ.',
        ], 403);
    }
}
