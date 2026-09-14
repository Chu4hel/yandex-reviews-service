<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAdminAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            AssignRequestId::class,
        ]);

        $middleware->alias([
            'admin.access' => EnsureAdminAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
                $requestId = Context::get('request_id');
                if (is_string($requestId) && $requestId !== '') {
                    $response->headers->set('X-Request-ID', $requestId);
                }
            }

            return $response;
        });
    })->create();
