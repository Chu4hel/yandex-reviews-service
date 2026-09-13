<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProxyServer;
use Illuminate\Console\Command;

class ProxyAddCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proxy:add {proxy : URL прокси (например http://user:pass@1.2.3.4:8080 или 1.2.3.4:8080)}';

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

        // If scheme is missing, prepend http://
        if (! str_contains($input, '://')) {
            $input = 'http://'.$input;
        }

        $parsed = parse_url($input);
        if ($parsed === false || empty($parsed['host']) || empty($parsed['port'])) {
            $this->error('Неверный формат строки прокси. Ожидается: [protocol://][user:password@]host:port');

            return Command::FAILURE;
        }

        $protocol = strtolower((string) ($parsed['scheme'] ?? 'http'));
        $host = (string) $parsed['host'];
        $port = (int) $parsed['port'];
        $username = isset($parsed['user']) ? (string) $parsed['user'] : null;
        $password = isset($parsed['pass']) ? (string) $parsed['pass'] : null;

        $proxy = ProxyServer::updateOrCreate(
            ['host' => $host, 'port' => $port],
            [
                'protocol' => $protocol,
                'username' => $username,
                'password' => $password,
                'is_active' => true,
                'cooldown_until' => null,
                'fails_count' => 0,
            ]
        );

        $this->info("Прокси-сервер [#{$proxy->id}] {$protocol}://{$host}:{$port} успешно добавлен и активирован в пуле.");

        return Command::SUCCESS;
    }
}
