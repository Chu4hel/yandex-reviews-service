<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\Contracts\ProxyCheckerInterface;
use App\Domain\DTO\ProxyPingResult;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpProxyChecker implements ProxyCheckerInterface
{
    /**
     * Выполнить тестовый пинг-запрос через указанный прокси-сервер.
     *
     * @param array{protocol: string, host: string, port: int, username: ?string, password: ?string} $proxyConfig
     */
    public function ping(array $proxyConfig, int $timeoutSeconds = 5): ProxyPingResult
    {
        $checkUrl = (string) config('services.proxy.check_url', 'https://ya.ru');
        $proxyUrl = $this->buildProxyOption($proxyConfig);
        $startTime = microtime(true);

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->withOptions([
                    'proxy' => $proxyUrl,
                    'verify' => false,
                    'connect_timeout' => $timeoutSeconds,
                ])
                ->timeout($timeoutSeconds)
                ->get($checkUrl);

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $body = $response->body();

            if ($this->detectCaptcha($body)) {
                return ProxyPingResult::captcha($durationMs, $response->status());
            }

            if ($response->successful() || in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                return ProxyPingResult::success($durationMs, $response->status());
            }

            return ProxyPingResult::failure("HTTP статус {$response->status()}", $durationMs, $response->status());
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            return ProxyPingResult::failure($e->getMessage(), $durationMs);
        }
    }

    /**
     * Выполнить параллельный тестовый пинг-запрос для нескольких прокси-серверов.
     *
     * @param list<array{protocol: string, host: string, port: int, username: ?string, password: ?string}> $proxyConfigs
     * @return array<int, ProxyPingResult>
     */
    public function pingMany(array $proxyConfigs, int $timeoutSeconds = 5): array
    {
        if (empty($proxyConfigs)) {
            return [];
        }

        if (count($proxyConfigs) === 1) {
            return [0 => $this->ping($proxyConfigs[0], $timeoutSeconds)];
        }

        $checkUrl = (string) config('services.proxy.check_url', 'https://ya.ru');
        $startTime = microtime(true);

        try {
            /** @var array<int, Response|\Throwable> $responses */
            $responses = Http::pool(function (Pool $pool) use ($proxyConfigs, $checkUrl, $timeoutSeconds) {
                $calls = [];
                foreach ($proxyConfigs as $index => $cfg) {
                    $proxyUrl = $this->buildProxyOption($cfg);

                    $calls[$index] = $pool->withHeaders($this->getHeaders())
                        ->withOptions([
                            'proxy' => $proxyUrl,
                            'verify' => false,
                            'connect_timeout' => $timeoutSeconds,
                        ])
                        ->timeout($timeoutSeconds)
                        ->get($checkUrl);
                }

                return $calls;
            });

            $totalDurationMs = (int) round((microtime(true) - $startTime) * 1000);
            $results = [];

            foreach ($proxyConfigs as $index => $cfg) {
                $resp = $responses[$index] ?? null;

                if ($resp instanceof \Throwable) {
                    $results[$index] = ProxyPingResult::failure($resp->getMessage(), $totalDurationMs);
                    continue;
                }

                if ($resp instanceof Response) {
                    $body = $resp->body();
                    if ($this->detectCaptcha($body)) {
                        $results[$index] = ProxyPingResult::captcha($totalDurationMs, $resp->status());
                    } elseif ($resp->successful() || in_array($resp->status(), [301, 302, 303, 307, 308], true)) {
                        $results[$index] = ProxyPingResult::success($totalDurationMs, $resp->status());
                    } else {
                        $results[$index] = ProxyPingResult::failure("HTTP статус {$resp->status()}", $totalDurationMs, $resp->status());
                    }
                } else {
                    $results[$index] = ProxyPingResult::failure('Нет ответа от прокси', $totalDurationMs);
                }
            }

            return $results;
        } catch (\Throwable $e) {
            Log::warning('HttpProxyChecker: ошибка параллельного пула запросов, переключение на последовательный пинг', [
                'error' => $e->getMessage(),
            ]);

            $results = [];
            foreach ($proxyConfigs as $index => $cfg) {
                $results[$index] = $this->ping($cfg, $timeoutSeconds);
            }

            return $results;
        }
    }

    /**
     * @param array{protocol: string, host: string, port: int, username: ?string, password: ?string} $proxyConfig
     */
    private function buildProxyOption(array $proxyConfig): string
    {
        $protocol = strtolower(trim($proxyConfig['protocol'] ?: 'http'));
        $host = trim($proxyConfig['host']);
        $port = (int) $proxyConfig['port'];
        $username = $proxyConfig['username'] ?? null;
        $password = $proxyConfig['password'] ?? null;

        $auth = '';
        if ($username !== null && $username !== '') {
            $auth = $password !== null && $password !== ''
                ? "{$username}:{$password}@"
                : "{$username}@";
        }

        return "{$protocol}://{$auth}{$host}:{$port}";
    }

    /**
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8',
        ];
    }

    private function detectCaptcha(string $html): bool
    {
        return str_contains($html, 'captcha-page')
            || str_contains($html, 'smartcaptcha')
            || str_contains($html, 'showcaptcha');
    }
}
