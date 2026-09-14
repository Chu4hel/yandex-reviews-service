<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Contracts\CircuitBreakerInterface;
use App\Domain\Contracts\ProxyRotatorInterface;
use App\Domain\DTO\ParsedOrganizationDto;
use App\Domain\DTO\ParsedReviewsBatchDto;
use App\Domain\DTO\ProxyDto;
use App\Domain\Exceptions\CircuitBreakerOpenException;
use App\Domain\Exceptions\YandexCaptchaDetectedException;
use App\Domain\Exceptions\YandexMarkupChangedException;
use App\Domain\Exceptions\YandexOrganizationNotFoundException;
use App\Domain\Exceptions\YandexParserException;
use App\Infrastructure\Services\YandexMapsParserService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexMapsParserServiceTest extends TestCase
{
    private YandexMapsParserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new YandexMapsParserService;
    }

    public function test_normalize_url_with_numeric_id(): void
    {
        $url = $this->service->normalizeUrl('11223344');
        $this->assertSame('https://yandex.ru/maps/org/11223344/reviews/', $url);
        $this->assertSame('11223344', $this->service->extractOrgId('11223344'));
    }

    public function test_normalize_url_with_full_slug_url(): void
    {
        $input = 'https://yandex.ru/maps/org/dodo_pizza/79409187372/reviews/?add-review=true';
        $normalized = $this->service->normalizeUrl($input);
        $this->assertSame('https://yandex.ru/maps/org/dodo_pizza/79409187372/reviews/', $normalized);
        $this->assertSame('79409187372', $this->service->extractOrgId($input));
    }

    public function test_normalize_url_with_oid_parameter(): void
    {
        $input = 'https://yandex.ru/maps/?oid=99887766&ll=37.6,55.7&z=15';
        $normalized = $this->service->normalizeUrl($input);
        $this->assertSame('https://yandex.ru/maps/org/99887766/reviews/', $normalized);
        $this->assertSame('99887766', $this->service->extractOrgId($input));
    }

    public function test_extract_org_id_throws_exception_on_invalid_input(): void
    {
        $this->expectException(YandexParserException::class);
        $this->expectExceptionMessage('Не удалось извлечь идентификатор организации');

        $this->service->extractOrgId('https://google.com/search?q=pizza');
    }

    public function test_successful_parse_organization(): void
    {
        $payload = [
            'stack' => [
                [
                    'results' => [
                        'items' => [
                            [
                                'id' => '79409187372',
                                'title' => 'Додо Пицца',
                                'address' => 'ул. Ленина, д. 10',
                                'ratingData' => [
                                    'ratingValue' => 4.82,
                                    'ratingCount' => 350,
                                    'reviewCount' => 120,
                                ],
                                'reviewResults' => [
                                    'params' => [
                                        'totalPages' => 3,
                                        'count' => 120,
                                    ],
                                    'reviews' => [
                                        [
                                            'reviewId' => 'rev-001',
                                            'author' => [
                                                'name' => 'Иван Иванов',
                                                'avatarUrl' => 'https://avatars.mds.yandex.net/get-yapic/0/0-0/islands-middle',
                                                'professionLevel' => 'Знаток города 5 уровня',
                                            ],
                                            'rating' => 5,
                                            'text' => 'Отличная горячая пицца, быстрая доставка!',
                                            'updatedTime' => '2026-03-01T12:00:00Z',
                                            'businessComment' => [
                                                'text' => 'Спасибо за отзыв! Рады стараться.',
                                                'updatedTime' => '2026-03-01T14:30:00Z',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $html = '<html><head><script type="application/json" class="state-view">'.json_encode($payload).'</script></head><body>Content</body></html>';

        Http::fake([
            'https://yandex.ru/maps/org/79409187372/reviews/' => Http::response($html, 200),
        ]);

        $dto = $this->service->parseOrganization('79409187372');

        $this->assertInstanceOf(ParsedOrganizationDto::class, $dto);
        $this->assertSame('79409187372', $dto->yandexOrgId);
        $this->assertSame('Додо Пицца', $dto->name);
        $this->assertSame('ул. Ленина, д. 10', $dto->address);
        $this->assertSame(4.82, $dto->rating);
        $this->assertSame(350, $dto->ratingsCount);
        $this->assertSame(120, $dto->reviewsCount);
        $this->assertSame(3, $dto->totalPages);
        $this->assertCount(1, $dto->initialReviews);

        $firstReview = $dto->initialReviews[0];
        $this->assertSame('rev-001', $firstReview->yandexReviewId);
        $this->assertSame('Иван Иванов', $firstReview->authorName);
        $this->assertSame(5, $firstReview->rating);
        $this->assertSame('Спасибо за отзыв! Рады стараться.', $firstReview->businessResponseText);
    }

    public function test_successful_parse_reviews_page(): void
    {
        $payload = [
            'stack' => [
                [
                    'results' => [
                        'items' => [
                            [
                                'id' => '79409187372',
                                'reviewResults' => [
                                    'params' => [
                                        'totalPages' => 4,
                                        'count' => 180,
                                    ],
                                    'reviews' => [
                                        [
                                            'reviewId' => 'rev-002',
                                            'author' => ['name' => 'Ольга'],
                                            'rating' => 4,
                                            'text' => 'Всё вкусно, но пришлось подождать 15 минут.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $html = '<script type="application/json" class="state-view">'.json_encode($payload).'</script>';

        Http::fake([
            'https://yandex.ru/maps/org/79409187372/reviews/?page=2' => Http::response($html, 200),
        ]);

        $batch = $this->service->parseReviewsPage('https://yandex.ru/maps/org/79409187372/reviews/', 2);

        $this->assertInstanceOf(ParsedReviewsBatchDto::class, $batch);
        $this->assertSame(2, $batch->page);
        $this->assertSame(4, $batch->totalPages);
        $this->assertTrue($batch->hasNextPage);
        $this->assertCount(1, $batch->reviews);
        $this->assertSame('rev-002', $batch->reviews[0]->yandexReviewId);
    }

    public function test_detects_smartcaptcha_and_throws_exception(): void
    {
        $captchaHtml = '<html><body><div id="smartcaptcha" class="smartcaptcha-container">Подтвердите, что вы не робот</div></body></html>';

        Http::fake([
            '*' => Http::response($captchaHtml, 200),
        ]);

        $this->expectException(YandexCaptchaDetectedException::class);
        $this->service->parseOrganization('79409187372');
    }

    public function test_missing_state_view_throws_markup_changed_exception(): void
    {
        $invalidHtml = '<html><body><div class="empty-app">Нет данных</div></body></html>';

        Http::fake([
            '*' => Http::response($invalidHtml, 200),
        ]);

        $this->expectException(YandexMarkupChangedException::class);
        $this->expectExceptionMessage('Не удалось найти блок данных state-view');

        $this->service->parseOrganization('79409187372');
    }

    public function test_broken_json_in_state_view_throws_markup_changed_exception(): void
    {
        $brokenHtml = '<html><body><script type="application/json" class="state-view">{"invalid": json</script></body></html>';

        Http::fake([
            '*' => Http::response($brokenHtml, 200),
        ]);

        $this->expectException(YandexMarkupChangedException::class);
        $this->expectExceptionMessage('Ошибка декодирования встроенного JSON состояния');

        $this->service->parseOrganization('79409187372');
    }

    public function test_organization_not_found_in_stack_throws_exception(): void
    {
        $payload = [
            'stack' => [
                [
                    'error' => 'not-found',
                ],
            ],
        ];

        $html = '<script type="application/json" class="state-view">'.json_encode($payload).'</script>';

        Http::fake([
            '*' => Http::response($html, 200),
        ]);

        $this->expectException(YandexOrganizationNotFoundException::class);
        $this->expectExceptionMessage('не найдена на Яндекс.Картах');

        $this->service->parseOrganization('79409187372');
    }

    public function test_empty_items_in_stack_throws_exception(): void
    {
        $payload = [
            'stack' => [
                [
                    'results' => [
                        'items' => [],
                    ],
                ],
            ],
        ];

        $html = '<script type="application/json" class="state-view">'.json_encode($payload).'</script>';

        Http::fake([
            '*' => Http::response($html, 200),
        ]);

        $this->expectException(YandexOrganizationNotFoundException::class);
        $this->expectExceptionMessage('Список данных организации пуст');

        $this->service->parseOrganization('79409187372');
    }

    public function test_http_connection_failure_throws_parser_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $this->expectException(YandexParserException::class);
        $this->expectExceptionMessage('Ошибка подключения к Яндекс.Картам');

        $this->service->parseOrganization('79409187372');
    }

    public function test_circuit_breaker_open_blocks_requests_immediately(): void
    {
        $mockBreaker = $this->createMock(CircuitBreakerInterface::class);
        $mockBreaker->expects($this->once())
            ->method('isAvailable')
            ->with('yandex_maps')
            ->willReturn(false);

        $serviceWithBreaker = new YandexMapsParserService(null, $mockBreaker);

        $this->expectException(CircuitBreakerOpenException::class);
        $serviceWithBreaker->parseOrganization('79409187372');
    }

    public function test_retry_with_next_proxy_on_captcha_succeeds_on_second_attempt(): void
    {
        $mockRotator = $this->createMock(ProxyRotatorInterface::class);

        $proxy1 = new ProxyDto(1, 'http', '192.168.1.1', 8080);
        $proxy2 = new ProxyDto(2, 'http', '192.168.1.2', 8080);

        // 1-я попытка: возвращаем proxy1, 2-я попытка: proxy2
        $mockRotator->expects($this->exactly(2))
            ->method('getNextProxy')
            ->willReturnOnConsecutiveCalls($proxy1, $proxy2);

        // proxy1 помечается как попавший на капчу
        $mockRotator->expects($this->once())
            ->method('markCaptcha')
            ->with(1, 30);

        // proxy2 помечается как успешный
        $mockRotator->expects($this->once())
            ->method('markSuccess')
            ->with(2, $this->anything());

        $payload = [
            'stack' => [
                [
                    'results' => [
                        'items' => [
                            [
                                'id' => '79409187372',
                                'title' => 'Успешная организация',
                                'ratingData' => ['rating' => 4.5, 'ratingsCount' => 10, 'reviewsCount' => 5],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $htmlValid = '<html><head><script type="application/json" class="state-view">'.json_encode($payload).'</script></head></html>';
        $htmlCaptcha = '<html><body><div class="smartcaptcha">Подтвердите, что вы не робот</div></body></html>';

        // 1-й запрос возвращает капчу, 2-й — валидный HTML
        Http::fake([
            'https://yandex.ru/maps/org/79409187372/reviews/' => Http::sequence()
                ->push($htmlCaptcha, 200)
                ->push($htmlValid, 200),
        ]);

        $serviceWithRotator = new YandexMapsParserService($mockRotator);
        $result = $serviceWithRotator->parseOrganization('79409187372');

        $this->assertInstanceOf(ParsedOrganizationDto::class, $result);
        $this->assertSame('Успешная организация', $result->name);
    }
}
