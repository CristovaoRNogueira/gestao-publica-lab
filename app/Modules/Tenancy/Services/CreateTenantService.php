<?php

namespace App\Modules\Tenancy\Services;

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

            // 3. Ensure global Permission exists
            $permission = Permission::firstOrCreate([
                'slug' => 'memberships.roles.manage',
            ], [
                'name' => 'Gerenciar Papéis de Associação',
            ]);

            // 4. Create the tenant-scoped Admin Role
            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Administrador',
                'slug' => 'admin',
            ]);

            // 5. Attach the permission to the role
            $role->permissions()->attach($permission->id);

            // 6. Attach the role to the membership
            $membership->roles()->attach($role->id);

            return $tenant;
        });
    }
}
