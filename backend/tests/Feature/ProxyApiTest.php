<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Contracts\ProxyCheckerInterface;
use App\Domain\DTO\ProxyPingResult;
use App\Models\ProxyServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProxyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*' => Http::response('<html><head><title>Yandex</title></head><body>OK</body></html>', 200),
        ]);
    }

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

    public function test_authenticated_regular_user_can_add_proxies_via_public_endpoint(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/proxies', [
            'proxies' => [
                'http://contributor:pass123@192.168.20.1:8080',
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('proxy_servers', [
            'host' => '192.168.20.1',
            'port' => 8080,
            'username' => 'contributor',
        ]);
    }

    public function test_regular_user_cannot_view_admin_proxies(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response1 = $this->actingAs($user, 'sanctum')->getJson('/api/proxies');
        $response1->assertStatus(403);

        $response2 = $this->actingAs($user, 'sanctum')->getJson('/api/admin/proxies');
        $response2->assertStatus(403);
    }

    public function test_proxy_credentials_are_masked_without_x_admin_key(): void
    {
        config(['services.admin.api_key' => 'super-secret-key']);
        $admin = User::factory()->create(['is_admin' => true]);

        ProxyServer::create([
            'protocol' => 'http',
            'host' => '10.50.0.1',
            'port' => 8080,
            'username' => 'confidential_user',
            'password' => 'secret_password_never_expose',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/proxies');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.username', 'c***r');
        $response->assertJsonPath('data.0.masked_endpoint', 'http://c***r:***@10.50.0.1:8080');
        $response->assertJsonMissing(['password' => 'secret_password_never_expose']);
    }

    public function test_proxy_username_is_unmasked_with_valid_x_admin_key(): void
    {
        config(['services.admin.api_key' => 'super-secret-key']);
        $admin = User::factory()->create(['is_admin' => true]);

        ProxyServer::create([
            'protocol' => 'http',
            'host' => '10.50.0.2',
            'port' => 8080,
            'username' => 'revealed_admin_user',
            'password' => 'secret_password_never_expose',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-Admin-Key', 'super-secret-key')
            ->getJson('/api/admin/proxies');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.username', 'revealed_admin_user');
        $response->assertJsonPath('data.0.masked_endpoint', 'http://revealed_admin_user:***@10.50.0.2:8080');
        $response->assertJsonMissing(['password' => 'secret_password_never_expose']);
    }

    public function test_purchased_proxies_with_identical_url_and_different_credentials_are_both_saved(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Покупные прокси с одинаковым host и port (gateway/url), но разными логинами и паролями
        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/proxies', [
            'proxies' => [
                'proxy-gate.net:8000:client_zone_1:pass_secret_1',
                'proxy-gate.net:8000:client_zone_2:pass_secret_2',
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('count', 2);

        // Убеждаемся, что в базе сохранены ОБЕ прокси, а не одна перезаписала другую
        $this->assertDatabaseHas('proxy_servers', [
            'host' => 'proxy-gate.net',
            'port' => 8000,
            'username' => 'client_zone_1',
        ]);
        $this->assertDatabaseHas('proxy_servers', [
            'host' => 'proxy-gate.net',
            'port' => 8000,
            'username' => 'client_zone_2',
        ]);
        $this->assertEquals(2, ProxyServer::where('host', 'proxy-gate.net')->where('port', 8000)->count());
    }

    public function test_proxy_ping_failure_saves_proxy_as_inactive(): void
    {
        $mockChecker = \Mockery::mock(ProxyCheckerInterface::class);
        $mockChecker->shouldReceive('pingMany')->once()->andReturn([
            0 => ProxyPingResult::failure('Connection refused: 111', 2000),
        ]);
        $this->app->instance(ProxyCheckerInterface::class, $mockChecker);

        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/proxies', [
            'proxy' => 'broken-proxy.example.com:3128:user:pass',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('count', 1);
        $response->assertJsonPath('failed_count', 1);

        $proxy = ProxyServer::where('host', 'broken-proxy.example.com')->first();
        $this->assertNotNull($proxy);
        $this->assertFalse($proxy->is_active);
        $this->assertStringContainsString('Тестовый пинг не удался', (string) $proxy->last_error);
    }

    public function test_admin_can_ping_existing_proxy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $proxy = ProxyServer::create([
            'protocol' => 'http',
            'host' => '10.200.1.1',
            'port' => 8080,
            'is_active' => false,
            'fails_count' => 5,
        ]);

        Http::fake([
            '*' => Http::response('<html>OK</html>', 200),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/proxies/{$proxy->id}/ping");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertTrue($proxy->fresh()->is_active);
        $this->assertEquals(0, $proxy->fresh()->fails_count);
    }

    public function test_destroy_invalid_proxies_is_denied_without_admin_key(): void
    {
        config(['services.admin.api_key' => 'secret-master-key']);
        $admin = User::factory()->create(['is_admin' => true]);

        ProxyServer::create([
            'protocol' => 'http',
            'host' => '10.200.1.10',
            'port' => 8080,
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson('/api/admin/proxies/invalid');

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Для массового удаления невалидных прокси требуется валидный API-ключ администратора (X-Admin-Key).');
        $this->assertDatabaseHas('proxy_servers', ['host' => '10.200.1.10']);
    }

    public function test_destroy_invalid_proxies_deletes_only_inactive_proxies_with_valid_admin_key(): void
    {
        config(['services.admin.api_key' => 'secret-master-key']);
        $admin = User::factory()->create(['is_admin' => true]);

        // 1. Активный валидный прокси
        $activeProxy = ProxyServer::create([
            'protocol' => 'http',
            'host' => '10.200.1.1',
            'port' => 8080,
            'is_active' => true,
        ]);

        // 2. Прокси на охлаждении (валидный, но во временном карантине)
        $cooldownProxy = ProxyServer::create([
            'protocol' => 'http',
            'host' => '10.200.1.2',
            'port' => 8080,
            'is_active' => true,
            'cooldown_until' => now()->addMinutes(15),
        ]);

        // 3. Невалидный прокси 1 (не прошел пинг)
        $invalidProxy1 = ProxyServer::create([
            'protocol' => 'http',
            'host' => '10.200.1.3',
            'port' => 8080,
            'is_active' => false,
            'last_error' => 'Connection timed out',
        ]);

        // 4. Невалидный прокси 2 (деактивирован)
        $invalidProxy2 = ProxyServer::create([
            'protocol' => 'socks5',
            'host' => '10.200.1.4',
            'port' => 1080,
            'is_active' => false,
            'last_error' => 'Auth failed',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->withHeader('X-Admin-Key', 'secret-master-key')
            ->deleteJson('/api/admin/proxies/invalid');

        $response->assertStatus(200);
        $response->assertJsonPath('deleted_count', 2);
        $response->assertJsonPath('message', 'Успешно удалено невалидных прокси: 2');

        // Проверяем, что невалидные удалены
        $this->assertDatabaseMissing('proxy_servers', ['id' => $invalidProxy1->id]);
        $this->assertDatabaseMissing('proxy_servers', ['id' => $invalidProxy2->id]);

        // Проверяем, что активный и охлаждающийся остались
        $this->assertDatabaseHas('proxy_servers', ['id' => $activeProxy->id]);
        $this->assertDatabaseHas('proxy_servers', ['id' => $cooldownProxy->id]);
    }

    public function test_destroy_invalid_proxies_via_alias_with_x_admin_key(): void
    {
        config(['services.admin.api_key' => 'secret-master-key']);

        ProxyServer::create([
            'protocol' => 'http',
            'host' => '10.200.1.5',
            'port' => 8080,
            'is_active' => false,
        ]);

        $response = $this->withHeader('X-Admin-Key', 'secret-master-key')
            ->deleteJson('/api/proxies/invalid');

        $response->assertStatus(200);
        $response->assertJsonPath('deleted_count', 1);
        $this->assertDatabaseMissing('proxy_servers', ['host' => '10.200.1.5']);
    }

    public function test_admin_can_check_all_proxies_in_pool_and_optionally_delete_dead(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $goodProxy = ProxyServer::create([
            'protocol' => 'http',
            'host' => '10.100.1.1',
            'port' => 8080,
            'is_active' => true,
        ]);

        $badProxy = ProxyServer::create([
            'protocol' => 'http',
            'host' => '10.100.1.2',
            'port' => 8080,
            'is_active' => true,
        ]);

        $mockChecker = \Mockery::mock(ProxyCheckerInterface::class);
        $mockChecker->shouldReceive('pingMany')->once()->andReturn([
            0 => ProxyPingResult::success(150, 200),
            1 => ProxyPingResult::failure('Connection timed out', 6000),
        ]);
        $this->app->instance(ProxyCheckerInterface::class, $mockChecker);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/proxies/check-all', [
            'timeout' => 6,
            'delete_dead' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('stats.total', 2);
        $response->assertJsonPath('stats.active', 1);
        $response->assertJsonPath('stats.disabled', 1);
        $response->assertJsonPath('deleted_count', 1);

        $this->assertDatabaseHas('proxy_servers', ['id' => $goodProxy->id, 'is_active' => 1]);
        $this->assertDatabaseMissing('proxy_servers', ['id' => $badProxy->id]);
    }

    public function test_admin_can_verify_master_key(): void
    {
        config(['services.admin.api_key' => 'verified-secret-key']);
        $admin = User::factory()->create(['is_admin' => true]);

        // Неверный ключ
        $response1 = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/proxies/verify-key', [
            'admin_key' => 'wrong-key',
        ]);
        // Для администратора сессии возвращает valid: true
        $response1->assertStatus(200);
        $response1->assertJsonPath('valid', true);

        // Гость с верным ключом
        $response2 = $this->withHeader('X-Admin-Key', 'verified-secret-key')
            ->postJson('/api/admin/proxies/verify-key');
        $response2->assertStatus(200);
        $response2->assertJsonPath('valid', true);

        // Гость с дефолтным ключом georeviews_secret_admin_key_2026
        $response3 = $this->withHeader('X-Admin-Key', 'georeviews_secret_admin_key_2026')
            ->postJson('/api/admin/proxies/verify-key');
        $response3->assertStatus(200);
        $response3->assertJsonPath('valid', true);
    }
}
