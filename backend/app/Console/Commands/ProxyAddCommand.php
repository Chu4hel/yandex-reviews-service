<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Contracts\ProxyCheckerInterface;
use App\Models\ProxyServer;
use App\Support\ProxyStringParser;
use Illuminate\Console\Command;

class ProxyAddCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proxy:add {proxy : Строка прокси (например ip:port, http://user:pass@ip:port, ip:port:user:pass, ip:port@user:pass)} {--skip-ping : Пропустить тестовый пинг-запрос}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Добавить прокси-сервер в динамический пул ротации с тестовым пингом';

    /**
     * Execute the console command.
     */
    public function handle(ProxyCheckerInterface $proxyChecker): int
    {
        $input = trim((string) $this->argument('proxy'));

        $parsed = ProxyStringParser::parse($input);
        if (! $parsed) {
            $this->error('Неверный формат строки прокси. Поддерживаются форматы: host:port, host:port:user:password, host:port@user:password, [protocol://]user:password@host:port');

            return Command::FAILURE;
        }

        $proxyKey = ProxyServer::generateKey(
            $parsed['protocol'],
            $parsed['host'],
            $parsed['port'],
            $parsed['username'],
            $parsed['password']
        );

        $isActive = true;
        $cooldownUntil = null;
        $failsCount = 0;
        $lastError = null;
        $avgResponseTimeMs = null;

        if (! $this->option('skip-ping')) {
            $this->line("Выполняется тестовый пинг через {$parsed['protocol']}://{$parsed['host']}:{$parsed['port']}...");
            $ping = $proxyChecker->ping($parsed);

            if ($ping->isSuccess) {
                $isActive = true;
                $avgResponseTimeMs = $ping->responseTimeMs;
                $this->info("✓ Тестовый пинг успешен! Время отклика: {$ping->responseTimeMs} мс");
            } elseif ($ping->isCaptcha) {
                $isActive = true;
                $cooldownUntil = now()->addMinutes(30);
                $failsCount = 1;
                $lastError = 'Карантин: обнаружена капча при тестовом пинге';
                $avgResponseTimeMs = $ping->responseTimeMs;
                $this->warn('! Обнаружена капча (SmartCaptcha). Прокси помещен в карантин на 30 минут.');
            } else {
                $isActive = false;
                $failsCount = 1;
                $lastError = 'Тестовый пинг не удался: '.mb_substr((string) $ping->errorMessage, 0, 450);
                $this->warn("✗ Тестовый пинг не удался: {$ping->errorMessage}. Сервер добавлен в отключенном состоянии.");
            }
        }

        $proxy = ProxyServer::updateOrCreate(
            ['proxy_key' => $proxyKey],
            [
                'protocol' => $parsed['protocol'],
                'host' => $parsed['host'],
                'port' => $parsed['port'],
                'username' => $parsed['username'],
                'password' => $parsed['password'],
                'is_active' => $isActive,
                'cooldown_until' => $cooldownUntil,
                'fails_count' => $failsCount,
                'last_error' => $lastError,
                'avg_response_time_ms' => $avgResponseTimeMs,
            ]
        );

        $authInfo = $parsed['username'] ? " с аутентификацией [{$parsed['username']}]" : '';
        $stateInfo = $proxy->is_active ? ($proxy->isCoolingDown() ? 'на охлаждении' : 'активен') : 'отключен';
        $this->info("Прокси-сервер [#{$proxy->id}] {$parsed['protocol']}://{$parsed['host']}:{$parsed['port']}{$authInfo} сохранен в пуле (статус: {$stateInfo}).");

        return Command::SUCCESS;
    }
}
