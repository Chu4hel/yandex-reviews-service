<?php

declare(strict_types=1);

namespace App\Domain\Contracts;

use App\Domain\DTO\ParsedOrganizationDto;
use App\Domain\DTO\ParsedReviewsBatchDto;
use App\Domain\Exceptions\YandexParserException;

interface YandexParserInterface
{
    /**
     * Parse organization metadata and initial batch of reviews.
     *
     * @throws YandexParserException
     */
    public function parseOrganization(string $url): ParsedOrganizationDto;

    /**
     * Parse a specific batch/page of reviews (50 reviews per page).
     *
     * @throws YandexParserException
     */
    public function parseReviewsPage(string $url, int $page): ParsedReviewsBatchDto;

    /**
     * Normalize URL or ID into a clean canonical Yandex Maps reviews URL.
     */
    public function normalizeUrl(string $input): string;

    /**
     * Extract Yandex Organization ID from URL or raw input.
     */
    public function extractOrgId(string $input): string;
}
