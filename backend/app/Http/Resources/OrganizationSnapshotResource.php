<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrganizationSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganizationSnapshot
 */
class OrganizationSnapshotResource extends JsonResource
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
            'rating_before' => $this->rating_before !== null ? (float) $this->rating_before : null,
            'rating_after' => $this->rating_after !== null ? (float) $this->rating_after : null,
            'ratings_count_before' => $this->ratings_count_before !== null ? (int) $this->ratings_count_before : null,
            'ratings_count_after' => $this->ratings_count_after !== null ? (int) $this->ratings_count_after : null,
            'reviews_count_before' => $this->reviews_count_before !== null ? (int) $this->reviews_count_before : null,
            'reviews_count_after' => $this->reviews_count_after !== null ? (int) $this->reviews_count_after : null,
            'new_reviews_added' => (int) $this->new_reviews_added,
            'updated_reviews_count' => (int) $this->updated_reviews_count,
            'snapshot_at' => $this->snapshot_at->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
