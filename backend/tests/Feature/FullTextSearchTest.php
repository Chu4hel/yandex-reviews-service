<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullTextSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->org = Organization::create([
            'yandex_org_id' => '1234567890',
            'name' => 'Тестовое кафе',
            'url' => 'https://yandex.ru/maps/org/test/1234567890/',
            'rating' => 4.8,
            'ratings_count' => 100,
            'reviews_count' => 100,
            'sync_status' => 'completed',
        ]);
    }

    public function test_search_matches_multiple_words_in_any_order(): void
    {
        Review::create([
            'organization_id' => $this->org->id,
            'yandex_review_id' => 'rev_1',
            'author_name' => 'Александр',
            'rating' => 5,
            'text' => 'Горячая вкуснейшая пицца и невероятно быстрая аккуратная доставка!',
            'published_at' => now(),
        ]);

        Review::create([
            'organization_id' => $this->org->id,
            'yandex_review_id' => 'rev_2',
            'author_name' => 'Екатерина',
            'rating' => 1,
            'text' => 'Холодный кофе и грязные столы, ужасное обслуживание.',
            'published_at' => now(),
        ]);

        // Поиск слов в обратном порядке: "доставка вкуснейшая"
        $res = $this->actingAs($this->user)
            ->getJson("/api/organizations/{$this->org->id}/reviews?search=доставка+вкуснейшая");

        $res->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.yandex_review_id', 'rev_1');
    }

    public function test_search_matches_business_response_text(): void
    {
        Review::create([
            'organization_id' => $this->org->id,
            'yandex_review_id' => 'rev_resp',
            'author_name' => 'Михаил',
            'rating' => 2,
            'text' => 'Забыли положить соус к заказу.',
            'business_response_text' => 'Михаил, здравствуйте! Отправили вам персональный промокод на скидку.',
            'business_response_at' => now(),
            'published_at' => now(),
        ]);

        $res = $this->actingAs($this->user)
            ->getJson("/api/organizations/{$this->org->id}/reviews?search=персональный+промокод");

        $res->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.yandex_review_id', 'rev_resp');
    }

    public function test_search_sorts_by_relevance_when_selected(): void
    {
        Review::create([
            'organization_id' => $this->org->id,
            'yandex_review_id' => 'rev_body_only',
            'author_name' => 'Иван Иванов',
            'rating' => 4,
            'text' => 'Здесь работает отличный бариста Алексей, варит вкусный раф.',
            'published_at' => now()->subDays(1),
        ]);

        Review::create([
            'organization_id' => $this->org->id,
            'yandex_review_id' => 'rev_author_match',
            'author_name' => 'Алексей Смирнов',
            'rating' => 5,
            'text' => 'Хорошее заведение в центре города.',
            'published_at' => now()->subDays(5),
        ]);

        // При поиске "Алексей" совпадение в имени автора имеет высший вес релевантности
        $res = $this->actingAs($this->user)
            ->getJson("/api/organizations/{$this->org->id}/reviews?search=Алексей&sort=relevance");

        $res->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.yandex_review_id', 'rev_author_match')
            ->assertJsonPath('data.1.yandex_review_id', 'rev_body_only');
    }
}
