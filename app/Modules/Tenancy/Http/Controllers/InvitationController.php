<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Services\CreateInvitationService;
use App\Modules\Tenancy\Services\ResendInvitationService;
use App\Modules\Tenancy\Services\RevokeInvitationService;
use App\Modules\Tenancy\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', TenantInvitation::class);

        $tenantId = app(\App\Modules\Tenancy\Context\TenantContext::class)->getTenant()->id;

        $invitations = TenantInvitation::where('tenant_id', $tenantId)
            ->with(['role:id,name', 'inviter:id,name,email'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return Inertia::render('Invitation/Index', [
            'invitations' => $invitations
        ]);
    }

    public function create(): Response
    {
        $this->authorize('manage', TenantInvitation::class);

        $tenantId = app(\App\Modules\Tenancy\Context\TenantContext::class)->getTenant()->id;
        $roles = Role::where('tenant_id', $tenantId)->orderBy('name')->get();
        $units = \App\Modules\Tenancy\Models\OrganizationUnit::where('tenant_id', $tenantId)->orderBy('name')->get();

        return Inertia::render('Invitation/Create', [
            'roles' => $roles,
            'units' => $units,
        ]);
    }

    public function store(Request $request, CreateInvitationService $service)
    {
        $this->authorize('manage', TenantInvitation::class);

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where('tenant_id', app(\App\Modules\Tenancy\Context\TenantContext::class)->getTenant()->id),
            ],
            'organization_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_units', 'id')->where('tenant_id', app(\App\Modules\Tenancy\Context\TenantContext::class)->getTenant()->id),
            ],
        ]);

        $service->execute(
            $validated['email'],
            $validated['role_id'],
            app(\App\Modules\Tenancy\Context\TenantContext::class)->getTenant()->id,
            $request->user(),
            $validated['organization_unit_id'] ?? null
        );

        return redirect('/invitations')->with('success', 'Convite enviado com sucesso.');
    }

    public function resend(TenantInvitation $invitation, ResendInvitationService $service)
    {
        $this->authorize('resend', $invitation);

        // Ensure invitation belongs to current tenant
        if ($invitation->tenant_id !== app(\App\Modules\Tenancy\Context\TenantContext::class)->getTenant()->id) {
            abort(404);
        }

        $service->execute($invitation);

        return back()->with('success', 'Convite reenviado com sucesso.');
    }

    public function revoke(TenantInvitation $invitation, RevokeInvitationService $service)
    {
        $this->authorize('revoke', $invitation);

        if ($invitation->tenant_id !== app(\App\Modules\Tenancy\Context\TenantContext::class)->getTenant()->id) {
            abort(404);
        }

        $service->execute($invitation);

        return back()->with('success', 'Convite revogado com sucesso.');
    }
}
