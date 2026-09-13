<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use App\Models\ProxyServer;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SystemSettingsController extends Controller
{
    /**
     * Get system-wide operational metrics and settings.
     */
    public function index(): JsonResponse
    {
        $totalProxies = ProxyServer::count();
        $activeProxies = ProxyServer::where('is_active', true)->count();
        $coolingDownProxies = ProxyServer::where('is_active', true)
            ->whereNotNull('cooldown_until')
            ->where('cooldown_until', '>', now())
            ->count();

        $avgProxyLatency = (int) round((float) ProxyServer::where('is_active', true)
            ->whereNotNull('avg_response_time_ms')
            ->avg('avg_response_time_ms'));

        $totalJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        return response()->json([
            'environment' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_time' => now()->toIso8601String(),
                'queue_driver' => config('queue.default'),
            ],
            'database_metrics' => [
                'organizations_count' => Organization::count(),
                'reviews_count' => Review::count(),
                'snapshots_count' => OrganizationSnapshot::count(),
            ],
            'proxy_pool_metrics' => [
                'total_proxies' => $totalProxies,
                'available_for_use' => max(0, $activeProxies - $coolingDownProxies),
                'active_proxies' => $activeProxies,
                'cooling_down_proxies' => $coolingDownProxies,
                'avg_latency_ms' => $avgProxyLatency,
            ],
            'queue_metrics' => [
                'pending_jobs' => $totalJobs,
                'failed_jobs' => $failedJobs,
            ],
        ]);
    }
}
