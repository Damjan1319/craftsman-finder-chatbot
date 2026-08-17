<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        if (config('cache.default') === 'file') {
            $cachePath = env('APP_CACHE_PATH', '/tmp/cache/data');

            if (! is_dir($cachePath)) {
                mkdir($cachePath, 0755, true);
            }

            config([
                'cache.stores.file.path' => $cachePath,
                'cache.stores.file.lock_path' => $cachePath,
            ]);
        }
    }
}
