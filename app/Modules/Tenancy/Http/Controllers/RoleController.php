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

class RoleController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TenantContext $context,
        private readonly RoleService $roleService,
    ) {
    }

    public function index()
    {
        $this->authorize('viewAny', Role::class);

        $tenantId = $this->context->getTenant()?->id;

        $roles = Role::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->paginate(15);

        return response()->json($roles);
    }

    public function show(int $id)
    {
        $tenantId = $this->context->getTenant()?->id;
        $role = Role::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('view', $role);

        return response()->json($role);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $role = $this->roleService->create($request->validated());

        return redirect()->route('roles.show', $role->id)
            ->with('success', 'Papel criado com sucesso.');
    }

    public function update(UpdateRoleRequest $request, int $id): RedirectResponse
    {
        $tenantId = $this->context->getTenant()?->id;
        $role = Role::where('tenant_id', $tenantId)->findOrFail($id);

        $this->authorize('update', $role);

        $this->roleService->update($role, $request->validated());

        return redirect()->route('roles.show', $role->id)
            ->with('success', 'Papel atualizado com sucesso.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $tenantId = $this->context->getTenant()?->id;
        $role = Role::where('tenant_id', $tenantId)->findOrFail($id);

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
