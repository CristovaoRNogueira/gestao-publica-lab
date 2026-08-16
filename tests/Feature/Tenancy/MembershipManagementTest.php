<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MembershipManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveTenant(string $slug = 'tenant-a'): Tenant
    {
        return Tenant::create(['name' => 'T', 'slug' => $slug, 'is_active' => true]);
    }

    private function grantPermission(Tenant $tenant, Membership $membership, string $permissionSlug): Role
    {
        $role = Role::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Admin', 'slug' => 'admin']);
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

    // -------------------------------------------------------------------------
    // Index (GET /memberships)
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_memberships_index(): void
    {
        $response = $this->get('/memberships');
        $response->assertRedirect('/login');
    }

    public function test_member_without_permission_cannot_access_memberships_index(): void
    {
        $tenant = $this->createActiveTenant();
        $user = User::factory()->create();
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->get('/memberships');

        $response->assertStatus(403);
    }

    public function test_member_with_permission_can_access_index_and_sees_only_tenant_memberships(): void
    {
        $tenantA = $this->createActiveTenant('tenant-a');
        $tenantB = $this->createActiveTenant('tenant-b');

        $userA = User::factory()->create();
        $membershipA = Membership::create(['tenant_id' => $tenantA->id, 'user_id' => $userA->id, 'is_active' => true]);
        $this->grantPermission($tenantA, $membershipA, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

        $userB = User::factory()->create();
        Membership::create(['tenant_id' => $tenantA->id, 'user_id' => $userB->id, 'is_active' => true]);

        $userC = User::factory()->create();
        Membership::create(['tenant_id' => $tenantB->id, 'user_id' => $userC->id, 'is_active' => true]);

        $response = $this->actingAs($userA)
            ->withSession(['tenant_id' => $tenantA->id])
            ->get('/memberships');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Membership/Index')
            ->has('memberships', 2)
        );
    }

    // -------------------------------------------------------------------------
    // Edit (GET /memberships/{membership}/edit)
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_memberships_edit(): void
    {
        $tenant = $this->createActiveTenant();
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $response = $this->get("/memberships/{$membership->id}/edit");
        $response->assertRedirect('/login');
    }

    public function test_member_without_permission_cannot_access_memberships_edit(): void
    {
        $tenant = $this->createActiveTenant();
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->get("/memberships/{$membership->id}/edit");

        $response->assertStatus(403);
    }

    public function test_member_with_permission_can_access_edit_with_tenant_available_roles(): void
    {
        $tenantA = $this->createActiveTenant('tenant-a');
        $tenantB = $this->createActiveTenant('tenant-b');

        $userA = User::factory()->create();
        $membershipA = Membership::create(['tenant_id' => $tenantA->id, 'user_id' => $userA->id, 'is_active' => true]);
        $this->grantPermission($tenantA, $membershipA, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

        $userB = User::factory()->create();
        $membershipB = Membership::create(['tenant_id' => $tenantA->id, 'user_id' => $userB->id, 'is_active' => true]);

        Role::create(['tenant_id' => $tenantA->id, 'name' => 'Role A', 'slug' => 'role-a']);
        Role::create(['tenant_id' => $tenantA->id, 'name' => 'Role B', 'slug' => 'role-b']);
        Role::create(['tenant_id' => $tenantB->id, 'name' => 'Role C', 'slug' => 'role-c']);

        $response = $this->actingAs($userA)
            ->withSession(['tenant_id' => $tenantA->id])
            ->get("/memberships/{$membershipB->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Membership/Edit')
            ->where('membership.id', $membershipB->id)
            ->has('availableRoles', 3) // +1 da Role do grantPermission
        );
    }

    public function test_edit_cross_tenant_blocks_access(): void
    {
        $tenantA = $this->createActiveTenant('tenant-a');
        $tenantB = $this->createActiveTenant('tenant-b');

        $userA = User::factory()->create();
        $membershipA = Membership::create(['tenant_id' => $tenantA->id, 'user_id' => $userA->id, 'is_active' => true]);
        $this->grantPermission($tenantA, $membershipA, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

        $userB = User::factory()->create();
        $membershipB = Membership::create(['tenant_id' => $tenantB->id, 'user_id' => $userB->id, 'is_active' => true]);

        $response = $this->actingAs($userA)
            ->withSession(['tenant_id' => $tenantA->id])
            ->get("/memberships/{$membershipB->id}/edit");

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Inertia Contract (Assign / Revoke)
    // -------------------------------------------------------------------------

    public function test_assign_role_with_inertia_returns_redirect_and_success_flash(): void
    {
        $tenant = $this->createActiveTenant();
        $userA = User::factory()->create();
        $membershipA = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $userA->id, 'is_active' => true]);
        $this->grantPermission($tenant, $membershipA, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

        $userB = User::factory()->create();
        $membershipB = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $userB->id, 'is_active' => true]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'New Role', 'slug' => 'new-role']);

        $response = $this->actingAs($userA)
            ->withSession(['tenant_id' => $tenant->id])
            ->post("/memberships/{$membershipB->id}/roles", ['role_id' => $role->id], ['X-Inertia' => 'true']);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Papel atribuído com sucesso.');
    }

    public function test_assign_role_to_inactive_membership_with_inertia_returns_validation_error(): void
    {
        $tenant = $this->createActiveTenant();
        $userA = User::factory()->create();
        $membershipA = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $userA->id, 'is_active' => true]);
        $this->grantPermission($tenant, $membershipA, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

        $userB = User::factory()->create();
        $membershipB = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $userB->id, 'is_active' => false]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'New Role', 'slug' => 'new-role']);

        $response = $this->actingAs($userA)
            ->withSession(['tenant_id' => $tenant->id])
            ->post("/memberships/{$membershipB->id}/roles", ['role_id' => $role->id], ['X-Inertia' => 'true']);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['role_id' => 'Não é possível atribuir papéis a uma associação inativa.']);
    }

    public function test_revoke_role_with_inertia_returns_redirect_and_success_flash(): void
    {
        $tenant = $this->createActiveTenant();
        $userA = User::factory()->create();
        $membershipA = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $userA->id, 'is_active' => true]);
        $this->grantPermission($tenant, $membershipA, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

        $userB = User::factory()->create();
        $membershipB = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $userB->id, 'is_active' => true]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'New Role', 'slug' => 'new-role']);
        $membershipB->roles()->attach($role);

        $response = $this->actingAs($userA)
            ->withSession(['tenant_id' => $tenant->id])
            ->delete("/memberships/{$membershipB->id}/roles/{$role->id}", [], ['X-Inertia' => 'true']);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Papel removido com sucesso.');
    }

    public function test_revoke_last_admin_with_inertia_returns_redirect_and_error_flash(): void
    {
        $tenant = $this->createActiveTenant();
        $userA = User::factory()->create();
        $membershipA = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $userA->id, 'is_active' => true]);
        $adminRole = $this->grantPermission($tenant, $membershipA, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);

        $response = $this->actingAs($userA)
            ->withSession(['tenant_id' => $tenant->id])
            ->delete("/memberships/{$membershipA->id}/roles/{$adminRole->id}", [], ['X-Inertia' => 'true']);

        $response->assertForbidden();
    }
}
