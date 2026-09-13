<?php

namespace App\Domain\DTO;

class ParsedOrganizationDto
{
    /**
     * @param array<int, ParsedReviewDto> $initialReviews
     */
    public function __construct(
        public readonly string $yandexOrgId,
        public readonly string $name,
        public readonly string $url,
        public readonly ?string $address,
        public readonly ?float $rating,
        public readonly int $ratingsCount,
        public readonly int $reviewsCount,
        public readonly array $initialReviews = [],
        public readonly int $totalPages = 1,
    ) {
    }
}
