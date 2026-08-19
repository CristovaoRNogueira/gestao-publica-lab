<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\OrganizationUnit;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Enums\PermissionSlug;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionCatalogSeeder::class,
            PlatformPermissionCatalogSeeder::class,
        ]);

        $superAdminUser = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
            ]
        );

        $localAdminUser = User::firstOrCreate(
            ['email' => 'localadmin@example.com'],
            [
                'name' => 'Local Admin',
                'password' => Hash::make('password'),
            ]
        );

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'prefeitura'],
            ['name' => 'Prefeitura de Teste', 'is_active' => true]
        );

        $saude = OrganizationUnit::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'secretaria-de-saude'],
            ['name' => 'Secretaria de Saúde', 'type' => 'Secretaria', 'is_active' => true]
        );

        $posto = OrganizationUnit::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'posto-central', 'parent_id' => $saude->id],
            ['name' => 'Posto Central', 'type' => 'Unidade', 'is_active' => true]
        );

        $superRole = Role::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Super Administrador', 'slug' => 'super-administrador']
        );
        // Sync all permissions including global scope
        $superRole->permissions()->sync(Permission::all());

        $localRole = Role::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Administrador Local', 'slug' => 'administrador-local']
        );
        $localPermissions = Permission::whereIn('slug', [
            PermissionSlug::ORGANIZATION_UNITS_VIEW->value,
            PermissionSlug::ORGANIZATION_UNITS_CREATE->value,
            PermissionSlug::ORGANIZATION_UNITS_UPDATE->value,
            PermissionSlug::MEMBERSHIPS_MANAGE->value,
            PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value,
        ])->get();
        $localRole->permissions()->sync($localPermissions);

        $superMembership = Membership::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $superAdminUser->id],
            ['status' => 'active', 'organization_unit_id' => null]
        );
        $superMembership->roles()->sync([$superRole->id]);

        $localMembership = Membership::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $localAdminUser->id],
            ['status' => 'active', 'organization_unit_id' => $saude->id]
        );
        $localMembership->roles()->sync([$localRole->id]);
    }
}
