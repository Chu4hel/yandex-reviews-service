<?php

namespace App\Domain\DTO;

class SyncResultDto
{
    public function __construct(
        public readonly int $organizationId,
        public readonly int $totalReviewsSaved,
        public readonly int $newReviewsAdded,
        public readonly int $updatedReviewsCount,
        public readonly ?float $ratingBefore,
        public readonly ?float $ratingAfter,
        public readonly int $reviewsCountBefore,
        public readonly int $reviewsCountAfter,
        public readonly string $status,
    ) {
    }
}
