<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Register ActivityLog observer to add context
        \App\Models\ActivityLog::observe(\App\Observers\ActivityLogObserver::class);

        // The app has no Tailwind: Laravel's default pagination view renders unsized SVG arrows
        // that fill the page (seen on /clients, 2026-11-11). Use our own plain view everywhere.
        \Illuminate\Pagination\Paginator::defaultView('vendor.pagination.app');
        \Illuminate\Pagination\Paginator::defaultSimpleView('vendor.pagination.app');
    }
}
