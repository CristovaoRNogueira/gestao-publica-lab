<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacStructuralTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_is_tenant_owned_and_enforces_unique_slug_per_tenant()
    {
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);

        // Create first role
        $role1 = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'slug' => 'admin',
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role1->id,
            'tenant_id' => $tenant->id,
            'slug' => 'admin',
        ]);

        // Attempt to create second role with same slug in same tenant -> Should throw exception
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/UNIQUE constraint failed|unique constraint/');

        Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Clone',
            'slug' => 'admin',
        ]);
    }

    public function test_roles_with_same_slug_allowed_in_different_tenants()
    {
        $tenant1 = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'T2', 'slug' => 't2', 'is_active' => true]);

        Role::create(['tenant_id' => $tenant1->id, 'name' => 'Admin', 'slug' => 'admin']);
        Role::create(['tenant_id' => $tenant2->id, 'name' => 'Admin', 'slug' => 'admin']);

        $this->assertDatabaseCount('roles', 2);
    }

    public function test_permission_is_global_and_enforces_unique_slug()
    {
        Permission::create(['name' => 'View Sec', 'slug' => 'sec.view']);

        $this->assertDatabaseHas('permissions', ['slug' => 'sec.view']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/UNIQUE constraint failed|unique constraint/');

        Permission::create(['name' => 'View Sec Clone', 'slug' => 'sec.view']);
    }

    public function test_role_permission_many_to_many_relation()
    {
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Manager', 'slug' => 'manager']);
        $permission = Permission::create(['name' => 'Edit', 'slug' => 'edit']);

        $role->permissions()->attach($permission->id);

        $this->assertDatabaseHas('role_permission', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);

        $this->assertTrue($role->permissions->contains($permission));
        $this->assertTrue($permission->roles->contains($role));
    }

    public function test_membership_role_many_to_many_relation()
    {
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Staff', 'slug' => 'staff']);

        $membership->roles()->attach($role->id);

        $this->assertDatabaseHas('membership_role', [
            'membership_id' => $membership->id,
            'role_id' => $role->id,
        ]);

        $this->assertTrue($membership->roles->contains($role));
        $this->assertTrue($role->memberships->contains($membership));
    }
}
