<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Contracts\YandexParserInterface;
use App\Domain\DTO\ParsedOrganizationDto;
use App\Domain\DTO\ParsedReviewDto;
use App\Jobs\SyncOrganizationReviewsJob;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class OrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_can_list_organizations(): void
    {
        Organization::create([
            'yandex_org_id' => '123456789',
            'name' => 'Тестовое кафе',
            'url' => 'https://yandex.ru/maps/org/123456789/reviews/',
            'rating' => 4.8,
            'ratings_count' => 120,
            'reviews_count' => 85,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/organizations');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Тестовое кафе');
    }

    public function test_can_connect_organization_via_mock_parser(): void
    {
        $this->mock(YandexParserInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('normalizeUrl')
                ->andReturn('https://yandex.ru/maps/org/11223344/reviews/');

            $mock->shouldReceive('extractOrgId')
                ->andReturn('11223344');

            $mock->shouldReceive('parseOrganization')
                ->andReturn(new ParsedOrganizationDto(
                    yandexOrgId: '11223344',
                    name: 'Пиццерия Тест',
                    url: 'https://yandex.ru/maps/org/11223344/reviews/',
                    address: 'г. Москва, ул. Тестовая 1',
                    rating: 4.75,
                    ratingsCount: 50,
                    reviewsCount: 30,
                    initialReviews: [
                        new ParsedReviewDto(
                            yandexReviewId: 'rev_1',
                            authorName: 'Иван',
                            authorAvatarUrl: null,
                            authorLevel: 'Эксперт 5 уровня',
                            rating: 5,
                            text: 'Отличная пицца!',
                            publishedAt: '2026-09-01T12:00:00Z',
                        ),
                    ],
                    totalPages: 1,
                ));
        });

        Queue::fake();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/organizations', [
                'url' => 'https://yandex.ru/maps/org/11223344/',
            ]);

        Queue::assertPushed(SyncOrganizationReviewsJob::class);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Пиццерия Тест')
            ->assertJsonPath('data.rating', 4.75);

        $this->assertDatabaseHas('organizations', [
            'yandex_org_id' => '11223344',
            'name' => 'Пиццерия Тест',
        ]);

        $this->assertDatabaseHas('reviews', [
            'yandex_review_id' => 'rev_1',
            'author_name' => 'Иван',
        ]);
    }

    public function test_reviews_pagination_returns_50_per_page(): void
    {
        $org = Organization::create([
            'yandex_org_id' => '555666777',
            'name' => 'Большой ресторан',
            'url' => 'https://yandex.ru/maps/org/555666777/reviews/',
            'rating' => 4.9,
            'ratings_count' => 100,
            'reviews_count' => 75,
        ]);

        for ($i = 1; $i <= 75; $i++) {
            Review::create([
                'organization_id' => $org->id,
                'yandex_review_id' => "review_id_{$i}",
                'author_name' => "Автор {$i}",
                'rating' => 5,
                'text' => "Текст отзыва {$i}",
                'published_at' => now()->subMinutes($i),
            ]);
        }

        // Request page 1
        $response1 = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/organizations/{$org->id}/reviews?page=1");

        $response1->assertStatus(200)
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 75);

        // Request page 2
        $response2 = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/organizations/{$org->id}/reviews?page=2");

        $response2->assertStatus(200)
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }
}
