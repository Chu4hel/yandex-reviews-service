<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\Contracts\CircuitBreakerInterface;
use App\Domain\Contracts\ProxyRotatorInterface;
use App\Domain\Contracts\YandexParserInterface;
use App\Domain\DTO\ParsedOrganizationDto;
use App\Domain\DTO\ParsedReviewDto;
use App\Domain\DTO\ParsedReviewsBatchDto;
use App\Domain\DTO\ProxyDto;
use App\Domain\Exceptions\CircuitBreakerOpenException;
use App\Domain\Exceptions\YandexCaptchaDetectedException;
use App\Domain\Exceptions\YandexMarkupChangedException;
use App\Domain\Exceptions\YandexOrganizationNotFoundException;
use App\Domain\Exceptions\YandexParserException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YandexMapsParserService implements YandexParserInterface
{
    /**
     * Пул браузерных User-Agent для ротации цифрового отпечатка.
     *
     * @var array<int, string>
     */
    protected array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36 Edg/123.0.0.0',
    ];

    public function __construct(
        protected ?ProxyRotatorInterface $proxyRotator = null,
        protected ?CircuitBreakerInterface $circuitBreaker = null
    ) {}

    public function normalizeUrl(string $input): string
    {
        $input = trim($input);

        // Прямой ввод числового идентификатора карточки
        if (preg_match('/^\d+$/', $input)) {
            return "https://yandex.ru/maps/org/{$input}/reviews/";
        }

        // Разрешение коротких ссылок через следование по редиректам
        if (str_contains($input, 'maps.app.goo.gl') || str_contains($input, '/maps/-/')) {
            $input = $this->resolveRedirectUrl($input);
        }

        // Извлечение идентификатора из query-параметра oid
        if (preg_match('/[?&]oid=(\d+)/', $input, $matches)) {
            return "https://yandex.ru/maps/org/{$matches[1]}/reviews/";
        }

        // Нормализация базового URL Яндекс.Карт
        if (preg_match('/https?:\/\/(?:www\.)?(?:yandex\.[a-z]+|maps\.yandex\.[a-z]+)\/maps\/org\/(?:[^\/]+\/)?(\d+)/i', $input, $matches)) {
            $orgId = $matches[1];
            // Сохранение человекопонятного слага при наличии
            if (preg_match('/\/maps\/org\/([a-zA-Z0-9_-]+)\/\d+/i', $input, $slugMatch)) {
                $slug = $slugMatch[1];

                return "https://yandex.ru/maps/org/{$slug}/{$orgId}/reviews/";
            }

            return "https://yandex.ru/maps/org/{$orgId}/reviews/";
        }

        return $input;
    }

    public function extractOrgId(string $input): string
    {
        $normalized = $this->normalizeUrl($input);

        if (preg_match('/\/org\/(?:[^\/]+\/)?(\d+)/i', $normalized, $matches)) {
            return $matches[1];
        }

        if (preg_match('/[?&]oid=(\d+)/', $normalized, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^\d+$/', trim($input))) {
            return trim($input);
        }

        throw new YandexParserException("Не удалось извлечь идентификатор организации из ссылки: {$input}");
    }

    public function parseOrganization(string $url): ParsedOrganizationDto
    {
        $normalizedUrl = $this->normalizeUrl($url);
        $data = $this->fetchStateViewData($normalizedUrl, 1);

        $item = $this->extractOrganizationItem($data, $normalizedUrl);

        $orgId = (string) ($item['id'] ?? $this->extractOrgId($normalizedUrl));
        $name = (string) ($item['title'] ?? 'Без названия');
        $address = isset($item['address']) ? (string) $item['address'] : (isset($item['fullAddress']) ? (string) $item['fullAddress'] : null);

        $ratingData = $item['ratingData'] ?? [];
        $rating = isset($ratingData['ratingValue']) ? round((float) $ratingData['ratingValue'], 2) : null;
        $ratingsCount = (int) ($ratingData['ratingCount'] ?? 0);
        $reviewsCount = (int) ($ratingData['reviewCount'] ?? 0);

        // Парсинг первой страницы отзывов
        $reviews = [];
        $totalPages = 1;
        if (isset($item['reviewResults'])) {
            $rawReviews = $item['reviewResults']['reviews'] ?? [];
            foreach ($rawReviews as $rev) {
                $reviews[] = $this->mapReviewDto($rev);
            }
            $params = $item['reviewResults']['params'] ?? [];
            $totalPages = max(1, (int) ($params['totalPages'] ?? 1));
            if ($reviewsCount === 0 && isset($params['count'])) {
                $reviewsCount = (int) $params['count'];
            }
        }

        return new ParsedOrganizationDto(
            yandexOrgId: $orgId,
            name: $name,
            url: $normalizedUrl,
            address: $address,
            rating: $rating,
            ratingsCount: $ratingsCount,
            reviewsCount: $reviewsCount,
            initialReviews: $reviews,
            totalPages: $totalPages,
        );
    }

    public function parseReviewsPage(string $url, int $page): ParsedReviewsBatchDto
    {
        $normalizedUrl = $this->normalizeUrl($url);
        $data = $this->fetchStateViewData($normalizedUrl, $page);

        $item = $this->extractOrganizationItem($data, $normalizedUrl);

        $reviews = [];
        $totalPages = 1;
        $totalCount = 0;

        if (isset($item['reviewResults'])) {
            $rawReviews = $item['reviewResults']['reviews'] ?? [];
            foreach ($rawReviews as $rev) {
                $reviews[] = $this->mapReviewDto($rev);
            }
            $params = $item['reviewResults']['params'] ?? [];
            $totalPages = max(1, (int) ($params['totalPages'] ?? 1));
            $totalCount = (int) ($params['count'] ?? count($reviews));
        }

        $hasNext = $page < $totalPages && count($reviews) > 0;

        return new ParsedReviewsBatchDto(
            reviews: $reviews,
            page: $page,
            totalPages: $totalPages,
            totalReviewsCount: $totalCount,
            hasNextPage: $hasNext,
        );
    }

    /**
     * Загрузка HTML страницы Яндекс.Карт и декодирование встроенного JSON состояния state-view.
     *
     * @return array<string, mixed>
     *
     * @throws YandexParserException
     */
    protected function fetchStateViewData(string $url, int $page = 1): array
    {
        $targetUrl = $url;
        if ($page > 1) {
            $separator = str_contains($targetUrl, '?') ? '&' : '?';
            $targetUrl .= "{$separator}page={$page}";
        }

        // Проверка состояния предохранителя Circuit Breaker
        if ($this->circuitBreaker !== null && ! $this->circuitBreaker->isAvailable('yandex_maps')) {
            Log::warning('YandexMapsParser: запрос заблокирован предохранителем (Circuit Breaker OPEN)', [
                'target_url' => $targetUrl,
                'page' => $page,
            ]);
            throw new CircuitBreakerOpenException;
        }

        $maxAttempts = ($this->proxyRotator !== null) ? 3 : 1;
        $lastException = null;
        $usedProxy = false;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $proxy = $this->proxyRotator?->getNextProxy();

            // Если ротатор настроен, но нет доступных прокси (пул пуст или все в карантине)
            if ($this->proxyRotator !== null && $proxy === null) {
                Log::warning('YandexMapsParser: нет доступных активных прокси в пуле, переход к прямому подключению', [
                    'target_url' => $targetUrl,
                    'page' => $page,
                    'attempt' => $attempt,
                ]);
                break;
            }

            if ($proxy !== null) {
                $usedProxy = true;
            }

            try {
                return $this->executeHttpRequest($targetUrl, $page, $proxy, $attempt, $maxAttempts);
            } catch (YandexParserException $e) {
                $lastException = $e;
                if ($attempt < $maxAttempts && $proxy !== null) {
                    usleep(200000);

                    continue;
                }
            } catch (\Throwable $e) {
                $lastException = new YandexParserException("Ошибка подключения к Яндекс.Картам: {$e->getMessage()}", 0, $e);
                if ($attempt < $maxAttempts && $proxy !== null) {
                    usleep(200000);

                    continue;
                }
            }
        }

        // Фолбэк на прямое подключение без прокси, если все попытки через прокси завершились неудачей
        if ($usedProxy || $this->proxyRotator !== null) {
            Log::info('YandexMapsParser: выполнение фолбэка на прямое подключение без прокси', [
                'target_url' => $targetUrl,
                'page' => $page,
                'previous_error' => $lastException?->getMessage(),
            ]);

            try {
                return $this->executeHttpRequest($targetUrl, $page, null, 1, 1);
            } catch (\Throwable $fallbackEx) {
                Log::error('YandexMapsParser: фолбэк на прямое подключение также завершился сбоем', [
                    'target_url' => $targetUrl,
                    'page' => $page,
                    'error' => $fallbackEx->getMessage(),
                ]);

                if ($fallbackEx instanceof YandexParserException) {
                    throw $fallbackEx;
                }

                throw new YandexParserException("Сбой при прямом подключении после отказа прокси: {$fallbackEx->getMessage()}", 0, $fallbackEx);
            }
        }

        throw $lastException ?? new YandexParserException('Не удалось получить данные с Яндекс.Карт');
    }

    /**
     * Выполнение одного HTTP-запроса к Яндекс.Картам через прокси или напрямую.
     *
     * @return array<string, mixed>
     *
     * @throws YandexParserException
     */
    protected function executeHttpRequest(string $targetUrl, int $page, ?ProxyDto $proxy, int $attempt, int $maxAttempts): array
    {
        $ua = $this->getRandomUserAgent();
        $startTime = microtime(true);
        $connectTimeout = (int) config('services.proxy.connect_timeout', 6);
        $requestTimeout = (int) config('services.proxy.request_timeout', 15);

        try {
            $httpClient = Http::withHeaders([
                'User-Agent' => $ua,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                'Sec-Ch-Ua' => '"Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
                'Sec-Ch-Ua-Mobile' => '?0',
                'Sec-Ch-Ua-Platform' => '"Windows"',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
                'Upgrade-Insecure-Requests' => '1',
            ])
                ->timeout($requestTimeout)
                ->withOptions([
                    'connect_timeout' => $connectTimeout,
                ]);

            if ($proxy !== null) {
                $httpClient = $httpClient->withOptions([
                    'proxy' => $proxy->toHttpOption(),
                    'connect_timeout' => $connectTimeout,
                ]);
            }

            $response = $httpClient->get($targetUrl);
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $html = $response->body();

            // 1. Детекция SmartCaptcha и антибот-проверок
            if (str_contains($html, 'captcha-page') || str_contains($html, 'smartcaptcha') || str_contains($html, 'showcaptcha')) {
                if ($this->proxyRotator !== null && $proxy !== null) {
                    $this->proxyRotator->markCaptcha($proxy->id, 30);
                }
                $this->circuitBreaker?->recordFailure('yandex_maps');

                Log::warning("YandexMapsParser: обнаружена капча (попытка {$attempt}/{$maxAttempts})", [
                    'target_url' => $targetUrl,
                    'page' => $page,
                    'proxy' => $proxy ? $proxy->toMaskedString() : 'direct',
                    'duration_ms' => $durationMs,
                    'http_status' => $response->status(),
                ]);

                throw new YandexCaptchaDetectedException;
            }

            // 2. Извлечение серверного блока состояния state-view
            if (! preg_match('/<script type="application\/json" class="state-view">(.*?)<\/script>/s', $html, $matches)) {
                $this->circuitBreaker?->recordFailure('yandex_maps');
                Log::error('YandexMapsParser: тег state-view не найден в ответе (возможна смена разметки)', [
                    'target_url' => $targetUrl,
                    'page' => $page,
                    'proxy' => $proxy ? $proxy->toMaskedString() : 'direct',
                    'http_status' => $response->status(),
                    'duration_ms' => $durationMs,
                    'html_snippet' => mb_substr($html, 0, 300),
                ]);
                throw new YandexMarkupChangedException('Не удалось найти блок данных state-view в ответе Яндекс.Карт. Возможно, изменилась вёрстка платформы.');
            }

            $decoded = json_decode($matches[1], true);
            if (! is_array($decoded)) {
                $this->circuitBreaker?->recordFailure('yandex_maps');
                Log::error('YandexMapsParser: ошибка декодирования JSON state-view', [
                    'target_url' => $targetUrl,
                    'page' => $page,
                    'proxy' => $proxy ? $proxy->toMaskedString() : 'direct',
                    'json_error' => json_last_error_msg(),
                ]);
                throw new YandexMarkupChangedException('Ошибка декодирования встроенного JSON состояния Яндекс.Карт.');
            }

            // Успешный ответ: фиксируем в ротаторе прокси и сбрасываем счетчик Circuit Breaker
            if ($this->proxyRotator !== null && $proxy !== null) {
                $this->proxyRotator->markSuccess($proxy->id, $durationMs);
            }
            $this->circuitBreaker?->recordSuccess('yandex_maps');

            Log::debug('YandexMapsParser: успешно получено и декодировано состояние state-view', [
                'target_url' => $targetUrl,
                'page' => $page,
                'proxy' => $proxy ? $proxy->toMaskedString() : 'direct',
                'http_status' => $response->status(),
                'duration_ms' => $durationMs,
            ]);

            return $decoded;
        } catch (YandexParserException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            if ($this->proxyRotator !== null && $proxy !== null) {
                $this->proxyRotator->markFailed($proxy->id, $e->getMessage());
            }
            $this->circuitBreaker?->recordFailure('yandex_maps');

            Log::error("YandexMapsParser: сетевой сбой (попытка {$attempt}/{$maxAttempts})", [
                'target_url' => $targetUrl,
                'page' => $page,
                'proxy' => $proxy ? $proxy->toMaskedString() : 'direct',
                'duration_ms' => $durationMs,
                'error_class' => get_class($e),
                'error' => $e->getMessage(),
            ]);

            throw new YandexParserException("Ошибка подключения к Яндекс.Картам: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Извлечение структуры данных организации из дерева состояния.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws YandexOrganizationNotFoundException
     */
    protected function extractOrganizationItem(array $data, string $url): array
    {
        $stack = $data['stack'] ?? [];
        if (empty($stack)) {
            throw new YandexOrganizationNotFoundException('Данные организации не найдены в объекте карточки Яндекс.Карт.');
        }

        $firstStack = $stack[0] ?? [];
        if (isset($firstStack['error']) && $firstStack['error'] === 'not-found') {
            throw new YandexOrganizationNotFoundException("Организация по адресу {$url} не найдена на Яндекс.Картах.");
        }

        $items = $firstStack['results']['items'] ?? [];
        if (empty($items)) {
            throw new YandexOrganizationNotFoundException("Список данных организации пуст для {$url}. Возможно, карточка скрыта или удалена.");
        }

        return $items[0];
    }

    /**
     * Преобразование сырых данных отзыва в типизированный DTO.
     *
     * @param  array<string, mixed>  $raw
     */
    protected function mapReviewDto(array $raw): ParsedReviewDto
    {
        $author = $raw['author'] ?? [];
        $businessComment = $raw['businessComment'] ?? null;

        $photos = null;
        if (! empty($raw['photos']) && is_array($raw['photos'])) {
            $parsedPhotos = [];
            foreach ($raw['photos'] as $photoItem) {
                if (! is_array($photoItem)) {
                    continue;
                }
                $template = isset($photoItem['urlTemplate']) ? (string) $photoItem['urlTemplate'] : null;
                if (! $template) {
                    continue;
                }
                $photoId = isset($photoItem['id']) ? (string) $photoItem['id'] : md5($template);
                $previewUrl = str_replace('{size}', 'L', $template);
                $fullUrl = str_replace('{size}', 'orig', $template);

                $parsedPhotos[] = [
                    'id' => $photoId,
                    'preview_url' => $previewUrl,
                    'full_url' => $fullUrl,
                ];
            }
            $photos = ! empty($parsedPhotos) ? $parsedPhotos : null;
        }

        $rawAvatar = ! empty($author['avatarUrl'])
            ? (string) $author['avatarUrl']
            : (! empty($author['avatarUrlTemplate']) ? (string) $author['avatarUrlTemplate'] : null);

        $avatarUrl = null;
        if ($rawAvatar !== null && trim($rawAvatar) !== '') {
            $cleanAvatar = trim($rawAvatar);
            if (str_starts_with($cleanAvatar, '//')) {
                $cleanAvatar = 'https:'.$cleanAvatar;
            }
            $avatarUrl = str_replace('{size}', 'islands-middle', $cleanAvatar);
        }

        return new ParsedReviewDto(
            yandexReviewId: (string) ($raw['reviewId'] ?? md5((string) json_encode($raw))),
            authorName: isset($author['name']) ? (string) $author['name'] : 'Пользователь',
            authorAvatarUrl: $avatarUrl,
            authorLevel: isset($author['professionLevel']) ? (string) $author['professionLevel'] : (isset($author['rtb']) ? (string) $author['rtb'] : null),
            rating: (int) ($raw['rating'] ?? 5),
            text: isset($raw['text']) ? (string) $raw['text'] : null,
            publishedAt: isset($raw['updatedTime']) ? (string) $raw['updatedTime'] : null,
            businessResponseText: isset($businessComment['text']) ? (string) $businessComment['text'] : null,
            businessResponseAt: isset($businessComment['updatedTime']) ? (string) $businessComment['updatedTime'] : null,
            photos: $photos,
        );
    }

    /**
     * Разрешение короткой ссылки в конечный URL.
     */
    protected function resolveRedirectUrl(string $url): string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->getRandomUserAgent(),
            ])
                ->timeout(10)
                ->get($url);

            return $response->effectiveUri() ? (string) $response->effectiveUri() : $url;
        } catch (\Throwable $e) {
            return $url;
        }
    }

    protected function getRandomUserAgent(): string
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }
}
