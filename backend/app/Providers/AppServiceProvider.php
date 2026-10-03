<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
        // In development, loading a relationship one row at a time (an "N+1" problem) throws an error,
        // so the mistake is caught immediately. Switched off in production so visitors never see it.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
