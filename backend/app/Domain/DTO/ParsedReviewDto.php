<?php

declare(strict_types=1);

namespace App\Domain\DTO;

class ParsedReviewDto
{
    public function __construct(
        public readonly string $yandexReviewId,
        public readonly ?string $authorName,
        public readonly ?string $authorAvatarUrl,
        public readonly ?string $authorLevel,
        public readonly int $rating,
        public readonly ?string $text,
        public readonly ?string $publishedAt,
        public readonly ?string $businessResponseText = null,
        public readonly ?string $businessResponseAt = null,
        /** @var array<int, array{id: string, preview_url: string, full_url: string}>|null */
        public readonly ?array $photos = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'yandex_review_id' => $this->yandexReviewId,
            'author_name' => $this->authorName,
            'author_avatar_url' => $this->authorAvatarUrl,
            'author_level' => $this->authorLevel,
            'rating' => $this->rating,
            'text' => $this->text,
            'photos' => $this->photos,
            'published_at' => $this->publishedAt,
            'business_response_text' => $this->businessResponseText,
            'business_response_at' => $this->businessResponseAt,
        ];
    }
}
