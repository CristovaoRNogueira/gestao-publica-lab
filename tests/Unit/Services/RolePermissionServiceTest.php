<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Exceptions\CannotRemoveLastEffectivePermissionException;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private RolePermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionCatalogSeeder::class);
        $this->service = new RolePermissionService(app(\App\Modules\Tenancy\Context\TenantContext::class));
    }

    public function test_attach_permission_is_idempotent()
    {
        $tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role', 'slug' => 'role']);
        $permission = Permission::where('slug', PermissionSlug::SECRETARIAS_VIEW->value)->first();

        $this->service->attachPermission($role, $permission);
        $this->assertEquals(1, $role->permissions()->count());

        // Second attach should not fail and should not duplicate
        $this->service->attachPermission($role, $permission);
        $this->assertEquals(1, $role->permissions()->count());
    }

    public function test_detach_permission_removes_it()
    {
        $tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role', 'slug' => 'role']);
        $permission = Permission::where('slug', PermissionSlug::SECRETARIAS_VIEW->value)->first();

        $role->permissions()->attach($permission->id);

        $this->service->detachPermission($role, $permission);
        $this->assertEquals(0, $role->permissions()->count());
    }

    public function test_detach_critical_permission_from_unassigned_role_is_allowed()
    {
        $tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role', 'slug' => 'role']);
        $permission = Permission::where('slug', PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value)->first();

        $role->permissions()->attach($permission->id);

        // Role has no memberships, so it shouldn't trigger lockout exception
        $this->service->detachPermission($role, $permission);
        $this->assertEquals(0, $role->permissions()->count());
    }

    public function test_detach_critical_permission_from_last_assigned_role_throws_exception()
    {
        $tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role', 'slug' => 'role']);
        $permission = Permission::where('slug', PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value)->first();
        $role->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id]);
        $membership->roles()->attach($role->id);

        $this->expectException(CannotRemoveLastEffectivePermissionException::class);
        $this->service->detachPermission($role, $permission);
    }

    public function test_detach_critical_permission_allowed_if_another_role_in_same_membership_has_it()
    {
        $tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $role1 = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role 1', 'slug' => 'role-1']);
        $role2 = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role 2', 'slug' => 'role-2']);

        $permission = Permission::where('slug', PermissionSlug::ROLES_PERMISSIONS_MANAGE->value)->first();
        $role1->permissions()->attach($permission->id);
        $role2->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id]);
        $membership->roles()->attach([$role1->id, $role2->id]);

        // Removing from role 1 is fine because role 2 still provides it to the active membership
        $this->service->detachPermission($role1, $permission);
        $this->assertEquals(0, $role1->permissions()->count());
        $this->assertEquals(1, $role2->permissions()->count());
    }

    public function test_detach_critical_permission_allowed_if_another_active_membership_has_it()
    {
        $tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $role1 = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role 1', 'slug' => 'role-1']);
        $role2 = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role 2', 'slug' => 'role-2']);

        $permission = Permission::where('slug', PermissionSlug::ROLES_PERMISSIONS_MANAGE->value)->first();
        $role1->permissions()->attach($permission->id);
        $role2->permissions()->attach($permission->id);

        $user1 = User::factory()->create();
        $membership1 = Membership::create(['user_id' => $user1->id, 'tenant_id' => $tenant->id]);
        $membership1->roles()->attach($role1->id);

        $user2 = User::factory()->create();
        $membership2 = Membership::create(['user_id' => $user2->id, 'tenant_id' => $tenant->id]);
        $membership2->roles()->attach($role2->id);

        // Removing from role 1 is fine because membership 2 still has it via role 2
        $this->service->detachPermission($role1, $permission);
        $this->assertEquals(0, $role1->permissions()->count());
    }

    public function test_inactive_memberships_do_not_count_for_effective_capacity()
    {
        $tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $role1 = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role 1', 'slug' => 'role-1']);
        $role2 = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role 2', 'slug' => 'role-2']);

        $permission = Permission::where('slug', PermissionSlug::ROLES_PERMISSIONS_MANAGE->value)->first();
        $role1->permissions()->attach($permission->id);
        $role2->permissions()->attach($permission->id);

        $user1 = User::factory()->create();
        $membership1 = Membership::create(['user_id' => $user1->id, 'tenant_id' => $tenant->id, 'is_active' => true]);
        $membership1->roles()->attach($role1->id);

        $user2 = User::factory()->create();
        // Membership 2 is inactive
        $membership2 = Membership::create(['user_id' => $user2->id, 'tenant_id' => $tenant->id, 'is_active' => false]);
        $membership2->roles()->attach($role2->id);

        // Removing from role 1 should fail because membership 2 is inactive and cannot provide the capability
        $this->expectException(CannotRemoveLastEffectivePermissionException::class);
        $this->service->detachPermission($role1, $permission);
    }
}
