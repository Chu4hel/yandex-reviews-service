<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Model Organization
 *
 * @property int $id
 * @property string $yandex_org_id
 * @property string $name
 * @property string $url
 * @property string|null $address
 * @property float|null $rating
 * @property int $ratings_count
 * @property int $reviews_count
 * @property string $sync_status
 * @property int $sync_progress
 * @property Carbon|null $last_synced_at
 * @property string|null $last_sync_error
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Organization extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'yandex_org_id',
        'name',
        'url',
        'address',
        'rating',
        'ratings_count',
        'reviews_count',
        'sync_status',
        'sync_progress',
        'last_synced_at',
        'last_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'sync_progress' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->orderBy('published_at', 'desc');
    }

    /**
     * @return HasMany<OrganizationSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(OrganizationSnapshot::class)->orderBy('snapshot_at', 'desc');
    }
}
