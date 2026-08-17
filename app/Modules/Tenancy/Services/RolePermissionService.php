<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Exceptions\CannotRemoveLastEffectivePermissionException;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RolePermissionService
{
    public function __construct(
        private readonly TenantContext $context
    ) {
    }
    /**
     * @var string[]
     */
    private const CRITICAL_PERMISSIONS = [
        PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value,
        PermissionSlug::ROLES_PERMISSIONS_MANAGE->value,
    ];

    public function attachPermission(Role $role, Permission $permission): void
    {
        DB::transaction(function () use ($role, $permission) {
            Tenant::lockForUpdate()->find($role->tenant_id);

            if (PermissionSlug::tryFrom($permission->slug) === null) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
            }

            if (in_array($permission->slug, self::CRITICAL_PERMISSIONS, true)) {
                $membership = $this->context->getMembership();
                if (!$membership) {
                    throw ValidationException::withMessages(['permission_id' => ['Não autorizado.']]);
                }

                foreach (self::CRITICAL_PERMISSIONS as $criticalSlug) {
                    if (!$membership->hasPermission($criticalSlug)) {
                        throw ValidationException::withMessages([
                            'permission_id' => ['Você precisa de todas as permissões administrativas para conceder esta permissão.']
                        ]);
                    }
                }
            }

            // Permission attachment is idempotent.
            if (! $role->permissions()->where('permission_id', $permission->id)->exists()) {
                $role->permissions()->attach($permission->id);
            }
        });
    }

    public function detachPermission(Role $role, Permission $permission): void
    {
        DB::transaction(function () use ($role, $permission) {
            Tenant::lockForUpdate()->find($role->tenant_id);

            if (in_array($permission->slug, self::CRITICAL_PERMISSIONS, true)) {
                $this->detachCriticalPermissionSafely($role, $permission);
                return;
            }

            $role->permissions()->detach($permission->id);
        });
    }

    private function detachCriticalPermissionSafely(Role $role, Permission $permission): void
    {
        // 1. If the role has no active memberships, it can safely lose the permission.
        $hasActiveMemberships = $role->memberships()->where('status', \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE)->exists();

        if ($hasActiveMemberships) {
            // 2. Calculate Effective Capacity POST-detach
            $this->ensureEffectiveCapacityRemains($role, $permission);
        }

        // 3. Detach
        $role->permissions()->detach($permission->id);
    }

    private function ensureEffectiveCapacityRemains(Role $roleTargeted, Permission $permission): void
    {
        $tenantId = $roleTargeted->tenant_id;

        // We need to verify if there is at least one active membership in the tenant
        // that still possesses the critical permission, IGNORING the role targeted for detachment.
        // A membership possesses the permission if it has ANY role (other than $roleTargeted)
        // that contains the permission.

        $hasRemainingAdmin = Membership::where('tenant_id', $tenantId)
            ->where('status', \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE)
            ->whereHas('roles', function ($query) use ($roleTargeted, $permission) {
                $query->where('roles.id', '!=', $roleTargeted->id)
                      ->whereHas('permissions', function ($permQuery) use ($permission) {
                          $permQuery->where('permissions.id', $permission->id);
                      });
            })
            ->exists();

        if (! $hasRemainingAdmin) {
            throw new CannotRemoveLastEffectivePermissionException($permission->name ?? $permission->slug);
        }
    }
}
