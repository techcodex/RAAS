<?php

namespace App\Providers;

use App\Services\RagClient;
use App\Support\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(RagClient::class, fn () => RagClient::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Login: per account + IP, so one shared network can't lock everyone out
        // and a targeted account can't be brute-forced.
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return [Limit::perMinute(5)->by($key), Limit::perMinute(20)->by($request->ip())];
        });

        // Registration: IP-based but generous enough for shared networks.
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(15)->by($request->ip()));
    }
}
