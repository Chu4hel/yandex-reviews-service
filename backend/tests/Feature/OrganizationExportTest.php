<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_export_reviews(): void
    {
        $org = Organization::create([
            'yandex_org_id' => '10001',
            'name' => 'Кафе',
            'url' => 'https://yandex.ru/maps/org/10001/reviews/',
        ]);

        $response = $this->getJson("/api/organizations/{$org->id}/export");
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_export_reviews_with_utf8_bom(): void
    {
        $user = User::factory()->create();
        $org = Organization::create([
            'yandex_org_id' => '10002',
            'name' => 'Кафе Уют',
            'url' => 'https://yandex.ru/maps/org/10002/reviews/',
        ]);

        Review::create([
            'organization_id' => $org->id,
            'yandex_review_id' => 'rev_101',
            'author_name' => 'Иван Иванов',
            'rating' => 5,
            'text' => 'Отличный кофе и обслуживание!',
            'published_at' => now(),
            'business_response_text' => 'Спасибо за отзыв!',
            'business_response_at' => now(),
        ]);

        Review::create([
            'organization_id' => $org->id,
            'yandex_review_id' => 'rev_102',
            'author_name' => 'Анна Смирнова',
            'rating' => 3,
            'text' => 'Долго несли заказ',
            'published_at' => now()->subDay(),
            'business_response_text' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->get("/api/organizations/{$org->id}/export");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=', (string) $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();

        // 1. Verify UTF-8 BOM is present at the beginning of the file
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // 2. Verify CSV headers
        $this->assertStringContainsString('ID отзыва Яндекса', $content);
        $this->assertStringContainsString('Автор', $content);
        $this->assertStringContainsString('Оценка (звёзды)', $content);

        // 3. Verify rows content
        $this->assertStringContainsString('rev_101', $content);
        $this->assertStringContainsString('Иван Иванов', $content);
        $this->assertStringContainsString('Отличный кофе и обслуживание!', $content);
        $this->assertStringContainsString('Спасибо за отзыв!', $content);
        $this->assertStringContainsString('rev_102', $content);
        $this->assertStringContainsString('Анна Смирнова', $content);
    }

    public function test_export_filters_by_rating(): void
    {
        $user = User::factory()->create();
        $org = Organization::create([
            'yandex_org_id' => '10003',
            'name' => 'Пиццерия',
            'url' => 'https://yandex.ru/maps/org/10003/reviews/',
        ]);

        Review::create([
            'organization_id' => $org->id,
            'yandex_review_id' => 'rev_5stars',
            'author_name' => 'Петр',
            'rating' => 5,
            'published_at' => now(),
            'has_business_response' => false,
        ]);

        Review::create([
            'organization_id' => $org->id,
            'yandex_review_id' => 'rev_1star',
            'author_name' => 'Ольга',
            'rating' => 1,
            'published_at' => now(),
            'has_business_response' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')->get("/api/organizations/{$org->id}/export?rating=5");

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('rev_5stars;Петр', $content);
        $this->assertStringNotContainsString('rev_1star;Ольга', $content);
    }

    public function test_export_filters_by_search_term(): void
    {
        $user = User::factory()->create();
        $org = Organization::create([
            'yandex_org_id' => '10004',
            'name' => 'Пекарня',
            'url' => 'https://yandex.ru/maps/org/10004/reviews/',
        ]);

        Review::create([
            'organization_id' => $org->id,
            'yandex_review_id' => 'rev_croissant',
            'author_name' => 'Виктор',
            'rating' => 5,
            'text' => 'Свежие круассаны каждое утро',
            'published_at' => now(),
        ]);

        Review::create([
            'organization_id' => $org->id,
            'yandex_review_id' => 'rev_baguette',
            'author_name' => 'Мария',
            'rating' => 4,
            'text' => 'Французский багет хрустит',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->get("/api/organizations/{$org->id}/export?search=круассан");

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('rev_croissant;Виктор', $content);
        $this->assertStringNotContainsString('rev_baguette;Мария', $content);
    }

    public function test_authenticated_user_can_export_reviews_in_json_format(): void
    {
        $user = User::factory()->create();
        $org = Organization::create([
            'yandex_org_id' => '10005',
            'name' => 'Кофейня JSON',
            'url' => 'https://yandex.ru/maps/org/10005/reviews/',
        ]);

        Review::create([
            'organization_id' => $org->id,
            'yandex_review_id' => 'rev_json_1',
            'author_name' => 'Алексей',
            'rating' => 5,
            'text' => 'Ароматный эспрессо!',
            'published_at' => now(),
            'business_response_text' => 'Рады стараться!',
            'business_response_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->get("/api/organizations/{$org->id}/export?format=json");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $this->assertStringContainsString('.json', (string) $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $decoded = json_decode($content, true);

        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertSame('rev_json_1', $decoded[0]['yandex_review_id']);
        $this->assertSame('Алексей', $decoded[0]['author_name']);
        $this->assertSame(5, $decoded[0]['rating']);
        $this->assertSame('Ароматный эспрессо!', $decoded[0]['text']);
        $this->assertSame('Рады стараться!', $decoded[0]['business_response']['text']);
    }
}
