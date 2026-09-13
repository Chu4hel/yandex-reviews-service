<?php

declare(strict_types=1);

namespace App\Console\Commands;

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
    protected $signature = 'proxy:add {proxy : Строка прокси (например ip:port, http://user:pass@ip:port, ip:port:user:pass, ip:port@user:pass)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Добавить прокси-сервер в динамический пул ротации';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $input = trim((string) $this->argument('proxy'));

        $parsed = ProxyStringParser::parse($input);
        if (! $parsed) {
            $this->error('Неверный формат строки прокси. Поддерживаются форматы: host:port, host:port:user:password, host:port@user:password, [protocol://]user:password@host:port');

            return Command::FAILURE;
        }

        $proxy = ProxyServer::updateOrCreate(
            ['host' => $parsed['host'], 'port' => $parsed['port']],
            [
                'protocol' => $parsed['protocol'],
                'username' => $parsed['username'],
                'password' => $parsed['password'],
                'is_active' => true,
                'cooldown_until' => null,
                'fails_count' => 0,
            ]
        );

        $authInfo = $parsed['username'] ? " с аутентификацией [{$parsed['username']}]" : '';
        $this->info("Прокси-сервер [#{$proxy->id}] {$parsed['protocol']}://{$parsed['host']}:{$parsed['port']}{$authInfo} успешно добавлен и активирован в пуле.");

        return Command::SUCCESS;
    }
}
