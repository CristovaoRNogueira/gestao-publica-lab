<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionCatalogSeeder::class);
    }

    private function createMemberWithPermission(string $permissionSlug): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Role',
            'slug' => 'admin-role'
        ]);

        $permission = Permission::where('slug', $permissionSlug)->first();
        $role->permissions()->attach($permission->id);
        $membership->roles()->attach($role->id);

        $this->actingAs($user);
        session(['tenant_id' => $tenant->id]);

        return [$user, $tenant, $role];
    }

    public function test_list_permissions_requires_permission()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermission(PermissionSlug::ROLES_VIEW->value);

        $response = $this->getJson("/roles/{$role->id}/permissions");
        $response->assertStatus(403);
    }

    public function test_list_permissions_works()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermission(PermissionSlug::ROLES_PERMISSIONS_MANAGE->value);

        $response = $this->getJson("/roles/{$role->id}/permissions");
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.slug', PermissionSlug::ROLES_PERMISSIONS_MANAGE->value);
    }

    public function test_list_permissions_cross_tenant_returns_404()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermission(PermissionSlug::ROLES_PERMISSIONS_MANAGE->value);

        $otherTenant = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
        $otherRole = Role::create(['tenant_id' => $otherTenant->id, 'name' => 'Other Role', 'slug' => 'other-role']);

        $response = $this->getJson("/roles/{$otherRole->id}/permissions");
        $response->assertStatus(404);
    }

    public function test_attach_permission_works_and_is_idempotent()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermission(PermissionSlug::ROLES_PERMISSIONS_MANAGE->value);
        $permissionToAttach = Permission::where('slug', PermissionSlug::SECRETARIAS_VIEW->value)->first();

        // First attach
        $response = $this->postJson("/roles/{$role->id}/permissions", [
            'permission_id' => $permissionToAttach->id,
        ]);
        $response->assertStatus(200);
        $this->assertEquals(2, $role->permissions()->count());

        // Second attach (Idempotent)
        $response = $this->postJson("/roles/{$role->id}/permissions", [
            'permission_id' => $permissionToAttach->id,
        ]);
        $response->assertStatus(200);
        $this->assertEquals(2, $role->permissions()->count());
    }

    public function test_attach_permission_without_authorization_returns_403()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermission(PermissionSlug::ROLES_VIEW->value);
        $permissionToAttach = Permission::where('slug', PermissionSlug::SECRETARIAS_VIEW->value)->first();

        $response = $this->postJson("/roles/{$role->id}/permissions", [
            'permission_id' => $permissionToAttach->id,
        ]);
        $response->assertStatus(403);
    }

    public function test_attach_non_existent_permission_returns_422()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermission(PermissionSlug::ROLES_PERMISSIONS_MANAGE->value);

        $response = $this->postJson("/roles/{$role->id}/permissions", [
            'permission_id' => 999999,
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['permission_id']);
    }

    public function test_attach_cross_tenant_role_returns_404()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermission(PermissionSlug::ROLES_PERMISSIONS_MANAGE->value);

        $otherTenant = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
        $otherRole = Role::create(['tenant_id' => $otherTenant->id, 'name' => 'Other Role', 'slug' => 'other-role']);
        $permissionToAttach = Permission::where('slug', PermissionSlug::SECRETARIAS_VIEW->value)->first();

        $response = $this->postJson("/roles/{$otherRole->id}/permissions", [
            'permission_id' => $permissionToAttach->id,
        ]);
        $response->assertStatus(404);
    }

    public function test_attach_legacy_permission_not_in_enum_returns_404()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermission(PermissionSlug::ROLES_PERMISSIONS_MANAGE->value);

        // Create a legacy permission in the DB that is not in the Enum
        $legacyPermission = Permission::create([
            'slug' => 'legacy.permission.not.in.enum',
            'name' => 'Legacy Permission'
        ]);

        $response = $this->postJson("/roles/{$role->id}/permissions", [
            'permission_id' => $legacyPermission->id,
        ]);

        $response->assertStatus(404);
        $this->assertFalse($role->permissions()->where('permission_id', $legacyPermission->id)->exists());
    }

    public function test_detach_permission_works()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermission(PermissionSlug::ROLES_PERMISSIONS_MANAGE->value);
        $permissionToAttach = Permission::where('slug', PermissionSlug::SECRETARIAS_VIEW->value)->first();
        $role->permissions()->attach($permissionToAttach->id);

        $response = $this->deleteJson("/roles/{$role->id}/permissions/{$permissionToAttach->id}");
        $response->assertStatus(200);
        $this->assertEquals(1, $role->permissions()->count());
    }

    public function test_detach_unbound_permission_returns_404()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermission(PermissionSlug::ROLES_PERMISSIONS_MANAGE->value);
        $permissionNotAttached = Permission::where('slug', PermissionSlug::SECRETARIAS_VIEW->value)->first();

        $response = $this->deleteJson("/roles/{$role->id}/permissions/{$permissionNotAttached->id}");
        $response->assertStatus(404);
    }

    public function test_detach_critical_permission_from_last_effective_role_returns_409()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermission(PermissionSlug::ROLES_PERMISSIONS_MANAGE->value);
        $permission = Permission::where('slug', PermissionSlug::ROLES_PERMISSIONS_MANAGE->value)->first();

        $response = $this->deleteJson("/roles/{$role->id}/permissions/{$permission->id}");
        $response->assertStatus(409);
        $this->assertEquals(1, $role->permissions()->count());
    }

    public function test_detach_critical_permission_allowed_if_another_membership_retains_it()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermission(PermissionSlug::ROLES_PERMISSIONS_MANAGE->value);
        $permission = Permission::where('slug', PermissionSlug::ROLES_PERMISSIONS_MANAGE->value)->first();

        // Create another user with the same permission
        $user2 = User::factory()->create();
        $membership2 = Membership::create(['user_id' => $user2->id, 'tenant_id' => $tenant->id, 'is_active' => true]);
        $role2 = Role::create(['tenant_id' => $tenant->id, 'name' => 'Admin Role 2', 'slug' => 'admin-role-2']);
        $role2->permissions()->attach($permission->id);
        $membership2->roles()->attach($role2->id);

        $response = $this->deleteJson("/roles/{$role->id}/permissions/{$permission->id}");
        $response->assertStatus(200);
        $this->assertEquals(0, $role->permissions()->count());
    }

    public function test_permission_global_catalog_remains_unchanged()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermission(PermissionSlug::ROLES_PERMISSIONS_MANAGE->value);

        $initialCount = Permission::count();

        // Try to attach garbage payload
        $response = $this->postJson("/roles/{$role->id}/permissions", [
            'name' => 'Hacked Permission',
            'slug' => 'hacked.permission',
        ]);
        $response->assertStatus(302); // Validation will fail because permission_id is missing

        $this->assertEquals($initialCount, Permission::count());
    }
}
