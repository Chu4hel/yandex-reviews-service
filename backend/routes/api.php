<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'Yandex Reviews Service API',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });
});

// Organization and Reviews routes (protected with Sanctum and throttled)
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('organizations')->group(function () {
    Route::get('/', [OrganizationController::class, 'index']);
    Route::post('/', [OrganizationController::class, 'store'])->middleware('throttle:sync-organizations');
    Route::get('/{organization}', [OrganizationController::class, 'show']);
    Route::get('/{organization}/status', [OrganizationController::class, 'status']);
    Route::post('/{organization}/sync', [OrganizationController::class, 'sync'])->middleware('throttle:sync-organizations');
    Route::get('/{organization}/reviews', [OrganizationController::class, 'reviews']);
    Route::get('/{organization}/snapshots', [OrganizationController::class, 'snapshots']);
});
