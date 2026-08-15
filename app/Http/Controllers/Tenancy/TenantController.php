<?php

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Services\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantResolver $resolver,
    ) {
    }

    /**
     * Handle explicit tenant selection (ADR-005).
     *
     * Validates tenant_id via TenantResolver (existence, active status,
     * and user membership) before storing in session.
     */
    public function select(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $resolved = $this->resolver->resolve($validated['tenant_id'], $user);

        if (! $resolved) {
            abort(403, 'Tenant inválido ou sem membership.');
        }

        $request->session()->put('tenant_id', $resolved->tenant->id);

        return redirect()->intended('/dashboard');
    }
}
