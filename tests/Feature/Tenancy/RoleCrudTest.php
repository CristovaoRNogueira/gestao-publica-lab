<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->seed(\Database\Seeders\PermissionCatalogSeeder::class);
    }

    private function createTenantAndUser(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id]);

        return [$user, $tenant, $membership];
    }

    private function grantPermission(Tenant $tenant, Membership $membership, string $permissionSlug): Role
    {
        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Role with ' . $permissionSlug,
            'slug' => 'role-' . str_replace('.', '-', $permissionSlug) . '-' . uniqid(),
        ]);

        $permission = \App\Modules\Tenancy\Models\Permission::firstOrCreate(
            ['slug' => $permissionSlug],
            ['name' => $permissionSlug]
        );
        $role->permissions()->attach($permission->id);
        $membership->roles()->attach($role->id);
        $membership->load('roles.permissions');

        return $role;
    }

    public function test_list_roles_requires_permission()
    {
        [$user, $tenant, $membership] = $this->createTenantAndUser();

        $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->get('/roles');
        $response->assertStatus(403);
    }

    public function test_list_roles_shows_only_active_tenant_roles()
    {
        [$user, $tenantA, $membershipA] = $this->createTenantAndUser();
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);

        $roleA = Role::create(['tenant_id' => $tenantA->id, 'name' => 'Role A', 'slug' => 'role-a']);
        $roleB = Role::create(['tenant_id' => $tenantB->id, 'name' => 'Role B', 'slug' => 'role-b']);

        $this->grantPermission($tenantA, $membershipA, PermissionSlug::ROLES_VIEW->value);

        $response = $this->actingAs($user)->withSession(['tenant_id' => $tenantA->id])->get('/roles');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Role/Index')
            ->where('roles.data.0.id', $roleA->id)
        );
    }

    public function test_show_role_requires_ownership_and_permission()
    {
        [$user, $tenantA, $membershipA] = $this->createTenantAndUser();
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);

        $roleA = Role::create(['tenant_id' => $tenantA->id, 'name' => 'Role A', 'slug' => 'role-a']);
        $roleB = Role::create(['tenant_id' => $tenantB->id, 'name' => 'Role B', 'slug' => 'role-b']);

        $this->actingAs($user)->withSession(['tenant_id' => $tenantA->id])->get("/roles/{$roleA->id}")->assertStatus(403);

        $this->grantPermission($tenantA, $membershipA, PermissionSlug::ROLES_VIEW->value);

        $this->actingAs($user)->withSession(['tenant_id' => $tenantA->id])->get("/roles/{$roleA->id}")
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Role/Show')
                ->where('role.id', $roleA->id)
                ->has('allPermissions')
                ->has('allPermissions.0.label') // label mapped from Enum
                ->has('allPermissions.0.description')
            );

        $this->actingAs($user)->withSession(['tenant_id' => $tenantA->id])->get("/roles/{$roleB->id}")->assertStatus(404);
    }

    public function test_create_role_requires_permission()
    {
        [$user, $tenant, $membership] = $this->createTenantAndUser();

        $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->post('/roles', [
            'name' => 'New Role',
            'slug' => 'new-role',
        ])->assertStatus(403);
    }

    public function test_create_role_success()
    {
        [$user, $tenant, $membership] = $this->createTenantAndUser();
        $this->grantPermission($tenant, $membership, PermissionSlug::ROLES_CREATE->value);

        $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->post('/roles', [
            'name' => 'New Role',
            'slug' => 'new-role',
            'description' => 'A new role',
        ]);

        $role = Role::where('slug', 'new-role')->first();
        $this->assertNotNull($role);
        $this->assertEquals($tenant->id, $role->tenant_id);
        $this->assertEquals('New Role', $role->name);

        $response->assertRedirect("/roles/{$role->id}");
    }

    public function test_create_role_with_duplicate_slug_intra_tenant_fails_with_422()
    {
        [$user, $tenant, $membership] = $this->createTenantAndUser();
        Role::create(['tenant_id' => $tenant->id, 'name' => 'Old', 'slug' => 'duplicate']);
        $this->grantPermission($tenant, $membership, PermissionSlug::ROLES_CREATE->value);

        $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->post('/roles', [
            'name' => 'New',
            'slug' => 'duplicate',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('slug');
    }

    public function test_create_role_with_duplicate_slug_cross_tenant_succeeds()
    {
        [$user, $tenantA, $membershipA] = $this->createTenantAndUser();
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);
        Role::create(['tenant_id' => $tenantB->id, 'name' => 'Old', 'slug' => 'duplicate']);

        $this->grantPermission($tenantA, $membershipA, PermissionSlug::ROLES_CREATE->value);

        $this->actingAs($user)->withSession(['tenant_id' => $tenantA->id])->post('/roles', [
            'name' => 'New',
            'slug' => 'duplicate',
        ])->assertRedirect();

        $this->assertEquals(2, Role::where('slug', 'duplicate')->count());
    }

    public function test_update_role_requires_ownership_and_permission()
    {
        [$user, $tenantA, $membershipA] = $this->createTenantAndUser();
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);

        $roleA = Role::create(['tenant_id' => $tenantA->id, 'name' => 'Role A', 'slug' => 'role-a']);
        $roleB = Role::create(['tenant_id' => $tenantB->id, 'name' => 'Role B', 'slug' => 'role-b']);

        $this->actingAs($user)->withSession(['tenant_id' => $tenantA->id])->put("/roles/{$roleA->id}", [
            'name' => 'Updated',
            'slug' => 'updated'
        ])->assertStatus(403);

        $this->grantPermission($tenantA, $membershipA, PermissionSlug::ROLES_UPDATE->value);

        $this->actingAs($user)->withSession(['tenant_id' => $tenantA->id])->put("/roles/{$roleB->id}", [
            'name' => 'Updated',
            'slug' => 'updated'
        ])->assertStatus(404);

        $this->actingAs($user)->withSession(['tenant_id' => $tenantA->id])->put("/roles/{$roleA->id}", [
            'name' => 'Updated Role A',
            'slug' => 'role-a'
        ])->assertRedirect("/roles/{$roleA->id}");

        $roleA->refresh();
        $this->assertEquals('Updated Role A', $roleA->name);
        $this->assertEquals('role-a', $roleA->slug); // slug is immutable
    }

    public function test_delete_role_requires_ownership_and_permission()
    {
        [$user, $tenantA, $membershipA] = $this->createTenantAndUser();
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);

        $roleA = Role::create(['tenant_id' => $tenantA->id, 'name' => 'Role A', 'slug' => 'role-a']);
        $roleB = Role::create(['tenant_id' => $tenantB->id, 'name' => 'Role B', 'slug' => 'role-b']);

        $this->actingAs($user)->withSession(['tenant_id' => $tenantA->id])->delete("/roles/{$roleA->id}")->assertStatus(403);

        $this->grantPermission($tenantA, $membershipA, PermissionSlug::ROLES_DELETE->value);

        $this->actingAs($user)->withSession(['tenant_id' => $tenantA->id])->delete("/roles/{$roleB->id}")->assertStatus(404);

        $this->actingAs($user)->withSession(['tenant_id' => $tenantA->id])->delete("/roles/{$roleA->id}")->assertRedirect('/roles');

        $this->assertNull(Role::find($roleA->id));
    }

    public function test_delete_assigned_role_returns_409()
    {
        [$user, $tenant, $membership] = $this->createTenantAndUser();
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role A', 'slug' => 'role-a']);
        $membership->roles()->attach($role->id);

        $this->grantPermission($tenant, $membership, PermissionSlug::ROLES_DELETE->value);

        $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->delete("/roles/{$role->id}");

        $response->assertStatus(409);
        $this->assertNotNull(Role::find($role->id));
    }

    public function test_delete_admin_role_assigned_returns_409()
    {
        [$user, $tenant, $membership] = $this->createTenantAndUser();

        // Simular o create tenant service admin role
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'slug' => 'admin']);
        $permission = \App\Modules\Tenancy\Models\Permission::where('slug', PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value)->first();
        $role->permissions()->attach($permission->id);
        $membership->roles()->attach($role->id);

        // the user has memberships.roles.manage through this role.
        // Grant ROLES_DELETE permission via the same role for testing
        $deletePermission = \App\Modules\Tenancy\Models\Permission::where('slug', PermissionSlug::ROLES_DELETE->value)->first();
        $role->permissions()->attach($deletePermission->id);

        $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->delete("/roles/{$role->id}");

        $response->assertStatus(409);
        $this->assertNotNull(Role::find($role->id));
    }
}
