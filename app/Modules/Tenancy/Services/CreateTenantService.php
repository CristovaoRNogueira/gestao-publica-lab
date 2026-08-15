<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Models\User;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTenantService
{
    /**
     * Creates a new Tenant, its owner Membership, and bootstraps RBAC
     * with the initial administrative role.
     *
     * @param User $owner The user who will own the tenant
     * @param array $tenantData Data to create the tenant (e.g. name, slug)
     * @return Tenant
     */
    public function execute(User $owner, array $tenantData): Tenant
    {
        return DB::transaction(function () use ($owner, $tenantData) {
            // 1. Create the Tenant
            $tenant = Tenant::create([
                'name' => $tenantData['name'],
                'slug' => $tenantData['slug'] ?? Str::slug($tenantData['name']),
                'is_active' => $tenantData['is_active'] ?? true,
            ]);

            // 2. Create the initial Membership (active)
            $membership = Membership::create([
                'user_id' => $owner->id,
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]);

            // 3. Find the global Permissions (fails if catalog is not seeded)
            $permissions = Permission::whereIn('slug', [
                PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value,
                PermissionSlug::ROLES_VIEW->value,
                PermissionSlug::ROLES_CREATE->value,
                PermissionSlug::ROLES_UPDATE->value,
                PermissionSlug::ROLES_DELETE->value,
                PermissionSlug::ROLES_PERMISSIONS_MANAGE->value,
            ])->get();

            if ($permissions->count() !== 6) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Not all required permissions were found in the catalog.');
            }

            // 4. Create the tenant-scoped Admin Role
            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Administrador',
                'slug' => 'admin',
            ]);

            // 5. Attach the permissions to the role
            $role->permissions()->attach($permissions->pluck('id'));

            // 6. Attach the role to the membership
            $membership->roles()->attach($role->id);

            return $tenant;
        });
    }
}
