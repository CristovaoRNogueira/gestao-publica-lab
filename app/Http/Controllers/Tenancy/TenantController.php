<?php

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Services\TenantResolver;
use App\Http\Requests\Tenancy\CreateTenantRequest;
use App\Modules\Tenancy\Exceptions\TenantSlugAlreadyExistsException;
use App\Modules\Tenancy\Services\CreateTenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    /**
     * Create a new Tenant, assigning the authenticated user as the owner.
     */
    public function store(CreateTenantRequest $request, CreateTenantService $service): RedirectResponse
    {
        try {
            $tenant = $service->execute($request->user(), $request->validated());

            // Automatically select the newly created tenant
            $request->session()->put('tenant_id', $tenant->id);

            return redirect()->intended('/dashboard')->with('success', 'Tenant criado com sucesso.');
        } catch (TenantSlugAlreadyExistsException $e) {
            throw ValidationException::withMessages([
                'slug' => 'O slug gerado ou fornecido já está em uso por outro tenant.',
            ]);
        }
    }
}
