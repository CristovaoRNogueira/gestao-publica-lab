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

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

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
