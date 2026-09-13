<?php

namespace App\Http\Controllers;

use App\Domain\Exceptions\YandexCaptchaDetectedException;
use App\Domain\Exceptions\YandexMarkupChangedException;
use App\Domain\Exceptions\YandexOrganizationNotFoundException;
use App\Domain\Exceptions\YandexParserException;
use App\Http\Requests\ConnectOrganizationRequest;
use App\Jobs\SyncOrganizationReviewsJob;
use App\Models\Organization;
use App\Services\OrganizationSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrganizationController extends Controller
{
    public function __construct(
        protected OrganizationSyncService $syncService
    ) {
    }

    /**
     * List all connected organizations.
     */
    public function index(): JsonResponse
    {
        $organizations = Organization::orderByDesc('created_at')->get();

        return response()->json([
            'data' => $organizations,
        ]);
    }

    /**
     * Connect a new organization by Yandex Maps URL or ID.
     */
    public function store(ConnectOrganizationRequest $request): JsonResponse
    {
        $url = $request->input('url');

        try {
            $organization = $this->syncService->connectOrganization($url);

            return response()->json([
                'message' => 'Организация успешно подключена',
                'data' => $organization,
            ], 201);
        } catch (YandexCaptchaDetectedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_type' => 'captcha_detected',
            ], 429);
        } catch (YandexOrganizationNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_type' => 'organization_not_found',
            ], 404);
        } catch (YandexMarkupChangedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_type' => 'markup_changed',
            ], 502);
        } catch (YandexParserException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_type' => 'parser_error',
            ], 422);
        } catch (\Throwable $e) {
            Log::error('OrganizationController::store unexpected error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Внутренняя ошибка сервера при подключении организации: ' . $e->getMessage(),
                'error_type' => 'server_error',
            ], 500);
        }
    }

    /**
     * Get organization details.
     */
    public function show(Organization $organization): JsonResponse
    {
        return response()->json([
            'data' => $organization,
        ]);
    }

    /**
     * Get real-time sync status and progress.
     */
    public function status(Organization $organization): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $organization->id,
                'sync_status' => $organization->sync_status,
                'sync_progress' => $organization->sync_progress,
                'last_synced_at' => $organization->last_synced_at,
                'last_sync_error' => $organization->last_sync_error,
                'rating' => $organization->rating,
                'ratings_count' => $organization->ratings_count,
                'reviews_count' => $organization->reviews_count,
            ],
        ]);
    }

    /**
     * Trigger re-synchronization of organization reviews.
     */
    public function sync(Organization $organization, Request $request): JsonResponse
    {
        if ($organization->sync_status === 'syncing') {
            return response()->json([
                'message' => 'Синхронизация уже выполняется.',
                'data' => $organization,
            ], 409);
        }

        // Check if sync execution is requested immediately
        $syncNow = $request->boolean('sync_now', false);

        if ($syncNow) {
            try {
                $result = $this->syncService->syncOrganizationReviews($organization);

                return response()->json([
                    'message' => 'Синхронизация успешно завершена.',
                    'data' => $organization->fresh(),
                    'result' => $result,
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'Ошибка при синхронизации: ' . $e->getMessage(),
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        // Otherwise dispatch background job
        $organization->update([
            'sync_status' => 'pending',
            'sync_progress' => 0,
            'last_sync_error' => null,
        ]);

        SyncOrganizationReviewsJob::dispatch($organization->id);

        return response()->json([
            'message' => 'Синхронизация запущена в фоновом режиме.',
            'data' => $organization->fresh(),
        ]);
    }

    /**
     * Get paginated reviews for organization (50 per page).
     */
    public function reviews(Organization $organization, Request $request): JsonResponse
    {
        $query = $organization->reviews();

        // Optional filter by rating
        if ($request->has('rating') && is_numeric($request->input('rating'))) {
            $rating = (int) $request->input('rating');
            if ($rating >= 1 && $rating <= 5) {
                $query->where('rating', $rating);
            }
        }

        // Sorting
        $sort = $request->input('sort', 'date_desc');
        match ($sort) {
            'date_asc' => $query->reorder('published_at', 'asc'),
            'rating_desc' => $query->reorder('rating', 'desc')->orderBy('published_at', 'desc'),
            'rating_asc' => $query->reorder('rating', 'asc')->orderBy('published_at', 'desc'),
            default => $query->reorder('published_at', 'desc'),
        };

        // Always 50 reviews per page as required by technical task
        $perPage = 50;
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'rating' => $organization->rating,
                'ratings_count' => $organization->ratings_count,
                'reviews_count' => $organization->reviews_count,
                'last_synced_at' => $organization->last_synced_at,
            ],
        ]);
    }

    /**
     * Get history of snapshots (changes between syncs).
     */
    public function snapshots(Organization $organization): JsonResponse
    {
        $snapshots = $organization->snapshots()->get();

        return response()->json([
            'data' => $snapshots,
        ]);
    }
}
