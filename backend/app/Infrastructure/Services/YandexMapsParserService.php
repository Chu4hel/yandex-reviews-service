<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\Contracts\ProxyRotatorInterface;
use App\Domain\Contracts\YandexParserInterface;
use App\Domain\DTO\ParsedOrganizationDto;
use App\Domain\DTO\ParsedReviewDto;
use App\Domain\DTO\ParsedReviewsBatchDto;
use App\Domain\Exceptions\YandexCaptchaDetectedException;
use App\Domain\Exceptions\YandexMarkupChangedException;
use App\Domain\Exceptions\YandexOrganizationNotFoundException;
use App\Domain\Exceptions\YandexParserException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YandexMapsParserService implements YandexParserInterface
{
    /**
     * Pool of realistic browser User-Agents for header rotation.
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
        protected ?ProxyRotatorInterface $proxyRotator = null
    ) {}

    public function normalizeUrl(string $input): string
    {
        $input = trim($input);

        // If numeric ID entered
        if (preg_match('/^\d+$/', $input)) {
            return "https://yandex.ru/maps/org/{$input}/reviews/";
        }

        // If short link, follow redirect to resolve real URL
        if (str_contains($input, 'maps.app.goo.gl') || str_contains($input, '/maps/-/')) {
            $input = $this->resolveRedirectUrl($input);
        }

        // Check if URL has query parameters with oid=
        if (preg_match('/[?&]oid=(\d+)/', $input, $matches)) {
            return "https://yandex.ru/maps/org/{$matches[1]}/reviews/";
        }

        // Normalize base Yandex maps URL
        if (preg_match('/https?:\/\/(?:www\.)?(?:yandex\.[a-z]+|maps\.yandex\.[a-z]+)\/maps\/org\/(?:[^\/]+\/)?(\d+)/i', $input, $matches)) {
            $orgId = $matches[1];
            // Extract slug if present
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

        // Parse initial batch of reviews from the first page
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
     * Fetch HTML page from Yandex Maps and decode JSON state-view.
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

        $ua = $this->getRandomUserAgent();
        $startTime = microtime(true);
        $proxy = $this->proxyRotator?->getNextProxy();

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
            ])->timeout(20);

            if ($proxy !== null) {
                $httpClient = $httpClient->withOptions([
                    'proxy' => $proxy->toHttpOption(),
                ]);
            }

            $response = $httpClient->get($targetUrl);
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            if ($this->proxyRotator !== null && $proxy !== null) {
                $this->proxyRotator->markFailed($proxy->id, $e->getMessage());
            }

            Log::error('YandexMapsParser: сетевой сбой при обращении к Яндекс.Картам', [
                'target_url' => $targetUrl,
                'page' => $page,
                'proxy' => $proxy ? $proxy->toMaskedString() : 'direct',
                'duration_ms' => $durationMs,
                'error_class' => get_class($e),
                'error' => $e->getMessage(),
            ]);
            throw new YandexParserException("Ошибка подключения к Яндекс.Картам: {$e->getMessage()}", 0, $e);
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $html = $response->body();

        // 1. Detect Captcha / Bot protection
        if (str_contains($html, 'captcha-page') || str_contains($html, 'smartcaptcha') || str_contains($html, 'showcaptcha')) {
            if ($this->proxyRotator !== null && $proxy !== null) {
                $this->proxyRotator->markCaptcha($proxy->id, 30);
            }

            Log::warning('YandexMapsParser: обнаружена капча / антибот проверка Яндекса', [
                'target_url' => $targetUrl,
                'page' => $page,
                'proxy' => $proxy ? $proxy->toMaskedString() : 'direct',
                'duration_ms' => $durationMs,
                'http_status' => $response->status(),
                'user_agent' => $ua,
            ]);
            throw new YandexCaptchaDetectedException;
        }

        // 2. Extract state-view JSON
        if (! preg_match('/<script type="application\/json" class="state-view">(.*?)<\/script>/s', $html, $matches)) {
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
            Log::error('YandexMapsParser: ошибка декодирования JSON state-view', [
                'target_url' => $targetUrl,
                'page' => $page,
                'proxy' => $proxy ? $proxy->toMaskedString() : 'direct',
                'json_error' => json_last_error_msg(),
            ]);
            throw new YandexMarkupChangedException('Ошибка декодирования встроенного JSON состояния Яндекс.Карт.');
        }

        if ($this->proxyRotator !== null && $proxy !== null) {
            $this->proxyRotator->markSuccess($proxy->id, $durationMs);
        }

        Log::debug('YandexMapsParser: успешно получено и декодировано состояние state-view', [
            'target_url' => $targetUrl,
            'page' => $page,
            'proxy' => $proxy ? $proxy->toMaskedString() : 'direct',
            'http_status' => $response->status(),
            'duration_ms' => $durationMs,
        ]);

        return $decoded;
    }

    /**
     * Extract organization item from state-view.
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
     * Map raw review array to ParsedReviewDto.
     *
     * @param  array<string, mixed>  $raw
     */
    protected function mapReviewDto(array $raw): ParsedReviewDto
    {
        $author = $raw['author'] ?? [];
        $businessComment = $raw['businessComment'] ?? null;

        return new ParsedReviewDto(
            yandexReviewId: (string) ($raw['reviewId'] ?? md5(json_encode($raw))),
            authorName: isset($author['name']) ? (string) $author['name'] : 'Пользователь',
            authorAvatarUrl: ! empty($author['avatarUrl']) ? (string) $author['avatarUrl'] : null,
            authorLevel: isset($author['professionLevel']) ? (string) $author['professionLevel'] : (isset($author['rtb']) ? (string) $author['rtb'] : null),
            rating: (int) ($raw['rating'] ?? 5),
            text: isset($raw['text']) ? (string) $raw['text'] : null,
            publishedAt: isset($raw['updatedTime']) ? (string) $raw['updatedTime'] : null,
            businessResponseText: isset($businessComment['text']) ? (string) $businessComment['text'] : null,
            businessResponseAt: isset($businessComment['updatedTime']) ? (string) $businessComment['updatedTime'] : null,
        );
    }

    /**
     * Resolve short link redirect to final URL.
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
