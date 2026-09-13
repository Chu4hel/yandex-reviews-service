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
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_add_proxies_in_batch(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/proxies', [
            'proxies' => [
                'http://user1:pass1@10.0.0.1:8080',
                'socks5://10.0.0.2:1080',
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('count', 2);
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
    }

    public function test_authenticated_user_can_list_proxies(): void
    {
        $user = User::factory()->create();

        ProxyServer::create([
            'protocol' => 'http',
            'host' => '192.168.1.50',
            'port' => 3128,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/proxies');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.host', '192.168.1.50');
        $response->assertJsonPath('data.0.port', 3128);
    }

    public function test_authenticated_user_can_toggle_proxy_state(): void
    {
        $user = User::factory()->create();

        $proxy = ProxyServer::create([
            'protocol' => 'http',
            'host' => '192.168.1.60',
            'port' => 8080,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/proxies/{$proxy->id}/toggle");

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_active', false);
        $this->assertFalse($proxy->fresh()->is_active);

        // Toggle back
        $response2 = $this->actingAs($user, 'sanctum')->postJson("/api/proxies/{$proxy->id}/toggle");
        $response2->assertStatus(200);
        $response2->assertJsonPath('data.is_active', true);
        $this->assertTrue($proxy->fresh()->is_active);
    }

    public function test_authenticated_user_can_delete_proxy(): void
    {
        $user = User::factory()->create();

        $proxy = ProxyServer::create([
            'protocol' => 'http',
            'host' => '192.168.1.70',
            'port' => 8080,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/proxies/{$proxy->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('proxy_servers', ['id' => $proxy->id]);
    }
}
