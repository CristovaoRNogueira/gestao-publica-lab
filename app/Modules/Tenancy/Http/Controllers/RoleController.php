<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Exceptions\CannotDeleteRoleInUseException;
use App\Modules\Tenancy\Http\Requests\StoreRoleRequest;
use App\Modules\Tenancy\Http\Requests\UpdateRoleRequest;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Services\RoleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TenantContext $context,
        private readonly RoleService $roleService,
    ) {
    }

    public function index(): Response
    {
        $this->authorize('viewAny', Role::class);

        $tenantId = $this->context->getTenant()?->id;

        $roles = Role::where('tenant_id', $tenantId)
            ->withCount('memberships')
            ->orderBy('name')
            ->paginate(15);

        return Inertia::render('Role/Index', [
            'roles' => $roles
        ]);
    }

    public function show(Role $role): Response
    {
        abort_if($role->tenant_id !== $this->context->getTenant()?->id, 404);

        $role->load(['permissions', 'memberships.user']);

        $this->authorize('view', $role);

        $allPermissions = \App\Modules\Tenancy\Models\Permission::orderBy('name')->get();

        return Inertia::render('Role/Show', [
            'role' => $role,
            'allPermissions' => $allPermissions
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('Role/Create');
    }

    public function edit(Role $role): Response
    {
        abort_if($role->tenant_id !== $this->context->getTenant()?->id, 404);

        $this->authorize('update', $role);

        return Inertia::render('Role/Edit', [
            'role' => $role
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $role = $this->roleService->create($request->validated());

        return redirect()->route('roles.show', $role->id)
            ->with('success', 'Papel criado com sucesso.');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->tenant_id !== $this->context->getTenant()?->id, 404);

        $this->authorize('update', $role);

        $this->roleService->update($role, $request->validated());

        return redirect()->route('roles.show', $role->id)
            ->with('success', 'Papel atualizado com sucesso.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->tenant_id !== $this->context->getTenant()?->id, 404);

        $this->authorize('delete', $role);

        try {
            $this->roleService->delete($role);
            return redirect()->route('roles.index')
                ->with('success', 'Papel excluído com sucesso.');
        } catch (CannotDeleteRoleInUseException $e) {
            abort(409, $e->getMessage());
        }
    }
}
