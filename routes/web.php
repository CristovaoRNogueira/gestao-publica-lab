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

    Route::get('/register', [\App\Http\Controllers\Auth\RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [\App\Http\Controllers\Auth\RegisterController::class, 'register']);

    Route::get('/forgot-password', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

    Route::get('/reset-password/{token}', [\App\Http\Controllers\Auth\ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [\App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])->name('password.update');
});

// Public global route (no auth required to view)
Route::get('invites/{token}', [\App\Modules\Tenancy\Http\Controllers\AcceptInvitationController::class, 'show'])->name('invites.show');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/pending-approval', \App\Http\Controllers\PendingApprovalController::class)->name('pending-approval');
    Route::get('onboarding', [TenantController::class, 'create'])->name('tenants.create');
    Route::post('/tenant/select', [TenantController::class, 'select'])->name('tenant.select');
    Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');

    // Public global routes (but require auth)
    Route::post('invites/{token}', [\App\Modules\Tenancy\Http\Controllers\AcceptInvitationController::class, 'accept'])->name('invites.accept');

    Route::middleware(['auth'])->prefix('platform')->name('platform.')->group(function () {
        Route::get('tenants', [\App\Modules\Platform\Http\Controllers\PlatformTenantController::class, 'index'])->name('tenants.index');
        Route::get('tenants/{tenant}', [\App\Modules\Platform\Http\Controllers\PlatformTenantController::class, 'show'])->name('tenants.show');
        Route::patch('tenants/{tenant}/status', [\App\Modules\Platform\Http\Controllers\PlatformTenantController::class, 'updateStatus'])->name('tenants.status.update');

        Route::get('users', [\App\Modules\Platform\Http\Controllers\PlatformUserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [\App\Modules\Platform\Http\Controllers\PlatformUserController::class, 'show'])->name('users.show');
        Route::patch('memberships/{membership}/status', [\App\Modules\Platform\Http\Controllers\PlatformUserController::class, 'updateMembershipStatus'])->name('memberships.status.update');
    });

    // Tenant-scoped routes
    Route::middleware(['auth', \App\Modules\Tenancy\Middleware\ResolveTenant::class])->group(function () {        // Invitations
        Route::get('invitations', [\App\Modules\Tenancy\Http\Controllers\InvitationController::class, 'index'])->name('invitations.index');
        Route::get('invitations/create', [\App\Modules\Tenancy\Http\Controllers\InvitationController::class, 'create'])->name('invitations.create');
        Route::post('invitations', [\App\Modules\Tenancy\Http\Controllers\InvitationController::class, 'store'])->name('invitations.store');
        Route::post('invitations/{invitation}/resend', [\App\Modules\Tenancy\Http\Controllers\InvitationController::class, 'resend'])->name('invitations.resend');
        Route::patch('invitations/{invitation}/revoke', [\App\Modules\Tenancy\Http\Controllers\InvitationController::class, 'revoke'])->name('invitations.revoke');
    });

    Route::middleware(['auth', \App\Modules\Tenancy\Middleware\ResolveTenant::class])->group(function () {
        Route::resource('secretarias', \App\Modules\Secretaria\Http\Controllers\SecretariaController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);

        Route::resource('memberships', \App\Modules\Tenancy\Http\Controllers\MembershipController::class)
            ->only(['index', 'edit']);

        Route::patch('memberships/{membership}/activate', [\App\Modules\Tenancy\Http\Controllers\MembershipController::class, 'activate'])
            ->name('memberships.activate');

        Route::patch('memberships/{membership}/deactivate', [\App\Modules\Tenancy\Http\Controllers\MembershipController::class, 'deactivate'])
            ->name('memberships.deactivate');

        Route::patch('memberships/{membership}/approve', [\App\Modules\Tenancy\Http\Controllers\MembershipController::class, 'approve'])
            ->name('memberships.approve');

        Route::patch('memberships/{membership}/reject', [\App\Modules\Tenancy\Http\Controllers\MembershipController::class, 'reject'])
            ->name('memberships.reject');

        Route::post('memberships/{membership}/roles', [\App\Modules\Tenancy\Http\Controllers\MembershipRoleController::class, 'store'])
            ->name('memberships.roles.store');

        Route::delete('memberships/{membership}/roles/{role}', [\App\Modules\Tenancy\Http\Controllers\MembershipRoleController::class, 'destroy'])
            ->name('memberships.roles.destroy')->scopeBindings();
        Route::resource('roles', \App\Modules\Tenancy\Http\Controllers\RoleController::class)
            ->only(['index', 'create', 'show', 'edit', 'store', 'update', 'destroy']);

        Route::get('roles/{role}/permissions', [\App\Modules\Tenancy\Http\Controllers\RolePermissionController::class, 'index'])
            ->name('roles.permissions.index');

        Route::post('roles/{role}/permissions', [\App\Modules\Tenancy\Http\Controllers\RolePermissionController::class, 'store'])
            ->name('roles.permissions.store');

        Route::delete('roles/{role}/permissions/{permission}', [\App\Modules\Tenancy\Http\Controllers\RolePermissionController::class, 'destroy'])
            ->name('roles.permissions.destroy');
    });
});
