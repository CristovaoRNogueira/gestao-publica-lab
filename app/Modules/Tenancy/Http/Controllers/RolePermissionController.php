<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Exceptions\CannotRemoveLastEffectivePermissionException;
use App\Modules\Tenancy\Http\Requests\AssignRolePermissionRequest;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Services\RolePermissionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class RolePermissionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TenantContext $context,
        private readonly RolePermissionService $service
    ) {
    }

    public function index(int $roleId): JsonResponse
    {
        $role = Role::where('tenant_id', $this->context->getTenant()?->id)->findOrFail($roleId);

        $this->authorize('viewPermissions', $role);

        return response()->json($role->permissions);
    }

    public function store(AssignRolePermissionRequest $request, int $roleId): JsonResponse
    {
        $role = Role::where('tenant_id', $this->context->getTenant()?->id)->findOrFail($roleId);

        $this->authorize('managePermissions', $role);

        $permission = Permission::findOrFail($request->input('permission_id'));

        $this->service->attachPermission($role, $permission);

        return response()->json(['message' => 'Permission attached successfully.']);
    }

    public function destroy(int $roleId, int $permissionId): JsonResponse
    {
        $role = Role::where('tenant_id', $this->context->getTenant()?->id)->findOrFail($roleId);

        $this->authorize('managePermissions', $role);

        // Fail with 404 if the permission is not attached to this role.
        $permission = $role->permissions()->findOrFail($permissionId);

        try {
            $this->service->detachPermission($role, $permission);
        } catch (CannotRemoveLastEffectivePermissionException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(['message' => 'Permission detached successfully.']);
    }
}
