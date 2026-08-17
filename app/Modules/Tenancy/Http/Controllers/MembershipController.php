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

        $actorMembership = $this->tenantContext->getMembership();
        $scopeService = app(\App\Modules\Tenancy\Services\OrganizationScope::class);

        $availableUnits = \App\Modules\Tenancy\Models\OrganizationUnit::where('tenant_id', $tenant->id)
            ->get()
            ->filter(fn($unit) => $scopeService->canManage($actorMembership, $unit))
            ->values();

        $availableRoles = \App\Modules\Tenancy\Models\Role::where('tenant_id', $tenant->id)
            ->get()
            ->filter(function ($role) use ($actorMembership) {
                $rolePermissions = $role->permissions()->pluck('slug')->toArray();
                $actorPermissions = $actorMembership->roles->flatMap->permissions->pluck('slug')->unique()->toArray();
                $missingPermissions = array_diff($rolePermissions, $actorPermissions);
                return empty($missingPermissions);
            })->values();

        return Inertia::render('Membership/Index', [
            'memberships' => $memberships,
            'availableRoles' => $availableRoles,
            'availableUnits' => $availableUnits,
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
    public function storeManual(Request $request, \App\Modules\Tenancy\Services\AddManualMemberService $addService): RedirectResponse
    {
        Gate::authorize('create', Membership::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'organization_unit_id' => ['nullable', 'integer', 'exists:organization_units,id'],
        ]);

        try {
            $result = $addService->execute(
                $validated['name'],
                $validated['email'],
                $validated['role_id'],
                $validated['organization_unit_id'] ?? null
            );

            $message = $result['created_user']
                ? 'Membro adicionado com sucesso. Um e-mail foi enviado para que a pessoa defina sua senha.'
                : 'Membro adicionado com sucesso.';

            return back()->with('flash', ['success' => $message]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if (request()->hasHeader('X-Inertia')) {
                return back()->with('error', $e->getMessage());
            }
            abort($e->getStatusCode(), $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            if (request()->hasHeader('X-Inertia')) {
                return back()->with('error', $e->getMessage());
            }
            abort(400, $e->getMessage());
        }
    }
}
