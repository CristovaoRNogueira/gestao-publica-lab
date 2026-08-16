<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateTenantStatusRequest;
use App\Modules\Platform\Services\UpdateTenantStatusService;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformTenantController extends Controller
{
    public function index(Request $request): Response
    {
        \Illuminate\Support\Facades\Gate::authorize('platform.tenants.view');

        $tenants = Tenant::query()
            ->withCount(['memberships as active_members_count' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('name')
            ->get();

        return Inertia::render('Platform/Tenant/Index', [
            'tenants' => $tenants
        ]);
    }

    public function show(Request $request, Tenant $tenant): Response
    {
        \Illuminate\Support\Facades\Gate::authorize('platform.tenants.view', $tenant);

        $tenant->loadCount(['memberships as active_members_count' => function ($query) {
            $query->where('is_active', true);
        }]);

        // As specified, we don't load all members to avoid massive payloads.
        // If we want administrators, we can load memberships where roles contain "admin" slug,
        // but for now we stick to basic administrative view.

        return Inertia::render('Platform/Tenant/Show', [
            'tenant' => $tenant
        ]);
    }

    public function updateStatus(
        UpdateTenantStatusRequest $request,
        Tenant $tenant,
        UpdateTenantStatusService $service
    ): RedirectResponse {
        \Illuminate\Support\Facades\Gate::authorize('platform.tenants.manage', $tenant);

        $service->execute($tenant, $request->validated('is_active'));

        return back()->with('success', 'Status do Tenant atualizado com sucesso.');
    }
}
