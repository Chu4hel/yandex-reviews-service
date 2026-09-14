<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Exceptions\YandexCaptchaDetectedException;
use App\Domain\Exceptions\YandexMarkupChangedException;
use App\Domain\Exceptions\YandexOrganizationNotFoundException;
use App\Domain\Exceptions\YandexParserException;
use App\Http\Requests\ConnectOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\OrganizationSnapshotResource;
use App\Http\Resources\ReviewResource;
use App\Jobs\SyncOrganizationReviewsJob;
use App\Models\Organization;
use App\Services\OrganizationSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationController extends Controller
{
    public function __construct(
        protected OrganizationSyncService $syncService
    ) {}

    /**
     * Список всех подключенных организаций.
     */
    public function index(): JsonResponse
    {
        $organizations = Organization::orderByDesc('created_at')->get();

        return response()->json([
            'data' => OrganizationResource::collection($organizations),
        ]);
    }

    /**
     * Подключение новой организации по ссылке или ID Яндекс Карт.
     */
    public function store(ConnectOrganizationRequest $request): JsonResponse
    {
        $url = $request->input('url');

        try {
            $organization = $this->syncService->connectOrganization($url);

            return response()->json([
                'message' => 'Организация успешно подключена',
                'data' => new OrganizationResource($organization),
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
                'message' => 'Внутренняя ошибка сервера при подключении организации: '.$e->getMessage(),
                'error_type' => 'server_error',
            ], 500);
        }
    }

    /**
     * Детальная информация об организации.
     */
    public function show(Organization $organization): JsonResponse
    {
        return response()->json([
            'data' => new OrganizationResource($organization),
        ]);
    }

    /**
     * Текущий статус и прогресс фоновой синхронизации организации.
     */
    public function status(Organization $organization): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $organization->id,
                'sync_status' => $organization->sync_status,
                'sync_progress' => $organization->sync_progress,
                'sync_message' => $organization->sync_message,
                'db_reviews_count' => $organization->reviews()->count(),
                'last_synced_at' => $organization->last_synced_at?->toIso8601String(),
                'last_sync_error' => $organization->last_sync_error,
                'rating' => $organization->rating,
                'ratings_count' => $organization->ratings_count,
                'reviews_count' => $organization->reviews_count,
            ],
        ]);
    }

    /**
     * Запуск повторной синхронизации отзывов организации.
     */
    public function sync(Organization $organization, Request $request): JsonResponse
    {
        if ($organization->sync_status === 'syncing') {
            return response()->json([
                'message' => 'Синхронизация уже выполняется.',
                'data' => new OrganizationResource($organization),
            ], 409);
        }

        // Проверка запроса на немедленную синхронизацию
        $syncNow = $request->boolean('sync_now', false);

        if ($syncNow) {
            try {
                $result = $this->syncService->syncOrganizationReviews($organization);

                return response()->json([
                    'message' => 'Синхронизация успешно завершена.',
                    'data' => new OrganizationResource($organization->fresh()),
                    'result' => $result,
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'Ошибка при синхронизации: '.$e->getMessage(),
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        // Запуск синхронизации в фоновой очереди
        $organization->update([
            'sync_status' => 'pending',
            'sync_progress' => 0,
            'last_sync_error' => null,
        ]);

        SyncOrganizationReviewsJob::dispatch($organization->id);

        return response()->json([
            'message' => 'Синхронизация запущена в фоновом режиме.',
            'data' => new OrganizationResource($organization->fresh()),
        ]);
    }

    /**
     * Получить пагинированный список отзывов организации (по 50 на страницу).
     */
    public function reviews(Organization $organization, Request $request): JsonResponse
    {
        $query = $organization->reviews();

        // Фильтрация по оценке
        if ($request->has('rating') && is_numeric($request->input('rating'))) {
            $rating = (int) $request->input('rating');
            if ($rating >= 1 && $rating <= 5) {
                $query->where('rating', $rating);
            }
        }

        // Полнотекстовый поиск по отзывам, авторам и ответам компании
        $hasSearch = $request->filled('search');
        $searchTerm = $hasSearch ? trim((string) $request->input('search')) : '';

        if ($searchTerm !== '') {
            $query->search($searchTerm);
        }

        // Сортировка отзывов (с поддержкой ранжирования по релевантности)
        $sort = $request->input('sort', 'date_desc');
        match ($sort) {
            'relevance' => $searchTerm !== '' ? $query->reorder()->orderByRelevance($searchTerm) : $query->reorder('published_at', 'desc'),
            'date_asc' => $query->reorder('published_at', 'asc'),
            'rating_desc' => $query->reorder('rating', 'desc')->orderBy('published_at', 'desc'),
            'rating_asc' => $query->reorder('rating', 'asc')->orderBy('published_at', 'desc'),
            default => $query->reorder('published_at', 'desc'),
        };

        // По 50 отзывов на страницу согласно техническому заданию
        $perPage = 50;
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => ReviewResource::collection($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'organization' => new OrganizationResource($organization),
        ]);
    }

    /**
     * Получить историю снимков изменений (динамику репутации).
     */
    public function snapshots(Organization $organization): JsonResponse
    {
        $snapshots = $organization->snapshots()->get();

        return response()->json([
            'data' => OrganizationSnapshotResource::collection($snapshots),
        ]);
    }

    /**
     * Экспорт отзывов в CSV с UTF-8 BOM для совместимости с Microsoft Excel.
     */
    public function export(Organization $organization, Request $request): StreamedResponse
    {
        $query = $organization->reviews();

        if ($request->has('rating') && is_numeric($request->input('rating'))) {
            $rating = (int) $request->input('rating');
            if ($rating >= 1 && $rating <= 5) {
                $query->where('rating', $rating);
            }
        }

        if ($request->filled('search')) {
            $searchTerm = trim((string) $request->input('search'));
            if ($searchTerm !== '') {
                $query->search($searchTerm);
            }
        }

        $query->orderBy('published_at', 'desc');

        $slug = Str::slug($organization->name ?: 'organization');
        $format = strtolower((string) $request->input('format', 'csv'));

        if ($format === 'json') {
            $fileName = sprintf('reviews_%s_%s.json', $slug, now()->format('Y-m-d_His'));

            $headers = [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ];

            return response()->stream(function () use ($query) {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }

                fwrite($handle, "[\n");
                $isFirst = true;

                $query->chunk(200, function ($reviews) use ($handle, &$isFirst) {
                    foreach ($reviews as $review) {
                        if (! $isFirst) {
                            fwrite($handle, ",\n");
                        }
                        $isFirst = false;

                        $item = [
                            'id' => $review->id,
                            'yandex_review_id' => $review->yandex_review_id,
                            'author_name' => $review->author_name ?? 'Пользователь',
                            'author_avatar_url' => $review->author_avatar_url,
                            'author_level' => $review->author_level,
                            'rating' => $review->rating,
                            'text' => $review->text,
                            'published_at' => $review->published_at ? $review->published_at->toIso8601String() : null,
                            'business_response' => ! empty($review->business_response_text) ? [
                                'text' => $review->business_response_text,
                                'responded_at' => $review->business_response_at ? $review->business_response_at->toIso8601String() : null,
                            ] : null,
                        ];

                        $json = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        if ($json !== false) {
                            fwrite($handle, '  '.$json);
                        }
                    }
                });

                fwrite($handle, "\n]\n");
                fclose($handle);
            }, 200, $headers);
        }

        $fileName = sprintf('reviews_%s_%s.csv', $slug, now()->format('Y-m-d_His'));

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM для корректного отображения кириллицы в Excel
            fwrite($handle, "\xEF\xBB\xBF");

            // Заголовок таблицы CSV
            fputcsv($handle, [
                'ID отзыва Яндекса',
                'Автор',
                'Статус автора',
                'Оценка (звёзды)',
                'Дата публикации',
                'Текст отзыва',
                'Есть ответ компании',
                'Текст ответа компании',
                'Дата ответа компании',
            ], ';');

            // Потоковая выгрузка отзывов порциями для экономии памяти
            $query->chunk(200, function ($reviews) use ($handle) {
                foreach ($reviews as $review) {
                    fputcsv($handle, [
                        $review->yandex_review_id,
                        $review->author_name ?? 'Пользователь',
                        $review->author_level ?? '',
                        (string) $review->rating,
                        $review->published_at ? $review->published_at->format('Y-m-d H:i:s') : '',
                        str_replace(["\r\n", "\r"], "\n", (string) ($review->text ?? '')),
                        ! empty($review->business_response_text) ? 'Да' : 'Нет',
                        str_replace(["\r\n", "\r"], "\n", (string) ($review->business_response_text ?? '')),
                        $review->business_response_at ? $review->business_response_at->format('Y-m-d H:i:s') : '',
                    ], ';');
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Удалить организацию (мягкое удаление в архив).
     */
    public function destroy(Organization $organization): JsonResponse
    {
        $organization->delete();

        return response()->json([
            'message' => 'Организация успешно перемещена в архив',
        ]);
    }
}
