<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Contracts\CircuitBreakerInterface;
use App\Domain\Contracts\ProxyCheckerInterface;
use App\Domain\Contracts\ProxyRotatorInterface;
use App\Domain\Contracts\YandexParserInterface;
use App\Infrastructure\Services\CacheCircuitBreaker;
use App\Infrastructure\Services\DatabaseProxyRotator;
use App\Infrastructure\Services\HttpProxyChecker;
use App\Infrastructure\Services\YandexMapsParserService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            CircuitBreakerInterface::class,
            CacheCircuitBreaker::class
        );

        $this->app->singleton(
            ProxyRotatorInterface::class,
            DatabaseProxyRotator::class
        );

        $this->app->singleton(
            YandexParserInterface::class,
            YandexMapsParserService::class
        );

        $this->app->singleton(
            ProxyCheckerInterface::class,
            HttpProxyChecker::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Автоматическое создание файла SQLite базы данных, если он отсутствует (CI, composer install, package:discover)
        if (config('database.default') === 'sqlite') {
            $dbPath = (string) config('database.connections.sqlite.database');
            if ($dbPath !== '' && $dbPath !== ':memory:' && ! file_exists($dbPath)) {
                $dir = dirname($dbPath);
                if (! is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                @touch($dbPath);
            }
        }

        // Регистрация корректной UTF-8 функции lower() для SQLite базы данных (кириллический поиск)
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if ($event->connection instanceof SQLiteConnection) {
                try {
                    $event->connection->getPdo()->sqliteCreateFunction(
                        'lower',
                        fn (?string $s): ?string => $s !== null ? mb_strtolower($s, 'UTF-8') : null,
                        1
                    );
                } catch (\Throwable) {
                    // База данных еще не инициализирована или идет процесс package:discover
                }
            }
        });
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $identifier = $user !== null ? (string) $user->getAuthIdentifier() : (string) $request->ip();

            return Limit::perMinute(120)->by($identifier);
        });

        RateLimiter::for('status', function (Request $request) {
            $user = $request->user();
            $identifier = $user !== null ? (string) $user->getAuthIdentifier() : (string) $request->ip();

            return Limit::perMinute(300)->by($identifier);
        });

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email', '');

            return Limit::perMinute(5)->by($email.$request->ip())->response(function () {
                return response()->json([
                    'message' => 'Слишком много попыток входа. Пожалуйста, повторите попытку через минуту.',
                ], 429);
            });
        });

        RateLimiter::for('sync-organizations', function (Request $request) {
            $user = $request->user();
            $identifier = $user !== null ? (string) $user->getAuthIdentifier() : (string) $request->ip();

            return Limit::perMinute(5)->by($identifier)->response(function () {
                return response()->json([
                    'message' => 'Слишком много запросов на синхронизацию карточек. Пожалуйста, подождите минуту перед следующей попыткой.',
                ], 429);
            });
        });
    }
}
