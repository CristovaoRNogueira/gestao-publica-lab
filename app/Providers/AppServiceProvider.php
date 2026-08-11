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
        $this->app->scoped(\App\Modules\Tenancy\Context\TenantContext::class, function () {
            return new \App\Modules\Tenancy\Context\TenantContext();
        });

        $this->app->singleton(\App\Modules\Tenancy\Services\TenantResolver::class, function () {
            return new \App\Modules\Tenancy\Services\TenantResolver();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
