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

        $this->app->scoped(\App\Modules\Platform\Context\PlatformContext::class, function () {
            return new \App\Modules\Platform\Context\PlatformContext();
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
        \Illuminate\Support\Facades\Gate::define('platform.tenants.view', function (\App\Models\User $user, ?\App\Modules\Tenancy\Models\Tenant $tenant = null) {
            $context = app(\App\Modules\Platform\Context\PlatformContext::class);
            $context->set($user);
            return $context->hasPermission(\App\Modules\Platform\Enums\PlatformPermissionSlug::TENANTS_VIEW->value);
        });

        \Illuminate\Support\Facades\Gate::define('platform.tenants.manage', function (\App\Models\User $user, ?\App\Modules\Tenancy\Models\Tenant $tenant = null) {
            $context = app(\App\Modules\Platform\Context\PlatformContext::class);
            $context->set($user);
            return $context->hasPermission(\App\Modules\Platform\Enums\PlatformPermissionSlug::TENANTS_MANAGE->value);
        });
    }
}
