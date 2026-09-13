<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model OrganizationSnapshot
 *
 * @property int $id
 * @property int $organization_id
 * @property float|null $rating_before
 * @property float|null $rating_after
 * @property int|null $ratings_count_before
 * @property int|null $ratings_count_after
 * @property int|null $reviews_count_before
 * @property int|null $reviews_count_after
 * @property int $new_reviews_added
 * @property int $updated_reviews_count
 * @property Carbon $snapshot_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrganizationSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'rating_before',
        'rating_after',
        'ratings_count_before',
        'ratings_count_after',
        'reviews_count_before',
        'reviews_count_after',
        'new_reviews_added',
        'updated_reviews_count',
        'snapshot_at',
    ];

    protected function casts(): array
    {
        return [
            'rating_before' => 'float',
            'rating_after' => 'float',
            'ratings_count_before' => 'integer',
            'ratings_count_after' => 'integer',
            'reviews_count_before' => 'integer',
            'reviews_count_after' => 'integer',
            'new_reviews_added' => 'integer',
            'updated_reviews_count' => 'integer',
            'snapshot_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
