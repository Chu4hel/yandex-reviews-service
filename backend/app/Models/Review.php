<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Review
 *
 * @property int $id
 * @property int $organization_id
 * @property string $yandex_review_id
 * @property string|null $author_name
 * @property string|null $author_avatar_url
 * @property string|null $author_level
 * @property int $rating
 * @property string|null $text
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property string|null $business_response_text
 * @property \Illuminate\Support\Carbon|null $business_response_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'yandex_review_id',
        'author_name',
        'author_avatar_url',
        'author_level',
        'rating',
        'text',
        'published_at',
        'business_response_text',
        'business_response_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'published_at' => 'datetime',
            'business_response_at' => 'datetime',
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
