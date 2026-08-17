<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $hasActiveMemberships = $request->user()
            ->memberships()
            ->where('status', \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE)
            ->exists();

        if ($hasActiveMemberships) {
            return Inertia::render('Dashboard');
        }

        $hasPendingMemberships = $request->user()
            ->memberships()
            ->where('status', \App\Modules\Tenancy\Models\Membership::STATUS_PENDING)
            ->exists();

        if ($hasPendingMemberships) {
            return redirect()->route('pending-approval');
        }

        $hasAnyMemberships = $request->user()->memberships()->exists();

        if ($hasAnyMemberships) {
            return redirect()->route('access-denied');
        }

        return redirect()->route('tenants.create');
    }
}
