<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Contracts\YandexParserInterface;
use App\Domain\DTO\ParsedReviewDto;
use App\Domain\DTO\SyncResultDto;
use App\Domain\Exceptions\YandexParserException;
use App\Jobs\SyncOrganizationReviewsJob;
use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use App\Models\Review;
use App\Support\ContentSanitizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrganizationSyncService
{
    public function __construct(
        protected YandexParserInterface $parser
    ) {}

    /**
     * Connect an organization by Yandex Maps URL or ID.
     *
     * @throws YandexParserException
     */
    public function connectOrganization(string $inputUrl): Organization
    {
        $normalizedUrl = $this->parser->normalizeUrl($inputUrl);
        $orgId = $this->parser->extractOrgId($normalizedUrl);

        // Check if organization already exists in database
        $organization = Organization::where('yandex_org_id', $orgId)->first();

        if ($organization) {
            $organization->update([
                'url' => $normalizedUrl,
            ]);

            // Dispatch background sync if idle
            if ($organization->sync_status !== 'syncing') {
                SyncOrganizationReviewsJob::dispatch($organization->id);
            }

            return $organization;
        }

        // Parse initial metadata and reviews
        $parsedOrg = $this->parser->parseOrganization($normalizedUrl);

        return DB::transaction(function () use ($parsedOrg, $normalizedUrl) {
            $organization = Organization::create([
                'yandex_org_id' => $parsedOrg->yandexOrgId,
                'name' => ContentSanitizer::sanitizePlainText($parsedOrg->name, 'Организация'),
                'url' => $normalizedUrl,
                'address' => ContentSanitizer::sanitizePlainText($parsedOrg->address),
                'rating' => $parsedOrg->rating,
                'ratings_count' => $parsedOrg->ratingsCount,
                'reviews_count' => $parsedOrg->reviewsCount,
                'sync_status' => 'pending',
                'sync_progress' => 0,
                'last_synced_at' => null,
            ]);

            // Save initial reviews
            $newCount = 0;
            foreach ($parsedOrg->initialReviews as $reviewDto) {
                $this->upsertReview($organization->id, $reviewDto);
                $newCount++;
            }

            // Create initial snapshot
            OrganizationSnapshot::create([
                'organization_id' => $organization->id,
                'rating_before' => null,
                'rating_after' => $organization->rating,
                'ratings_count_before' => 0,
                'ratings_count_after' => $organization->ratings_count,
                'reviews_count_before' => 0,
                'reviews_count_after' => $organization->reviews_count,
                'new_reviews_added' => $newCount,
                'updated_reviews_count' => 0,
                'snapshot_at' => now(),
            ]);

            // Dispatch background job for full reviews sync (up to ~600)
            SyncOrganizationReviewsJob::dispatch($organization->id);

            return $organization;
        });
    }

    /**
     * Perform full sync of reviews for an organization.
     *
     * @param  int  $maxPages  Maximum pages to fetch (12 pages * 50 = 600 reviews)
     *
     * @throws YandexParserException
     */
    public function syncOrganizationReviews(Organization $organization, int $maxPages = 12): SyncResultDto
    {
        $startTime = microtime(true);

        Log::withContext([
            'organization_id' => $organization->id,
            'yandex_org_id' => $organization->yandex_org_id,
        ]);

        Log::info('OrganizationSyncService: начата синхронизация отзывов', [
            'url' => $organization->url,
            'max_pages' => $maxPages,
            'current_rating' => $organization->rating,
            'current_reviews_count' => $organization->reviews_count,
        ]);

        $organization->update([
            'sync_status' => 'syncing',
            'sync_progress' => 0,
            'last_sync_error' => null,
        ]);

        $ratingBefore = $organization->rating;
        $ratingsCountBefore = $organization->ratings_count;
        $reviewsCountBefore = $organization->reviews_count;

        $newAddedCount = 0;
        $updatedCount = 0;

        try {
            // 1. Refresh organization metadata first
            $parsedOrg = $this->parser->parseOrganization($organization->url);
            $organization->update([
                'name' => ContentSanitizer::sanitizePlainText($parsedOrg->name, $organization->name),
                'address' => ContentSanitizer::sanitizePlainText($parsedOrg->address, $organization->address),
                'rating' => $parsedOrg->rating,
                'ratings_count' => $parsedOrg->ratingsCount,
                'reviews_count' => $parsedOrg->reviewsCount,
            ]);

            $totalPagesToScan = min($parsedOrg->totalPages, $maxPages);
            if ($totalPagesToScan < 1) {
                $totalPagesToScan = 1;
            }

            Log::info('OrganizationSyncService: метаданные организации обновлены, запуск постраничного сбора', [
                'name' => $parsedOrg->name,
                'rating' => $parsedOrg->rating,
                'ratings_count' => $parsedOrg->ratingsCount,
                'reviews_count' => $parsedOrg->reviewsCount,
                'total_pages_detected' => $parsedOrg->totalPages,
                'pages_to_scan' => $totalPagesToScan,
            ]);

            // 2. Fetch pages
            for ($page = 1; $page <= $totalPagesToScan; $page++) {
                $pageStart = microtime(true);
                $batch = $this->parser->parseReviewsPage($organization->url, $page);

                $pageNewCount = 0;
                $pageUpdatedCount = 0;

                foreach ($batch->reviews as $reviewDto) {
                    $isNew = $this->upsertReview($organization->id, $reviewDto);
                    if ($isNew) {
                        $newAddedCount++;
                        $pageNewCount++;
                    } else {
                        $updatedCount++;
                        $pageUpdatedCount++;
                    }
                }

                $progress = (int) round(($page / $totalPagesToScan) * 100);
                $organization->update(['sync_progress' => min(99, $progress)]);

                Log::info("OrganizationSyncService: обработана страница отзывов {$page}/{$totalPagesToScan}", [
                    'page' => $page,
                    'page_duration_ms' => (int) round((microtime(true) - $pageStart) * 1000),
                    'reviews_in_page' => count($batch->reviews),
                    'page_new_reviews' => $pageNewCount,
                    'page_updated_reviews' => $pageUpdatedCount,
                    'has_next_page' => $batch->hasNextPage,
                    'progress_percent' => $progress,
                ]);

                if (! $batch->hasNextPage) {
                    break;
                }

                // Respectful pause between requests (300ms) to avoid rate limits
                usleep(300000);
            }

            // 3. Create snapshot of changes
            $snapshot = OrganizationSnapshot::create([
                'organization_id' => $organization->id,
                'rating_before' => $ratingBefore,
                'rating_after' => $organization->rating,
                'ratings_count_before' => $ratingsCountBefore,
                'ratings_count_after' => $organization->ratings_count,
                'reviews_count_before' => $reviewsCountBefore,
                'reviews_count_after' => $organization->reviews_count,
                'new_reviews_added' => $newAddedCount,
                'updated_reviews_count' => $updatedCount,
                'snapshot_at' => now(),
            ]);

            $organization->update([
                'sync_status' => 'completed',
                'sync_progress' => 100,
                'last_synced_at' => now(),
                'last_sync_error' => null,
            ]);

            $totalDuration = round(microtime(true) - $startTime, 2);
            $totalSaved = Review::where('organization_id', $organization->id)->count();

            Log::info('OrganizationSyncService: синхронизация успешно завершена', [
                'total_duration_sec' => $totalDuration,
                'new_reviews_added' => $newAddedCount,
                'updated_reviews_count' => $updatedCount,
                'total_reviews_saved' => $totalSaved,
                'snapshot_id' => $snapshot->id,
                'rating_delta' => $organization->rating !== null && $ratingBefore !== null ? round($organization->rating - $ratingBefore, 2) : 0,
            ]);

            return new SyncResultDto(
                organizationId: $organization->id,
                totalReviewsSaved: $totalSaved,
                newReviewsAdded: $newAddedCount,
                updatedReviewsCount: $updatedCount,
                ratingBefore: $ratingBefore,
                ratingAfter: $organization->rating,
                reviewsCountBefore: $reviewsCountBefore,
                reviewsCountAfter: $organization->reviews_count,
                status: 'completed',
            );
        } catch (\Throwable $e) {
            $totalDuration = round(microtime(true) - $startTime, 2);
            Log::error('OrganizationSyncService: критическая ошибка синхронизации', [
                'total_duration_sec' => $totalDuration,
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $organization->update([
                'sync_status' => 'failed',
                'last_sync_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Idempotently upsert review into database.
     *
     * @return bool True if created, False if updated
     */
    protected function upsertReview(int $organizationId, ParsedReviewDto $dto): bool
    {
        $publishedAt = null;
        if ($dto->publishedAt) {
            try {
                $publishedAt = Carbon::parse($dto->publishedAt);
            } catch (\Throwable $e) {
                $publishedAt = null;
            }
        }

        $businessResponseAt = null;
        if ($dto->businessResponseAt) {
            try {
                $businessResponseAt = Carbon::parse($dto->businessResponseAt);
            } catch (\Throwable $e) {
                $businessResponseAt = null;
            }
        }

        $review = Review::where('organization_id', $organizationId)
            ->where('yandex_review_id', $dto->yandexReviewId)
            ->first();

        $attributes = [
            'author_name' => ContentSanitizer::sanitizePlainText($dto->authorName, 'Пользователь'),
            'author_avatar_url' => ContentSanitizer::sanitizeUrl($dto->authorAvatarUrl),
            'author_level' => ContentSanitizer::sanitizePlainText($dto->authorLevel),
            'rating' => $dto->rating,
            'text' => ContentSanitizer::sanitizeText($dto->text),
            'published_at' => $publishedAt,
            'business_response_text' => ContentSanitizer::sanitizeText($dto->businessResponseText),
            'business_response_at' => $businessResponseAt,
        ];

        if ($review) {
            $review->update($attributes);

            return false;
        }

        Review::create(array_merge($attributes, [
            'organization_id' => $organizationId,
            'yandex_review_id' => $dto->yandexReviewId,
        ]));

        return true;
    }
}
