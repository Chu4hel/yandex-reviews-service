<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ProxyServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProxyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_proxy_api(): void
    {
        $response = $this->getJson('/api/proxies');
        $response->assertStatus(403);
    }

    public function test_authenticated_admin_can_add_proxies_in_batch(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/proxies', [
            'proxies' => [
                'http://user1:pass1@10.0.0.1:8080',
                'socks5://10.0.0.2:1080',
                '10.0.0.3:8080:customuser:custompass',
                '10.0.0.4:3128@atuser:atpass',
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('count', 4);
        $this->assertDatabaseHas('proxy_servers', [
            'host' => '10.0.0.1',
            'port' => 8080,
            'protocol' => 'http',
            'username' => 'user1',
        ]);
        $this->assertDatabaseHas('proxy_servers', [
            'host' => '10.0.0.2',
            'port' => 1080,
            'protocol' => 'socks5',
        ]);
        $this->assertDatabaseHas('proxy_servers', [
            'host' => '10.0.0.3',
            'port' => 8080,
            'username' => 'customuser',
            'password' => 'custompass',
        ]);
        $this->assertDatabaseHas('proxy_servers', [
            'host' => '10.0.0.4',
            'port' => 3128,
            'username' => 'atuser',
            'password' => 'atpass',
        ]);
    }

    public function test_authenticated_admin_can_list_proxies(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        ProxyServer::create([
            'protocol' => 'http',
            'host' => '192.168.1.50',
            'port' => 3128,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/proxies');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.host', '192.168.1.50');
        $response->assertJsonPath('data.0.port', 3128);
    }

    public function test_authenticated_admin_can_toggle_proxy_state(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $proxy = ProxyServer::create([
            'protocol' => 'http',
            'host' => '192.168.1.60',
            'port' => 8080,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/proxies/{$proxy->id}/toggle");

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_active', false);
        $this->assertFalse($proxy->fresh()->is_active);

        // Toggle back
        $response2 = $this->actingAs($admin, 'sanctum')->postJson("/api/proxies/{$proxy->id}/toggle");
        $response2->assertStatus(200);
        $response2->assertJsonPath('data.is_active', true);
        $this->assertTrue($proxy->fresh()->is_active);
    }

    public function test_authenticated_admin_can_delete_proxy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $proxy = ProxyServer::create([
            'protocol' => 'http',
            'host' => '192.168.1.70',
            'port' => 8080,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/proxies/{$proxy->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('proxy_servers', ['id' => $proxy->id]);
    }

    public function test_can_manage_proxies_via_x_admin_key_without_session(): void
    {
        config(['services.admin.api_key' => 'secret-automation-key']);

        $response = $this->withHeader('X-Admin-Key', 'secret-automation-key')
            ->postJson('/api/admin/proxies', [
                'proxy' => 'http://172.16.0.5:8888',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('proxy_servers', [
            'host' => '172.16.0.5',
            'port' => 8888,
        ]);
    }
}
