<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class PendingApprovalController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // If they have active memberships, send them to the dashboard
        if ($user->memberships()->where('status', \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE)->exists()) {
            return redirect()->route('dashboard');
        }

        $pendingMemberships = $user->memberships()
            ->where('status', \App\Modules\Tenancy\Models\Membership::STATUS_PENDING)
            ->with(['tenant', 'organizationUnit'])
            ->get();

        if ($pendingMemberships->isEmpty()) {
            return redirect()->route('tenants.create');
        }

        return Inertia::render('PendingApproval', [
            'memberships' => $pendingMemberships->map(function ($membership) {
                return [
                    'id' => $membership->id,
                    'tenant_name' => $membership->tenant->name,
                    'unit_name' => $membership->organizationUnit ? $membership->organizationUnit->name : null,
                    'created_at' => $membership->created_at->toIso8601String(),
                ];
            })
        ]);
    }
}
