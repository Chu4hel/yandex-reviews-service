<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => (int) $this->organization_id,
            'yandex_review_id' => $this->yandex_review_id,
            'author_name' => $this->author_name,
            'author_avatar_url' => $this->author_avatar_url,
            'author_level' => $this->author_level,
            'rating' => (int) $this->rating,
            'text' => $this->text,
            'published_at' => $this->published_at?->toIso8601String(),
            'business_response_text' => $this->business_response_text,
            'business_response_at' => $this->business_response_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
