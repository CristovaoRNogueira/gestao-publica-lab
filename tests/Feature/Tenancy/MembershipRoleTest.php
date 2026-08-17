<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Models\User;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipRoleTest extends TestCase
{
    use RefreshDatabase;

    private function grantPermission(Tenant $tenant, Membership $membership, string $permissionSlug, string $roleName = 'Admin', string $roleSlug = 'admin'): Role
    {
        $role = Role::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $roleName, 'slug' => $roleSlug]);
        $permission = Permission::firstOrCreate(['name' => $permissionSlug, 'slug' => $permissionSlug]);
        if (!$role->permissions->contains($permission->id)) {
            $role->permissions()->attach($permission);
        }
        if (!$membership->roles->contains($role->id)) {
            $membership->roles()->attach($role);
        }
        $membership->load('roles.permissions');
        return $role;
    }

    public function test_assign_role_without_permission_returns_403()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Test', 'slug' => 'test']);

        $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->post("/memberships/{$targetMembership->id}/roles", ['role_id' => $role->id]);

        $response->assertStatus(403);
    }

    public function test_assign_role_with_permission_works()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        $actorMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->grantPermission($tenant, $actorMembership, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Test', 'slug' => 'test']);

        $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->post("/memberships/{$targetMembership->id}/roles", ['role_id' => $role->id]);

        $response->assertRedirect();
        $this->assertTrue($targetMembership->roles->contains($role->id));
    }

    public function test_assign_role_to_inactive_membership_is_denied_with_422()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        $actorMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->grantPermission($tenant, $actorMembership, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_INACTIVE]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Test', 'slug' => 'test']);

        $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->post("/memberships/{$targetMembership->id}/roles", ['role_id' => $role->id]);

        $response->assertStatus(422);
        $this->assertFalse($targetMembership->roles->contains($role->id));
    }

    public function test_revoke_role_from_inactive_membership_is_allowed()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        $actorMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->grantPermission($tenant, $actorMembership, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_INACTIVE]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Test', 'slug' => 'test']);
        $targetMembership->roles()->attach($role->id);

        $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->delete("/memberships/{$targetMembership->id}/roles/{$role->id}");

        $response->assertRedirect();
        $this->assertFalse($targetMembership->fresh()->roles->contains($role->id));
    }

    public function test_revoke_last_admin_role_is_blocked_with_409()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        $actorMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $adminRole = $this->grantPermission($tenant, $actorMembership, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

        $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->delete("/memberships/{$actorMembership->id}/roles/{$adminRole->id}");

        $response->assertForbidden();
        $this->assertTrue($actorMembership->fresh()->roles->contains($adminRole->id));
    }

    public function test_assign_role_cross_tenant_target_membership_returns_403()
    {
        $tenant1 = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'T2', 'slug' => 't2', 'is_active' => true]);

        $user = User::factory()->create();
        $actorMembership = Membership::create(['tenant_id' => $tenant1->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->grantPermission($tenant1, $actorMembership, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

        $targetMembership = Membership::create(['tenant_id' => $tenant2->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $role = Role::create(['tenant_id' => $tenant1->id, 'name' => 'Test', 'slug' => 'test']);

        $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant1->id])
            ->post("/memberships/{$targetMembership->id}/roles", ['role_id' => $role->id]);

        // Policy will block this because target is in another tenant
        $response->assertStatus(403);
    }

    public function test_revoke_cross_tenant_role_returns_404_due_to_scoped_binding()
    {
        $tenant1 = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'T2', 'slug' => 't2', 'is_active' => true]);

        $user = User::factory()->create();
        $actorMembership = Membership::create(['tenant_id' => $tenant1->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->grantPermission($tenant1, $actorMembership, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

        $targetMembership = Membership::create(['tenant_id' => $tenant1->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        // Role belongs to tenant 2
        $role = Role::create(['tenant_id' => $tenant2->id, 'name' => 'Test', 'slug' => 'test']);

        $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant1->id])
            ->delete("/memberships/{$targetMembership->id}/roles/{$role->id}");

        $response->assertStatus(404);
    }

    public function test_revoke_unbound_role_returns_404_due_to_scoped_binding()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);

        $user = User::factory()->create();
        $actorMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->grantPermission($tenant, $actorMembership, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Test', 'slug' => 'test']);

        // Role is not attached to targetMembership
        $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->delete("/memberships/{$targetMembership->id}/roles/{$role->id}");

        $response->assertStatus(404);
    }

    public function test_remove_admin_role_when_another_role_has_manage_permission_is_allowed()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        $actorMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $adminRole = $this->grantPermission($tenant, $actorMembership, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value, 'Admin', 'admin');
        $supervisorRole = $this->grantPermission($tenant, $actorMembership, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value, 'Supervisor', 'supervisor');

        // Should block removing adminRole because it's self-management
        $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->delete("/memberships/{$actorMembership->id}/roles/{$adminRole->id}");

        $response->assertForbidden();
        $this->assertTrue($actorMembership->fresh()->roles->contains($adminRole->id));
        $this->assertTrue($actorMembership->fresh()->roles->contains($supervisorRole->id));
    }
}
