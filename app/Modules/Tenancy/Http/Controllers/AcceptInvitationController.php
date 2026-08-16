<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Services\AcceptInvitationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AcceptInvitationController extends Controller
{
    public function show(string $token)
    {
        $tokenHash = hash('sha256', $token);

        $invitation = TenantInvitation::with(['tenant', 'inviter'])
            ->where('token_hash', $tokenHash)
            ->first();

        // Safe failure: Do not expose email or leak details.
        if (!$invitation || $invitation->status !== 'pending' || $invitation->expires_at < now()) {
            return Inertia::render('Public/Invites/Accept', [
                'isValid' => false,
                'token' => $token,
            ]);
        }

        // If guest, store intended URL to return here after login/register
        if (!auth()->check()) {
            session(['url.intended' => url()->current()]);
        }

        return Inertia::render('Public/Invites/Accept', [
            'isValid' => true,
            'token' => $token,
            'tenantName' => $invitation->tenant->name,
            'inviterName' => $invitation->inviter->name,
            'expiresAt' => $invitation->expires_at->toIso8601String(),
        ]);
    }

    public function accept(string $token, Request $request, AcceptInvitationService $service)
    {
        try {
            $invitation = $service->execute($token, $request->user());

            // Set the tenant in session so they go straight there
            session(['tenant_id' => $invitation->tenant_id]);

            return redirect()->route('dashboard')->with('success', 'Convite aceito com sucesso.');
        } catch (\Exception $e) {
            $status = $e->getCode() ?: 400;
            if ($status < 400 || $status >= 600) {
                $status = 400;
            }
            abort($status, $e->getMessage());
        }
    }
}
