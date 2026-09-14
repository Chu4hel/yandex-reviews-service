<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Contracts\YandexParserInterface;
use App\Domain\DTO\ParsedOrganizationDto;
use App\Domain\DTO\ParsedReviewDto;
use App\Domain\DTO\ParsedReviewsBatchDto;
use App\Jobs\SyncOrganizationReviewsJob;
use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use App\Models\Review;
use App\Models\User;
use App\Services\OrganizationSyncService;
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

    public function test_reviews_filter_by_search(): void
    {
        $org = Organization::create([
            'yandex_org_id' => '888999000',
            'name' => 'Кафе у дома',
            'url' => 'https://yandex.ru/maps/org/888999000/reviews/',
            'rating' => 4.5,
            'ratings_count' => 10,
            'reviews_count' => 3,
        ]);

        Review::create([
            'organization_id' => $org->id,
            'yandex_review_id' => 'rev_srch_1',
            'author_name' => 'Михаил Пиццеед',
            'rating' => 5,
            'text' => 'Самая хрустящая пепперони в городе',
            'published_at' => now(),
        ]);

        Review::create([
            'organization_id' => $org->id,
            'yandex_review_id' => 'rev_srch_2',
            'author_name' => 'Елена',
            'rating' => 4,
            'text' => 'Хороший чай и тихая атмосфера',
            'published_at' => now()->subHour(),
        ]);

        Review::create([
            'organization_id' => $org->id,
            'yandex_review_id' => 'rev_srch_3',
            'author_name' => 'Василий',
            'rating' => 2,
            'text' => 'Долгая доставка пиццы',
            'published_at' => now()->subDay(),
        ]);

        // Поиск по тексту "пепперони"
        $respText = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/organizations/{$org->id}/reviews?search=пепперони");

        $respText->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.author_name', 'Михаил Пиццеед');

        // Поиск по автору "Михаил"
        $respAuthor = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/organizations/{$org->id}/reviews?search=Михаил");

        $respAuthor->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.yandex_review_id', 'rev_srch_1');

        // Поиск по общему слову "пицц" (находит 2 отзыва)
        $respBoth = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/organizations/{$org->id}/reviews?search=пицц");

        $respBoth->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_organization_soft_delete(): void
    {
        $org = Organization::create([
            'yandex_org_id' => '99887766',
            'name' => 'Организация для удаления',
            'url' => 'https://yandex.ru/maps/org/99887766/reviews/',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/organizations/{$org->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Организация успешно перемещена в архив');

        $this->assertSoftDeleted('organizations', [
            'id' => $org->id,
        ]);

        // Повторный запрос show возвращает 404
        $showResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/organizations/{$org->id}");

        $showResponse->assertStatus(404);

        // В общем списке удаленная организация отсутствует
        $listResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/organizations');

        $listResponse->assertStatus(200);
        $ids = collect($listResponse->json('data'))->pluck('id')->all();
        $this->assertNotContains($org->id, $ids);
    }

    public function test_reviews_endpoint_returns_photos_payload(): void
    {
        $org = Organization::create([
            'yandex_org_id' => '999888777',
            'name' => 'Кафе с фото',
            'url' => 'https://yandex.ru/maps/org/999888777/reviews/',
            'rating' => 4.8,
            'ratings_count' => 10,
            'reviews_count' => 1,
        ]);

        Review::create([
            'organization_id' => $org->id,
            'yandex_review_id' => 'review_with_photo_1',
            'author_name' => 'Анна',
            'rating' => 5,
            'text' => 'Красивое блюдо!',
            'photos' => [
                [
                    'id' => 'photo_1',
                    'preview_url' => 'https://avatars.mds.yandex.net/get-altay/123/L',
                    'full_url' => 'https://avatars.mds.yandex.net/get-altay/123/orig',
                ],
            ],
            'published_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/organizations/{$org->id}/reviews");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.photos.0.id', 'photo_1')
            ->assertJsonPath('data.0.photos.0.preview_url', 'https://avatars.mds.yandex.net/get-altay/123/L')
            ->assertJsonPath('data.0.photos.0.full_url', 'https://avatars.mds.yandex.net/get-altay/123/orig');
    }

    public function test_connect_and_sync_creates_single_snapshot_after_full_sync(): void
    {
        $this->mock(YandexParserInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('normalizeUrl')
                ->andReturn('https://yandex.ru/maps/org/777888999/reviews/');

            $mock->shouldReceive('extractOrgId')
                ->andReturn('777888999');

            $mock->shouldReceive('parseOrganization')
                ->andReturn(new ParsedOrganizationDto(
                    yandexOrgId: '777888999',
                    name: 'Ресторан Одиночный Снимок',
                    url: 'https://yandex.ru/maps/org/777888999/reviews/',
                    address: 'г. Москва, ул. Снимков 1',
                    rating: 4.8,
                    ratingsCount: 120,
                    reviewsCount: 80,
                    initialReviews: [
                        new ParsedReviewDto(
                            yandexReviewId: 'rev_init_1',
                            authorName: 'Олег',
                            authorAvatarUrl: null,
                            authorLevel: 'Знаток',
                            rating: 5,
                            text: 'Первая пачка',
                            publishedAt: '2026-09-01T12:00:00Z',
                        ),
                    ],
                    totalPages: 1,
                ));

            $mock->shouldReceive('parseReviewsPage')
                ->andReturn(new ParsedReviewsBatchDto(
                    reviews: [
                        new ParsedReviewDto(
                            yandexReviewId: 'rev_page_2',
                            authorName: 'Мария',
                            authorAvatarUrl: null,
                            authorLevel: 'Новичок',
                            rating: 4,
                            text: 'Вторая пачка',
                            publishedAt: '2026-09-02T12:00:00Z',
                        ),
                    ],
                    page: 1,
                    totalPages: 1,
                    totalReviewsCount: 80,
                    hasNextPage: false,
                ));
        });

        // 1. Подключение организации (раньше тут создавался 1-й снимок)
        $syncService = app(OrganizationSyncService::class);
        Queue::fake();

        $org = $syncService->connectOrganization('https://yandex.ru/maps/org/777888999/');

        // На этапе connectOrganization снимок еще НЕ должен быть создан
        $this->assertDatabaseCount('organization_snapshots', 0);

        // 2. Выполняем фоновую синхронизацию
        $syncService->syncOrganizationReviews($org);

        // Должен быть создан ровно 1 снимок
        $this->assertDatabaseCount('organization_snapshots', 1);

        $snapshot = OrganizationSnapshot::first();
        $this->assertNull($snapshot->rating_before);
        $this->assertEquals(4.8, $snapshot->rating_after);
        $this->assertEquals(0, $snapshot->reviews_count_before);
        $this->assertEquals(80, $snapshot->reviews_count_after);
        $this->assertEquals(2, $snapshot->new_reviews_added);
    }
}
