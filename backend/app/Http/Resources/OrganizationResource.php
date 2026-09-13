<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
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
            'yandex_org_id' => $this->yandex_org_id,
            'name' => $this->name,
            'url' => $this->url,
            'address' => $this->address,
            'rating' => $this->rating ? round((float) $this->rating, 2) : null,
            'ratings_count' => (int) $this->ratings_count,
            'reviews_count' => (int) $this->reviews_count,
            'sync_status' => $this->sync_status,
            'sync_progress' => (int) $this->sync_progress,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'last_sync_error' => $this->last_sync_error,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
