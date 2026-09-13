<?php

declare(strict_types=1);

namespace App\Domain\DTO;

class ParsedReviewsBatchDto
{
    /**
     * @param  array<int, ParsedReviewDto>  $reviews
     */
    public function __construct(
        public readonly array $reviews,
        public readonly int $page,
        public readonly int $totalPages,
        public readonly int $totalReviewsCount,
        public readonly bool $hasNextPage,
    ) {}
}
