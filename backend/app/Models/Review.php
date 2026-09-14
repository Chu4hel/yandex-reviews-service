<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
 * @property Carbon|null $published_at
 * @property string|null $business_response_text
 * @property Carbon|null $business_response_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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

    /**
     * Полнотекстовый поиск по отзывам (мультидрайвер: MySQL Fulltext, SQLite/PG токенизированный).
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null) {
            return $query;
        }

        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        $driver = DB::getDriverName();

        // 1. Для MySQL/MariaDB используем MATCH(...) AGAINST(? IN BOOLEAN MODE)
        if ($driver === 'mysql') {
            /** @var list<string> $tokens */
            $tokens = preg_split('/[\s,\.\+\-]+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $booleanTokens = array_filter(array_map(function (string $token): ?string {
                $cleaned = preg_replace('/[+\-><()~*\"@]+/', '', $token);

                return $cleaned !== null && $cleaned !== '' ? '+'.$cleaned.'*' : null;
            }, $tokens));

            if (! empty($booleanTokens)) {
                $booleanTerm = implode(' ', $booleanTokens);

                return $query->whereRaw(
                    'MATCH(text, author_name, business_response_text) AGAINST(? IN BOOLEAN MODE)',
                    [$booleanTerm]
                );
            }
        }

        // 2. Универсальный токенизированный полнотекстовый поиск (SQLite, PostgreSQL, fallback)
        // Разбивает запрос на токены и ищет пересечение (AND) по всем словам в text, author_name или business_response_text
        /** @var list<string> $tokens */
        $tokens = preg_split('/[\s,\.\+\-]+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $validTokens = array_values(array_filter($tokens, fn (string $t): bool => mb_strlen($t, 'UTF-8') >= 1));

        if (empty($validTokens)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($validTokens): void {
            foreach ($validTokens as $token) {
                $lowered = mb_strtolower($token, 'UTF-8');
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $lowered);
                $pattern = "%{$escaped}%";

                $q->where(function (Builder $sub) use ($pattern): void {
                    $sub->whereRaw('LOWER(text) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(author_name) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(business_response_text) LIKE ?', [$pattern]);
                });
            }
        });
    }

    /**
     * Сортировка по релевантности совпадения поискового запроса.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOrderByRelevance(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $query->orderByDesc('published_at');
        }

        $lowered = mb_strtolower($term, 'UTF-8');
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $lowered);
        $pattern = "%{$escaped}%";

        // Бонус релевантности: точное совпадение всей фразы в авторе (x10), точное совпадение в тексте (x5)
        return $query->orderByRaw(
            '(CASE WHEN LOWER(author_name) LIKE ? THEN 10 ELSE 0 END + CASE WHEN LOWER(text) LIKE ? THEN 5 ELSE 0 END) DESC',
            [$pattern, $pattern]
        )->orderByDesc('published_at');
    }
}
