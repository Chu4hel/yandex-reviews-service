<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProxyServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HealthController extends Controller
{
    /**
     * General liveness and basic readiness check.
     */
    public function check(Request $request): JsonResponse
    {
        // If deep check requested or ready endpoint
        if ($request->boolean('deep', false)) {
            return $this->readiness();
        }

        return response()->json([
            'status' => 'ok',
            'service' => 'Yandex Reviews Service API',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Deep readiness probe for Kubernetes, Docker and SRE monitoring.
     * Evaluates Database, Queue, Disk storage, and Proxy pool availability.
     */
    public function readiness(): JsonResponse
    {
        $checks = [];
        $overallStatus = 'healthy';
        $httpCode = 200;

        // 1. Database Check
        $dbStart = microtime(true);
        try {
            DB::connection()->getPdo();
            $dbDuration = (int) round((microtime(true) - $dbStart) * 1000);
            $checks['database'] = [
                'status' => 'ok',
                'latency_ms' => $dbDuration,
                'connection' => config('database.default'),
            ];
        } catch (\Throwable $e) {
            $checks['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            $overallStatus = 'unhealthy';
            $httpCode = 503;
        }

        // 2. Queue & Failed Jobs Check
        try {
            $hasJobs = Schema::hasTable('jobs');
            $hasFailed = Schema::hasTable('failed_jobs');

            $pendingJobs = $hasJobs ? DB::table('jobs')->count() : 0;
            $failedJobs = $hasFailed ? DB::table('failed_jobs')->count() : 0;

            $checks['queue'] = [
                'status' => $failedJobs > 0 ? 'degraded' : 'ok',
                'driver' => (string) config('queue.default'),
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
            ];

            if ($failedJobs > 0 && $overallStatus === 'healthy') {
                $overallStatus = 'degraded';
            }
        } catch (\Throwable $e) {
            $checks['queue'] = [
                'status' => 'warning',
                'driver' => (string) config('queue.default'),
                'error' => $e->getMessage(),
            ];
        }

        // 3. Storage / Disk Space Check
        try {
            $storagePath = storage_path();
            $freeBytes = @disk_free_space($storagePath);
            $totalBytes = @disk_total_space($storagePath);

            if ($freeBytes !== false && $totalBytes !== false && $totalBytes > 0) {
                $freePercent = round(($freeBytes / $totalBytes) * 100, 1);
                $checks['storage'] = [
                    'status' => $freePercent < 5.0 ? 'degraded' : 'ok',
                    'free_mb' => (int) round($freeBytes / 1024 / 1024),
                    'free_percent' => $freePercent,
                ];

                if ($freePercent < 5.0 && $overallStatus === 'healthy') {
                    $overallStatus = 'degraded';
                }
            } else {
                $checks['storage'] = [
                    'status' => 'ok',
                    'note' => 'Disk statistics not available in this environment',
                ];
            }
        } catch (\Throwable $e) {
            $checks['storage'] = [
                'status' => 'warning',
                'error' => $e->getMessage(),
            ];
        }

        // 4. Proxy Pool Check
        try {
            $totalProxies = ProxyServer::count();
            $activeProxies = ProxyServer::where('is_active', true)->count();
            $coolingDownProxies = ProxyServer::where('is_active', true)
                ->whereNotNull('cooldown_until')
                ->where('cooldown_until', '>', now())
                ->count();

            $available = max(0, $activeProxies - $coolingDownProxies);

            $checks['proxy_pool'] = [
                'status' => $totalProxies === 0 ? 'direct_mode' : ($available > 0 ? 'ok' : 'degraded'),
                'total_configured' => $totalProxies,
                'available_for_use' => $available,
                'cooling_down' => $coolingDownProxies,
            ];
        } catch (\Throwable $e) {
            $checks['proxy_pool'] = [
                'status' => 'warning',
                'error' => $e->getMessage(),
            ];
        }

        return response()->json([
            'status' => $overallStatus,
            'service' => 'Yandex Reviews Service API',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $httpCode);
    }
}
