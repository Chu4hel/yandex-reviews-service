<?php

namespace App\Services;

use App\Domain\Contracts\YandexParserInterface;
use App\Domain\DTO\ParsedReviewDto;
use App\Domain\DTO\SyncResultDto;
use App\Domain\Exceptions\YandexParserException;
use App\Jobs\SyncOrganizationReviewsJob;
use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrganizationSyncService
{
    public function __construct(
        protected YandexParserInterface $parser
    ) {
    }

    /**
     * Connect an organization by Yandex Maps URL or ID.
     *
     * @param string $inputUrl
     * @return Organization
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
                'name' => $parsedOrg->name,
                'url' => $normalizedUrl,
                'address' => $parsedOrg->address,
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
     * @param Organization $organization
     * @param int $maxPages Maximum pages to fetch (12 pages * 50 = 600 reviews)
     * @return SyncResultDto
     * @throws YandexParserException
     */
    public function syncOrganizationReviews(Organization $organization, int $maxPages = 12): SyncResultDto
    {
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
                'name' => $parsedOrg->name,
                'address' => $parsedOrg->address,
                'rating' => $parsedOrg->rating,
                'ratings_count' => $parsedOrg->ratingsCount,
                'reviews_count' => $parsedOrg->reviewsCount,
            ]);

            $totalPagesToScan = min($parsedOrg->totalPages, $maxPages);
            if ($totalPagesToScan < 1) {
                $totalPagesToScan = 1;
            }

            // 2. Fetch pages
            for ($page = 1; $page <= $totalPagesToScan; $page++) {
                $batch = $this->parser->parseReviewsPage($organization->url, $page);

                foreach ($batch->reviews as $reviewDto) {
                    $isNew = $this->upsertReview($organization->id, $reviewDto);
                    if ($isNew) {
                        $newAddedCount++;
                    } else {
                        $updatedCount++;
                    }
                }

                $progress = (int) round(($page / $totalPagesToScan) * 100);
                $organization->update(['sync_progress' => min(99, $progress)]);

                if (!$batch->hasNextPage) {
                    break;
                }

                // Respectful pause between requests (300ms) to avoid rate limits
                usleep(300000);
            }

            // 3. Create snapshot of changes
            OrganizationSnapshot::create([
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

            return new SyncResultDto(
                organizationId: $organization->id,
                totalReviewsSaved: Review::where('organization_id', $organization->id)->count(),
                newReviewsAdded: $newAddedCount,
                updatedReviewsCount: $updatedCount,
                ratingBefore: $ratingBefore,
                ratingAfter: $organization->rating,
                reviewsCountBefore: $reviewsCountBefore,
                reviewsCountAfter: $organization->reviews_count,
                status: 'completed',
            );
        } catch (\Throwable $e) {
            Log::error('OrganizationSyncService: sync failed', [
                'organization_id' => $organization->id,
                'error' => $e->getMessage(),
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
     * @param int $organizationId
     * @param ParsedReviewDto $dto
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
            'author_name' => $dto->authorName,
            'author_avatar_url' => $dto->authorAvatarUrl,
            'author_level' => $dto->authorLevel,
            'rating' => $dto->rating,
            'text' => $dto->text,
            'published_at' => $publishedAt,
            'business_response_text' => $dto->businessResponseText,
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
