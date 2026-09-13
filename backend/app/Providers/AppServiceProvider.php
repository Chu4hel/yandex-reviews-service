<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Contracts\ProxyRotatorInterface;
use App\Domain\Contracts\YandexParserInterface;
use App\Infrastructure\Services\DatabaseProxyRotator;
use App\Infrastructure\Services\YandexMapsParserService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
            ProxyRotatorInterface::class,
            DatabaseProxyRotator::class
        );

        $this->app->singleton(
            YandexParserInterface::class,
            YandexMapsParserService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $identifier = $user !== null ? (string) $user->getAuthIdentifier() : (string) $request->ip();

            return Limit::perMinute(60)->by($identifier);
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
