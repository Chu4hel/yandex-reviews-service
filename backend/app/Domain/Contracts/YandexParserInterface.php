<?php

namespace App\Domain\Contracts;

use App\Domain\DTO\ParsedOrganizationDto;
use App\Domain\DTO\ParsedReviewsBatchDto;

interface YandexParserInterface
{
    /**
     * Parse organization metadata and initial batch of reviews.
     *
     * @param string $url
     * @return ParsedOrganizationDto
     * @throws \App\Domain\Exceptions\YandexParserException
     */
    public function parseOrganization(string $url): ParsedOrganizationDto;

    /**
     * Parse a specific batch/page of reviews (50 reviews per page).
     *
     * @param string $url
     * @param int $page
     * @return ParsedReviewsBatchDto
     * @throws \App\Domain\Exceptions\YandexParserException
     */
    public function parseReviewsPage(string $url, int $page): ParsedReviewsBatchDto;

    /**
     * Normalize URL or ID into a clean canonical Yandex Maps reviews URL.
     *
     * @param string $input
     * @return string
     */
    public function normalizeUrl(string $input): string;

    /**
     * Extract Yandex Organization ID from URL or raw input.
     *
     * @param string $input
     * @return string
     */
    public function extractOrgId(string $input): string;
}
