<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Contracts\ProxyRotatorInterface;
use App\Models\ProxyServer;
use Illuminate\Console\Command;

class ProxyCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proxy:check
                            {--timeout=6 : Таймаут проверки в секундах}
                            {--all : Проверять все прокси, включая отключенные}
                            {--delete-dead : Автоматически удалить неработающие прокси после проверки}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Выполнить параллельную проверку доступности пула прокси-серверов и обновить их метрики';

    /**
     * Execute the console command.
     */
    public function handle(ProxyRotatorInterface $rotator): int
    {
        $timeout = (int) $this->option('timeout');
        if ($timeout <= 0) {
            $timeout = (int) config('services.proxy.check_timeout', 6);
        }

        $all = (bool) $this->option('all');
        $deleteDead = (bool) $this->option('delete-dead');
        $onlyActive = ! $all;

        $targetCount = $onlyActive
            ? ProxyServer::where('is_active', true)->count()
            : ProxyServer::count();

        if ($targetCount === 0) {
            $this->warn('В базе данных нет прокси для проверки.');

            return Command::SUCCESS;
        }

        $scopeText = $onlyActive ? 'активных' : 'всех';
        $this->info("Запуск параллельной проверки {$targetCount} {$scopeText} прокси (таймаут {$timeout}с)...");

        $stats = $rotator->checkPool($timeout, $onlyActive);

        $this->newLine();
        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Всего проверено', $stats['total']],
                ['Доступно (активно)', "✅ {$stats['active']}"],
                ['Отключено (ошибки/таймаут)', "❌ {$stats['disabled']}"],
                ['В карантине (капча)', "⏳ {$stats['captcha']}"],
                ['Общее время проверки', "{$stats['duration_ms']} ms"],
            ]
        );

        if ($deleteDead) {
            $deletedCount = ProxyServer::where('is_active', false)->delete();
            $this->warn("Удалено неработающих (отключенных) прокси: {$deletedCount}");
        }

        $this->info('Проверка пула прокси завершена.');

        return Command::SUCCESS;
    }
}
