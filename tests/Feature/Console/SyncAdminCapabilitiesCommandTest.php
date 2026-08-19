<?php

namespace Tests\Feature\Console;

use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncAdminCapabilitiesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionCatalogSeeder::class);
    }

    public function test_sync_adds_missing_permissions_to_legacy_admin()
    {
        // Cenário A - Tenant Legado (falta memberships.* e invitations.*)
        $tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant-' . uniqid()]);
        $admin = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Legado',
            'slug' => 'admin',
        ]);

        $legacySlugs = [

            PermissionSlug::ROLES_VIEW->value,
            PermissionSlug::ROLES_CREATE->value,
            PermissionSlug::ROLES_UPDATE->value,
            PermissionSlug::ROLES_DELETE->value,
            PermissionSlug::ROLES_PERMISSIONS_MANAGE->value,
            PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value,
        ];

        $legacyIds = Permission::whereIn('slug', $legacySlugs)->pluck('id');
        $admin->permissions()->attach($legacyIds);

        $this->artisan('tenants:sync-admin-capabilities')
            ->expectsOutput('Iniciando sincronização...')
            ->expectsOutput('Sincronização finalizada.')
            ->assertExitCode(0);

        $currentSlugs = $admin->fresh()->permissions->pluck('slug')->toArray();
        $expectedSlugs = PermissionSlug::defaultAdminSlugs();

        sort($currentSlugs);
        sort($expectedSlugs);

        foreach ($expectedSlugs as $slug) {
            $this->assertContains($slug, $currentSlugs);
        }

        $this->assertContains(PermissionSlug::MEMBERSHIPS_MANAGE->value, $currentSlugs);
        $this->assertContains(PermissionSlug::INVITATIONS_VIEW->value, $currentSlugs);
        $this->assertContains(PermissionSlug::INVITATIONS_MANAGE->value, $currentSlugs);


    }

    public function test_sync_adds_missing_permissions_to_new_admin()
    {
        // Cenário B - Tenant Novo (falta secretarias.*)
        $tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant-' . uniqid()]);
        $admin = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Novo',
            'slug' => 'admin',
        ]);

        $newSlugs = [
            PermissionSlug::MEMBERSHIPS_MANAGE->value,
            PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value,
            PermissionSlug::INVITATIONS_VIEW->value,
            PermissionSlug::INVITATIONS_MANAGE->value,
            PermissionSlug::ROLES_VIEW->value,
            PermissionSlug::ROLES_CREATE->value,
            PermissionSlug::ROLES_UPDATE->value,
            PermissionSlug::ROLES_DELETE->value,
            PermissionSlug::ROLES_PERMISSIONS_MANAGE->value,
        ];

        $newIds = Permission::whereIn('slug', $newSlugs)->pluck('id');
        $admin->permissions()->attach($newIds);

        $this->artisan('tenants:sync-admin-capabilities')->assertExitCode(0);

        $currentSlugs = $admin->fresh()->permissions->pluck('slug')->toArray();
        $expectedSlugs = PermissionSlug::defaultAdminSlugs();

        sort($currentSlugs);
        sort($expectedSlugs);

        $this->assertEquals($expectedSlugs, $currentSlugs);
    }

    public function test_sync_does_not_remove_additional_legitimate_permissions()
    {
        // Cenário C - Permission adicional
        $tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant-' . uniqid()]);
        $admin = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'slug' => 'admin',
        ]);

        // Simula uma permissão extra no catálogo
        $extraPermission = Permission::create([
            'name' => 'Extra',
            'slug' => 'extra.permission',
        ]);

        $baseIds = Permission::whereIn('slug', PermissionSlug::defaultAdminSlugs())->pluck('id')->toArray();
        $admin->permissions()->attach($baseIds);
        $admin->permissions()->attach($extraPermission->id);

        $this->artisan('tenants:sync-admin-capabilities')->assertExitCode(0);

        $currentSlugs = $admin->fresh()->permissions->pluck('slug')->toArray();

        $this->assertContains('extra.permission', $currentSlugs);
        $this->assertCount(count(PermissionSlug::defaultAdminSlugs()) + 1, $currentSlugs);
    }

    public function test_sync_is_idempotent()
    {
        // Cenário D - Idempotência
        $tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant-' . uniqid()]);
        $admin = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'slug' => 'admin',
        ]);

        // Execução 1
        $this->artisan('tenants:sync-admin-capabilities')->assertExitCode(0);
        $count1 = \DB::table('role_permission')->where('role_id', $admin->id)->count();
        $this->assertEquals(count(PermissionSlug::defaultAdminSlugs()), $count1);

        // Execução 2
        $this->artisan('tenants:sync-admin-capabilities')->assertExitCode(0);
        $count2 = \DB::table('role_permission')->where('role_id', $admin->id)->count();
        $this->assertEquals($count1, $count2);
    }

    public function test_sync_does_not_affect_non_admin_roles()
    {
        // Cenário E - Role não-admin
        $tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant-' . uniqid()]);
        $editor = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Editor',
            'slug' => 'editor',
        ]);

        $this->artisan('tenants:sync-admin-capabilities')->assertExitCode(0);

        $this->assertEquals(0, $editor->fresh()->permissions()->count());
    }
}
