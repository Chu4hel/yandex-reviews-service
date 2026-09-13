<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_without_api_key_is_denied(): void
    {
        $response = $this->getJson('/api/admin/settings');
        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Доступ запрещен. Требуются права администратора или валидный сервисный API-ключ.');
    }

    public function test_regular_user_without_admin_flag_is_denied(): void
    {
        $regularUser = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($regularUser, 'sanctum')->getJson('/api/admin/settings');
        $response->assertStatus(403);
    }

    public function test_admin_user_via_sanctum_is_granted_access(): void
    {
        $adminUser = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($adminUser, 'sanctum')->getJson('/api/admin/settings');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'environment' => ['php_version', 'laravel_version', 'server_time', 'queue_driver'],
            'database_metrics' => ['organizations_count', 'reviews_count', 'snapshots_count'],
            'proxy_pool_metrics' => ['total_proxies', 'available_for_use', 'active_proxies', 'cooling_down_proxies'],
            'queue_metrics' => ['pending_jobs', 'failed_jobs'],
        ]);
    }

    public function test_external_service_with_valid_x_admin_key_is_granted_access(): void
    {
        config(['services.admin.api_key' => 'test-secret-admin-key']);

        $response = $this->withHeader('X-Admin-Key', 'test-secret-admin-key')
            ->getJson('/api/admin/settings');

        $response->assertStatus(200);
    }

    public function test_external_service_with_valid_x_api_key_is_granted_access(): void
    {
        config(['services.admin.api_key' => 'test-secret-admin-key']);

        $response = $this->withHeader('X-API-Key', 'test-secret-admin-key')
            ->getJson('/api/admin/proxies');

        $response->assertStatus(200);
    }

    public function test_external_service_with_invalid_key_is_denied(): void
    {
        config(['services.admin.api_key' => 'correct-secret-key']);

        $response = $this->withHeader('X-Admin-Key', 'wrong-key')
            ->getJson('/api/admin/settings');

        $response->assertStatus(403);
    }
}
