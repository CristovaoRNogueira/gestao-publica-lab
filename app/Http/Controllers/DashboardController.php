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
            ->where('is_active', true)
            ->exists();

        if (! $hasActiveMemberships) {
            return redirect()->route('tenants.create');
        }

        return Inertia::render('Dashboard');
    }
}
