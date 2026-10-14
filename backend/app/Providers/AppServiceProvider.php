<?php

namespace App\Providers;

use RuntimeException;
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
        if (
            $this->app->environment('production')
            && in_array(config('analytics.server_api_key'), [null, '', 'local-development-key'], true)
        ) {
            throw new RuntimeException('ANALYTICS_SERVER_KEY must be set to a non-development value in production.');
        }
    }
}
