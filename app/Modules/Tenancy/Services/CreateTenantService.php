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
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use App\Modules\Tenancy\Exceptions\TenantSlugAlreadyExistsException;

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
            $slug = $tenantData['slug'] ?? Str::slug($tenantData['name']);

            if (empty($slug)) {
                throw ValidationException::withMessages([
                    'slug' => 'O nome fornecido não resultou em um slug válido.',
                ]);
            }

            try {
                // 1. Create the Tenant
                $tenant = Tenant::create([
                    'name' => $tenantData['name'],
                    'slug' => $slug,
                    'is_active' => true, // Enforce active on creation
                ]);
            } catch (QueryException $e) {
                $isPostgresSlugCollision = $e->getCode() === '23505' && str_contains($e->getMessage(), 'tenants_slug_unique');
                $isSqliteSlugCollision = $e->getCode() === '23000' && str_contains($e->getMessage(), 'tenants.slug');

                if ($isPostgresSlugCollision || $isSqliteSlugCollision) {
                    throw new TenantSlugAlreadyExistsException($slug);
                }
                throw $e;
            }

            // 2. Create the initial Membership (active)
            $membership = Membership::create([
                'user_id' => $owner->id,
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]);

            $expectedSlugs = PermissionSlug::defaultAdminSlugs();

            // 3. Find the global Permissions (fails if catalog is not seeded)
            $permissions = Permission::whereIn('slug', $expectedSlugs)->get();

            $foundSlugs = $permissions->pluck('slug')->toArray();
            $missingSlugs = array_diff($expectedSlugs, $foundSlugs);

            if (!empty($missingSlugs)) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                    'Not all required permissions were found in the catalog. Missing: ' . implode(', ', $missingSlugs)
                );
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
