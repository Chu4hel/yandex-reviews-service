<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use App\Models\Review;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DumpOrganizationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reviews:dump
                            {id : Идентификатор организации}
                            {--output= : Путь к целевому JSON-файлу}
                            {--limit-reviews= : Ограничить количество выгружаемых отзывов}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Выгрузить полный офлайн-слепок организации со всеми отзывами и снимками в JSON';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $org = Organization::find($id);

        if (! $org) {
            $this->error("Организация с ID {$id} не найдена.");

            return self::FAILURE;
        }

        $this->line("Подготовка дампа для организации: [{$org->id}] {$org->name}...");

        $reviewsQuery = $org->reviews()->orderByDesc('published_at');
        $limitReviews = $this->option('limit-reviews');
        if ($limitReviews !== null && (int) $limitReviews > 0) {
            $reviewsQuery->limit((int) $limitReviews);
        }

        $reviews = $reviewsQuery->get();
        $snapshots = $org->snapshots()->orderBy('snapshot_at')->get();

        $payload = [
            'dump_version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'organization' => [
                'id' => $org->id,
                'name' => $org->name,
                'address' => $org->address,
                'url' => $org->url,
                'yandex_org_id' => $org->yandex_org_id,
                'rating' => $org->rating,
                'ratings_count' => $org->ratings_count,
                'reviews_count' => $org->reviews_count,
                'sync_status' => $org->sync_status,
                'last_synced_at' => $org->last_synced_at?->toIso8601String(),
            ],
            'snapshots_count' => $snapshots->count(),
            'snapshots' => $snapshots->map(fn (OrganizationSnapshot $s): array => [
                'id' => $s->id,
                'rating_before' => $s->rating_before,
                'rating_after' => $s->rating_after,
                'ratings_count_before' => $s->ratings_count_before,
                'ratings_count_after' => $s->ratings_count_after,
                'reviews_count_before' => $s->reviews_count_before,
                'reviews_count_after' => $s->reviews_count_after,
                'new_reviews_added' => $s->new_reviews_added,
                'updated_reviews_count' => $s->updated_reviews_count,
                'snapshot_at' => $s->snapshot_at->toIso8601String(),
            ])->toArray(),
            'reviews_count' => $reviews->count(),
            'reviews' => $reviews->map(fn (Review $r): array => [
                'id' => $r->id,
                'yandex_review_id' => $r->yandex_review_id,
                'author_name' => $r->author_name,
                'author_avatar_url' => $r->author_avatar_url,
                'author_level' => $r->author_level,
                'rating' => $r->rating,
                'text' => $r->text,
                'business_response_text' => $r->business_response_text,
                'published_at' => $r->published_at?->toIso8601String(),
            ])->toArray(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->error('Ошибка сериализации данных организации в JSON.');

            return self::FAILURE;
        }

        $outputPath = $this->option('output');
        if ($outputPath === null || $outputPath === '') {
            $dir = storage_path('app/dumps');
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            $filename = sprintf('org_%d_%s.json', $org->id, now()->format('Ymd_His'));
            $outputPath = $dir.DIRECTORY_SEPARATOR.$filename;
        } else {
            $dir = dirname((string) $outputPath);
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }

        File::put((string) $outputPath, $json);

        $this->info("Дамп успешно создан: {$outputPath}");
        $this->line(sprintf('Выгружено: %d снимков, %d отзывов.', $snapshots->count(), $reviews->count()));

        return self::SUCCESS;
    }
}
