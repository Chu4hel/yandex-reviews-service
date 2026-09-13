<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    /**
     * Handle an incoming request.
     * Allows access if:
     * 1. Valid service admin API key provided via 'X-Admin-Key' or 'X-API-Key' header.
     * 2. Or authenticated user via Sanctum has is_admin === true.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check Service API Key
        $configuredKey = (string) config('services.admin.api_key', '');
        $providedKey = $request->header('X-Admin-Key')
            ?? $request->header('X-API-Key')
            ?? $request->bearerToken();

        if ($configuredKey !== '' && $providedKey !== null && hash_equals($configuredKey, (string) $providedKey)) {
            return $next($request);
        }

        // 2. Check authenticated User with is_admin role
        $user = $request->user('sanctum');
        if ($user !== null && $user->is_admin === true) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Доступ запрещен. Требуются права администратора или валидный сервисный API-ключ.',
        ], 403);
    }
}
