<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Tenancy\TenantController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('/tenant/select', [TenantController::class, 'select'])->name('tenant.select');
    Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');

    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/onboarding', [TenantController::class, 'create'])->name('tenants.create');

    Route::middleware(['auth'])->prefix('platform')->name('platform.')->group(function () {
        Route::get('tenants', [\App\Modules\Platform\Http\Controllers\PlatformTenantController::class, 'index'])->name('tenants.index');
        Route::get('tenants/{tenant}', [\App\Modules\Platform\Http\Controllers\PlatformTenantController::class, 'show'])->name('tenants.show');
        Route::patch('tenants/{tenant}/status', [\App\Modules\Platform\Http\Controllers\PlatformTenantController::class, 'updateStatus'])->name('tenants.status.update');

        Route::get('users', [\App\Modules\Platform\Http\Controllers\PlatformUserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [\App\Modules\Platform\Http\Controllers\PlatformUserController::class, 'show'])->name('users.show');
        Route::patch('memberships/{membership}/status', [\App\Modules\Platform\Http\Controllers\PlatformUserController::class, 'updateMembershipStatus'])->name('memberships.status.update');
    });

    Route::middleware(['auth', \App\Modules\Tenancy\Middleware\ResolveTenant::class])->group(function () {
        Route::resource('secretarias', \App\Modules\Secretaria\Http\Controllers\SecretariaController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);

        Route::resource('memberships', \App\Modules\Tenancy\Http\Controllers\MembershipController::class)
            ->only(['index', 'edit']);

        Route::post('memberships/{membership}/roles', [\App\Modules\Tenancy\Http\Controllers\MembershipRoleController::class, 'store'])
            ->name('memberships.roles.store');

        Route::delete('memberships/{membership}/roles/{role}', [\App\Modules\Tenancy\Http\Controllers\MembershipRoleController::class, 'destroy'])
            ->name('memberships.roles.destroy')->scopeBindings();
        Route::resource('roles', \App\Modules\Tenancy\Http\Controllers\RoleController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy']);

        Route::get('roles/{role}/permissions', [\App\Modules\Tenancy\Http\Controllers\RolePermissionController::class, 'index'])
            ->name('roles.permissions.index');

        Route::post('roles/{role}/permissions', [\App\Modules\Tenancy\Http\Controllers\RolePermissionController::class, 'store'])
            ->name('roles.permissions.store');

        Route::delete('roles/{role}/permissions/{permission}', [\App\Modules\Tenancy\Http\Controllers\RolePermissionController::class, 'destroy'])
            ->name('roles.permissions.destroy');
    });
});
