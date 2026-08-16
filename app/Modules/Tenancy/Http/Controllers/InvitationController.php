<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Services\CreateInvitationService;
use App\Modules\Tenancy\Services\ResendInvitationService;
use App\Modules\Tenancy\Services\RevokeInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class InvitationController extends Controller
{
    use AuthorizesRequests;
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
        ]);

        $service->execute(
            $validated['email'],
            $validated['role_id'],
            app(\App\Modules\Tenancy\Context\TenantContext::class)->getTenant()->id,
            $request->user()
        );

        return back()->with('success', 'Convite enviado com sucesso.');
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
