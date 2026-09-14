<?php

use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProxyServerController;
use Illuminate\Support\Facades\Route;

// Мониторинг доступности (Liveness и Readiness проверки)
Route::get('/health', [HealthController::class, 'check']);
Route::get('/health/ready', [HealthController::class, 'readiness']);

// Маршруты аутентификации
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });
});

// Организации и отзывы (защита Sanctum и ограничение частоты запросов)
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('organizations')->group(function () {
    Route::get('/', [OrganizationController::class, 'index']);
    Route::post('/', [OrganizationController::class, 'store'])->middleware('throttle:sync-organizations');
    Route::get('/{organization}', [OrganizationController::class, 'show']);
    Route::get('/{organization}/status', [OrganizationController::class, 'status']);
    Route::post('/{organization}/sync', [OrganizationController::class, 'sync'])->middleware('throttle:sync-organizations');
    Route::get('/{organization}/reviews', [OrganizationController::class, 'reviews']);
    Route::get('/{organization}/snapshots', [OrganizationController::class, 'snapshots']);
    Route::get('/{organization}/export', [OrganizationController::class, 'export']);
    Route::delete('/{organization}', [OrganizationController::class, 'destroy']);
});

// Административный контур (сессия администратора либо заголовок X-Admin-Key)
Route::middleware(['admin.access', 'throttle:api'])->prefix('admin')->group(function () {
    // Управление пулом прокси
    Route::get('/proxies', [ProxyServerController::class, 'index']);
    Route::post('/proxies', [ProxyServerController::class, 'store']);
    Route::post('/proxies/{proxy}/toggle', [ProxyServerController::class, 'toggle']);
    Route::delete('/proxies/{proxy}', [ProxyServerController::class, 'destroy']);

    // Системные настройки и метрики очередей
    Route::get('/settings', [SystemSettingsController::class, 'index']);
});

// Алиас для обратной совместимости внешних интеграций
Route::middleware(['admin.access', 'throttle:api'])->prefix('proxies')->group(function () {
    Route::get('/', [ProxyServerController::class, 'index']);
    Route::post('/', [ProxyServerController::class, 'store']);
    Route::post('/{proxy}/toggle', [ProxyServerController::class, 'toggle']);
    Route::delete('/{proxy}', [ProxyServerController::class, 'destroy']);
});
