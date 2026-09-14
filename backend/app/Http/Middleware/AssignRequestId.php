<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    /**
     * Обработка входящего HTTP-запроса с привязкой сквозного Correlation ID (X-Request-ID).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headerRequestId = $request->header('X-Request-ID');
        $requestId = is_string($headerRequestId) && $headerRequestId !== ''
            ? $headerRequestId
            : (string) Str::uuid();

        // Инжектируем идентификатор запроса в контекст Laravel и структурированные логи
        Context::add('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);

        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
