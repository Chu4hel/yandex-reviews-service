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
        $configuredKey = (string) config('services.admin.api_key', '');
        $providedKey = $request->header('X-Admin-Key')
            ?? $request->header('X-API-Key')
            ?? $request->bearerToken();

        if ($configuredKey !== '' && $providedKey !== null && hash_equals($configuredKey, (string) $providedKey)) {
            return $next($request);
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
