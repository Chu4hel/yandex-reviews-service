<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProxyServer;
use Illuminate\Console\Command;

class ProxyListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proxy:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Показать текущее состояние пула прокси-серверов и статистику ротации';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $proxies = ProxyServer::orderBy('id', 'asc')->get();

        if ($proxies->isEmpty()) {
            $this->warn('Пул прокси пуст. Запросы выполняются напрямую без прокси.');
            $this->line('Добавить прокси можно командой: php artisan proxy:add http://user:pass@host:port');

            return Command::SUCCESS;
        }

        $rows = $proxies->map(function (ProxyServer $proxy) {
            $status = '✅ Активен';
            if (! $proxy->is_active) {
                $status = '❌ Отключен';
            } elseif ($proxy->isCoolingDown()) {
                $status = '⏳ Карантин до '.$proxy->cooldown_until?->format('H:i:s d.m');
            }

            $endpoint = "{$proxy->protocol}://{$proxy->host}:{$proxy->port}";
            if ($proxy->username) {
                $endpoint = "{$proxy->protocol}://{$proxy->username}:***@{$proxy->host}:{$proxy->port}";
            }

            return [
                'id' => $proxy->id,
                'endpoint' => $endpoint,
                'status' => $status,
                'success' => $proxy->success_count,
                'fails' => $proxy->fails_count,
                'avg_ms' => $proxy->avg_response_time_ms ? "{$proxy->avg_response_time_ms} ms" : '—',
                'last_used' => $proxy->last_used_at ? $proxy->last_used_at->diffForHumans() : 'Никогда',
                'last_error' => $proxy->last_error ? mb_substr($proxy->last_error, 0, 30).'...' : '—',
            ];
        });

        $this->table(
            ['ID', 'Адрес прокси', 'Статус', 'Успехов', 'Ошибок', 'Задержка', 'Исп-н', 'Посл. ошибка'],
            $rows->toArray()
        );

        return Command::SUCCESS;
    }
}
