<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Contracts\YandexParserInterface;
use App\Domain\DTO\ParsedOrganizationDto;
use App\Domain\DTO\ParsedReviewsBatchDto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_endpoint_is_throttled_after_too_many_attempts(): void
    {
        // 5 allowed attempts
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'throttletest@example.com',
                'password' => 'wrongpassword',
            ]);
            $response->assertStatus(422);
        }

        // 6th attempt must be throttled with HTTP 429
        $response = $this->postJson('/api/auth/login', [
            'email' => 'throttletest@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(429);
        $response->assertJsonStructure(['message']);
        $this->assertStringContainsString('Слишком много попыток входа', (string) $response->json('message'));
    }

    public function test_sync_organizations_endpoint_is_throttled(): void
    {
        $user = User::factory()->create();

        $this->app->bind(YandexParserInterface::class, function () {
            return new class implements YandexParserInterface
            {
                public function normalizeUrl(string $url): string
                {
                    return $url;
                }

                public function extractOrgId(string $url): string
                {
                    return '123';
                }

                public function parseOrganization(string $url): ParsedOrganizationDto
                {
                    return new ParsedOrganizationDto(
                        yandexOrgId: '123',
                        name: 'Test Org',
                        url: $url,
                        address: 'Address',
                        rating: 5.0,
                        ratingsCount: 1,
                        reviewsCount: 1,
                        initialReviews: [],
                        totalPages: 1,
                    );
                }

                public function parseReviewsPage(string $url, int $page): ParsedReviewsBatchDto
                {
                    return new ParsedReviewsBatchDto([], $page, 1, 0, false);
                }
            };
        });

        // 5 allowed attempts
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($user, 'sanctum')->postJson('/api/organizations', [
                'url' => 'https://yandex.ru/maps/org/test/123/',
            ]);
            $this->assertNotEquals(429, $response->status());
        }

        // 6th attempt must be rate-limited
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/test/123/',
        ]);

        $response->assertStatus(429);
        $this->assertStringContainsString('Слишком много запросов на синхронизацию карточек', (string) $response->json('message'));
    }
}
