<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneSnapshotsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prunes_snapshots_older_than_specified_days(): void
    {
        $org = Organization::create([
            'name' => 'Тестовое кафе',
            'url' => 'https://yandex.ru/maps/org/test/12345/reviews/',
            'yandex_org_id' => '12345',
            'sync_status' => 'synced',
        ]);

        // Old snapshot (100 days ago)
        OrganizationSnapshot::create([
            'organization_id' => $org->id,
            'rating_before' => 4.5,
            'rating_after' => 4.6,
            'snapshot_at' => now()->subDays(100),
        ]);

        // Recent snapshot (10 days ago)
        OrganizationSnapshot::create([
            'organization_id' => $org->id,
            'rating_before' => 4.6,
            'rating_after' => 4.7,
            'snapshot_at' => now()->subDays(10),
        ]);

        $this->assertEquals(2, OrganizationSnapshot::count());

        // Run command with --days=30
        $this->artisan('reviews:prune-snapshots --days=30')
            ->expectsOutputToContain('Успешно удалено устаревших снимков: 1')
            ->assertSuccessful();

        $this->assertEquals(1, OrganizationSnapshot::count());
        $remaining = OrganizationSnapshot::first();
        $this->assertNotNull($remaining);
        $this->assertEquals(4.7, $remaining->rating_after);
    }

    public function test_dry_run_does_not_delete_records(): void
    {
        $org = Organization::create([
            'name' => 'Тестовое кафе',
            'url' => 'https://yandex.ru/maps/org/test/12345/reviews/',
            'yandex_org_id' => '12345',
            'sync_status' => 'synced',
        ]);

        OrganizationSnapshot::create([
            'organization_id' => $org->id,
            'rating_before' => 4.5,
            'rating_after' => 4.6,
            'snapshot_at' => now()->subDays(120),
        ]);

        $this->artisan('reviews:prune-snapshots --days=60 --dry-run')
            ->expectsOutputToContain('[DRY-RUN] Найдено устаревших снимков для очистки: 1')
            ->assertSuccessful();

        $this->assertEquals(1, OrganizationSnapshot::count());
    }

    public function test_fails_on_invalid_days(): void
    {
        $this->artisan('reviews:prune-snapshots --days=-5')
            ->expectsOutputToContain('Количество дней должно быть положительным числом.')
            ->assertFailed();
    }
}
