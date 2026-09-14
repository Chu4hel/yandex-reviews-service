<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use Illuminate\Console\Command;

class PruneSnapshotsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reviews:prune-snapshots
                            {--days=90 : Срок хранения снимков в днях (по умолчанию: 90)}
                            {--org= : Идентификатор конкретной организации}
                            {--dry-run : Выполнить анализ без фактического удаления записей}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Автоматическая ротация и очистка устаревших снимков репутации организаций';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $this->error('Количество дней должно быть положительным числом.');

            return self::FAILURE;
        }

        $orgId = $this->option('org');
        $isDryRun = (bool) $this->option('dry-run');

        $cutoffDate = now()->subDays($days);

        $query = OrganizationSnapshot::query()->where('snapshot_at', '<=', $cutoffDate);

        if ($orgId !== null && $orgId !== '') {
            $orgNumericId = (int) $orgId;
            $org = Organization::find($orgNumericId);
            if (! $org) {
                $this->error("Организация с ID {$orgNumericId} не найдена в базе данных.");

                return self::FAILURE;
            }
            $query->where('organization_id', $orgNumericId);
            $this->info("Фильтр по организации: [{$org->id}] {$org->name}");
        }

        $count = $query->count();

        $this->line("Дата отсечения: {$cutoffDate->format('Y-m-d H:i:s')} (старше {$days} дн.)");

        if ($count === 0) {
            $this->info('Нет устаревших снимков, удовлетворяющих заданным критериям.');

            return self::SUCCESS;
        }

        if ($isDryRun) {
            $this->warn("[DRY-RUN] Найдено устаревших снимков для очистки: {$count}. Записи НЕ были удалены.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Успешно удалено устаревших снимков: {$deleted}.");

        return self::SUCCESS;
    }
}
