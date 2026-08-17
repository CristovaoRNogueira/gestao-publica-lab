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
use Illuminate\Http\RedirectResponse;
use App\Modules\Tenancy\Services\MembershipStatusService;

class MembershipController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MembershipStatusService $statusService,
    ) {
    }

    public function index(): Response
    {
        Gate::authorize('viewAny', Membership::class);

        $tenant = $this->tenantContext->getTenant();
        if (!$tenant) {
            abort(403);
        }

        $memberships = Membership::with(['user', 'roles.permissions'])
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

    public function activate(Membership $membership): RedirectResponse
    {
        Gate::authorize('activate', [Membership::class, $membership]);

        $this->statusService->activate($membership);

        return back()->with('flash', ['success' => 'Membro ativado com sucesso.']);
    }

    public function deactivate(Membership $membership): RedirectResponse
    {
        Gate::authorize('deactivate', [Membership::class, $membership]);

        try {
            $this->statusService->deactivate($membership);
        } catch (\App\Modules\Tenancy\Exceptions\CannotRemoveLastAdminException $e) {
            if (request()->hasHeader('X-Inertia')) {
                return back()->with('error', $e->getMessage());
            }
            abort(409, $e->getMessage());
        }

        return back()->with('flash', ['success' => 'Membro desativado com sucesso.']);
    }

    public function approve(Membership $membership): RedirectResponse
    {
        Gate::authorize('approve', [Membership::class, $membership]);

        $this->statusService->approve($membership);

        return back()->with('flash', ['success' => 'Acesso aprovado com sucesso.']);
    }

    public function reject(Membership $membership): RedirectResponse
    {
        Gate::authorize('reject', [Membership::class, $membership]);

        $this->statusService->reject($membership);

        return back()->with('flash', ['success' => 'Solicitação de acesso recusada.']);
    }
}
