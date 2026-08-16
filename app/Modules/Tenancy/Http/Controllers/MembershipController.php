<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function index(): Response
    {
        Gate::authorize('viewAny', Membership::class);

        $tenant = $this->tenantContext->getTenant();
        if (!$tenant) {
            abort(403);
        }

        $memberships = Membership::with(['user', 'roles'])
            ->where('tenant_id', $tenant->id)
            ->get();

        return Inertia::render('Membership/Index', [
            'memberships' => $memberships,
        ]);
    }

    public function edit(Membership $membership): Response
    {
        Gate::authorize('manageRoles', [Membership::class, $membership]);

        $tenant = $this->tenantContext->getTenant();
        if (!$tenant) {
            abort(403);
        }

        $membership->load(['user', 'roles']);

        $availableRoles = Role::where('tenant_id', $tenant->id)->get();

        return Inertia::render('Membership/Edit', [
            'membership' => $membership,
            'availableRoles' => $availableRoles,
        ]);
    }
}
