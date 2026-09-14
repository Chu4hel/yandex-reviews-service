<?php

declare(strict_types=1);

namespace App\Domain\Contracts;

use App\Domain\DTO\ParsedOrganizationDto;
use App\Domain\DTO\ParsedReviewsBatchDto;
use App\Domain\Exceptions\YandexParserException;

interface YandexParserInterface
{
    /**
     * Сбор метаданных организации и начального пакета отзывов.
     *
     * @throws YandexParserException
     */
    public function parseOrganization(string $url): ParsedOrganizationDto;

    /**
     * Сбор конкретной страницы отзывов (по 50 отзывов на страницу).
     *
     * @throws YandexParserException
     */
    public function parseReviewsPage(string $url, int $page): ParsedReviewsBatchDto;

    /**
     * Нормализация URL или ID в канонический адрес страницы отзывов на Яндекс.Картах.
     */
    public function normalizeUrl(string $input): string;

    /**
     * Извлечение числового идентификатора организации из URL или произвольной строки.
     */
    public function extractOrgId(string $input): string;
}
