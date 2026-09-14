<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Organization;
use App\Services\OrganizationSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncOrganizationReviewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $organizationId
    ) {}

    public function handle(OrganizationSyncService $syncService): void
    {
        $organization = Organization::find($this->organizationId);
        if (! $organization) {
            Log::warning('SyncOrganizationReviewsJob: Organization not found', ['id' => $this->organizationId]);

            return;
        }

        Log::info('SyncOrganizationReviewsJob: старт выполнения фоновой задачи', [
            'job_id' => $this->job ? $this->job->getJobId() : 'sync',
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
            'organization_id' => $organization->id,
            'name' => $organization->name,
        ]);

        $syncService->syncOrganizationReviews($organization);

        Log::info('SyncOrganizationReviewsJob: фоновая задача успешно завершена', [
            'job_id' => $this->job ? $this->job->getJobId() : 'sync',
            'attempt' => $this->attempts(),
            'organization_id' => $organization->id,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('SyncOrganizationReviewsJob: исчерпаны все попытки выполнения фоновой задачи', [
            'organization_id' => $this->organizationId,
            'max_tries' => $this->tries,
            'error_class' => get_class($exception),
            'error_message' => $exception->getMessage(),
        ]);

        $organization = Organization::find($this->organizationId);
        if ($organization) {
            $organization->update([
                'sync_status' => 'failed',
                'last_sync_error' => $exception->getMessage(),
            ]);
        }
    }
}
