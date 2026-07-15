<?php

namespace App\Providers;

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
        // In tests, tenant tables live in the single test database, so make
        // migrate:fresh (RefreshDatabase) pick up tenant migrations as well.
        // Duplicated base migrations are skipped (same filenames).
        if ($this->app->runningUnitTests()) {
            $this->loadMigrationsFrom(database_path('migrations/tenant'));
        }
    }
}
