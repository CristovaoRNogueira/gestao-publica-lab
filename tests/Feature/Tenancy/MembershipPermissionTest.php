<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Services\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MembershipPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_without_roles_returns_false()
    {
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->assertFalse($membership->hasPermission('view.dashboard'));
    }

    public function test_membership_with_role_without_permission_returns_false()
    {
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'slug' => 'admin']);
        $membership->roles()->attach($role);

        $membership->load('roles.permissions');

        $this->assertFalse($membership->hasPermission('view.dashboard'));
    }

    public function test_membership_with_role_containing_permission_returns_true()
    {
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'slug' => 'admin']);
        $permission = Permission::create(['name' => 'View Dashboard', 'slug' => 'view.dashboard']);

        $role->permissions()->attach($permission);
        $membership->roles()->attach($role);

        $membership->load('roles.permissions');

        $this->assertTrue($membership->hasPermission('view.dashboard'));
    }

    public function test_membership_with_multiple_roles_merges_permissions()
    {
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $roleA = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role A', 'slug' => 'role-a']);
        $roleB = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role B', 'slug' => 'role-b']);

        $permissionX = Permission::create(['name' => 'Perm X', 'slug' => 'perm.x']);
        $permissionY = Permission::create(['name' => 'Perm Y', 'slug' => 'perm.y']);

        $roleA->permissions()->attach($permissionX);
        $roleB->permissions()->attach($permissionY);

        $membership->roles()->attach([$roleA->id, $roleB->id]);

        $membership->load('roles.permissions');

        $this->assertTrue($membership->hasPermission('perm.x'));
        $this->assertTrue($membership->hasPermission('perm.y'));
        $this->assertFalse($membership->hasPermission('perm.z'));
    }

    public function test_membership_of_tenant_a_does_not_use_roles_of_tenant_b()
    {
        $tenantA = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $tenantB = Tenant::create(['name' => 'T2', 'slug' => 't2', 'is_active' => true]);

        $user = User::factory()->create();

        $membershipA = Membership::create(['tenant_id' => $tenantA->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $membershipB = Membership::create(['tenant_id' => $tenantB->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $roleB = Role::create(['tenant_id' => $tenantB->id, 'name' => 'Role B', 'slug' => 'role-b']);
        $permissionX = Permission::create(['name' => 'Perm X', 'slug' => 'perm.x']);
        $roleB->permissions()->attach($permissionX);
        $membershipB->roles()->attach($roleB);

        $membershipA->load('roles.permissions');
        $this->assertFalse($membershipA->hasPermission('perm.x'));
    }

    public function test_tenant_resolver_loads_membership_with_tenant_roles_and_permissions()
    {
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'slug' => 'admin']);
        $permission = Permission::create(['name' => 'View Dashboard', 'slug' => 'view.dashboard']);
        $role->permissions()->attach($permission);
        $membership->roles()->attach($role);

        $resolver = new TenantResolver();
        $resolved = $resolver->resolve($tenant->id, $user);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->membership->relationLoaded('tenant'));
        $this->assertTrue($resolved->membership->relationLoaded('roles'));
        $this->assertTrue($resolved->membership->roles->first()->relationLoaded('permissions'));
    }

    public function test_has_permission_does_not_execute_queries_when_relations_are_loaded()
    {
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'slug' => 'admin']);
        $permission = Permission::create(['name' => 'View Dashboard', 'slug' => 'view.dashboard']);
        $role->permissions()->attach($permission);
        $membership->roles()->attach($role);

        $resolver = new TenantResolver();
        $resolved = $resolver->resolve($tenant->id, $user);

        $resolvedMembership = $resolved->membership;

        // Ensure relations are loaded
        $this->assertTrue($resolvedMembership->relationLoaded('roles'));
        $this->assertTrue($resolvedMembership->roles->first()->relationLoaded('permissions'));

        DB::enableQueryLog();
        DB::flushQueryLog();

        $resolvedMembership->hasPermission('view.dashboard');
        $resolvedMembership->hasPermission('other.permission');
        $resolvedMembership->hasPermission('view.dashboard');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries, 'hasPermission() should not execute any queries when relations are eager loaded.');
    }
}
