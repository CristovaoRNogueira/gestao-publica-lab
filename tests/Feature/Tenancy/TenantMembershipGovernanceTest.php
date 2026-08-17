<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Enums\PermissionSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Modules\Tenancy\Exceptions\CannotRemoveLastAdminException;
use Illuminate\Validation\ValidationException;

class TenantMembershipGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionCatalogSeeder::class);
    }

    private function createTenantUserWithPermissions(Tenant $tenant, array $permissions): User
    {
        $user = User::factory()->create();
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Custom Role', 'slug' => 'custom-role-'.uniqid()]);

        $permissionIds = \App\Modules\Tenancy\Models\Permission::whereIn('slug', $permissions)->pluck('id');
        $role->permissions()->sync($permissionIds);

        $membership->roles()->sync([$role->id]);

        return $user;
    }

    public function test_user_with_invitations_manage_cannot_invite_admin_role()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't1', 'is_active' => true]);
        $inviter = $this->createTenantUserWithPermissions($tenant, [PermissionSlug::INVITATIONS_MANAGE->value]);

        // Admin role requiring MEMBERSHIPS_ROLES_MANAGE
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin-1', 'tenant_id' => $tenant->id]);
        $adminRole->permissions()->attach(\App\Modules\Tenancy\Models\Permission::where('slug', PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value)->first()->id);

        $this->actingAs($inviter);
        session(['tenant_id' => $tenant->id]);

        $response = $this->post('/invitations', [
            'email' => 'new@example.com',
            'role_id' => $adminRole->id,
        ]);

        $response->assertSessionHasErrors(['role_id' => 'Você não tem permissão para convidar administradores.']);
    }

    public function test_user_with_invitations_manage_and_roles_manage_can_invite_admin_role()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't2', 'is_active' => true]);
        $inviter = $this->createTenantUserWithPermissions($tenant, [
            PermissionSlug::INVITATIONS_MANAGE->value,
            PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value,
        ]);

        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin-2', 'tenant_id' => $tenant->id]);
        $adminRole->permissions()->attach(\App\Modules\Tenancy\Models\Permission::where('slug', PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value)->first()->id);

        $this->actingAs($inviter);
        session(['tenant_id' => $tenant->id]);

        $this->post('/invitations', [
            'email' => 'new@example.com',
            'role_id' => $adminRole->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('tenant_invitations', ['email' => 'new@example.com']);
    }

    public function test_common_role_invitation_remains_allowed()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't3', 'is_active' => true]);
        $inviter = $this->createTenantUserWithPermissions($tenant, [PermissionSlug::INVITATIONS_MANAGE->value]);

        $commonRole = Role::create(['name' => 'Common', 'slug' => 'common-1', 'tenant_id' => $tenant->id]);

        $this->actingAs($inviter);
        session(['tenant_id' => $tenant->id]);

        $this->post('/invitations', [
            'email' => 'new2@example.com',
            'role_id' => $commonRole->id,
        ])->assertRedirect();
    }

    public function test_membership_manage_required_for_deactivation()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't4', 'is_active' => true]);
        $inviter = $this->createTenantUserWithPermissions($tenant, [PermissionSlug::INVITATIONS_MANAGE->value]);
        $target = $this->createTenantUserWithPermissions($tenant, []);
        $targetMembership = Membership::where('user_id', $target->id)->first();

        $this->actingAs($inviter);
        session(['tenant_id' => $tenant->id]);

        $this->patch("/memberships/{$targetMembership->id}/deactivate")->assertForbidden();
    }

    public function test_authorized_admin_can_deactivate_ordinary_member()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't5', 'is_active' => true]);
        $admin = $this->createTenantUserWithPermissions($tenant, [PermissionSlug::MEMBERSHIPS_MANAGE->value]);
        $target = $this->createTenantUserWithPermissions($tenant, []);
        $targetMembership = Membership::where('user_id', $target->id)->first();

        $this->actingAs($admin);
        session(['tenant_id' => $tenant->id]);

        $this->patch("/memberships/{$targetMembership->id}/deactivate")->assertRedirect();

        $targetMembership->refresh();
        $this->assertEquals(\App\Modules\Tenancy\Models\Membership::STATUS_INACTIVE, $targetMembership->status);
    }

    public function test_authorized_admin_can_reactivate_member()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't6', 'is_active' => true]);
        $admin = $this->createTenantUserWithPermissions($tenant, [PermissionSlug::MEMBERSHIPS_MANAGE->value]);
        $target = $this->createTenantUserWithPermissions($tenant, []);
        $targetMembership = Membership::where('user_id', $target->id)->first();
        $targetMembership->update(['status' => \App\Modules\Tenancy\Models\Membership::STATUS_INACTIVE]);

        $this->actingAs($admin);
        session(['tenant_id' => $tenant->id]);

        $this->patch("/memberships/{$targetMembership->id}/activate")->assertRedirect();

        $targetMembership->refresh();
        $this->assertEquals(\App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE, $targetMembership->status);
    }

    public function test_cannot_deactivate_self()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't7', 'is_active' => true]);
        $admin = $this->createTenantUserWithPermissions($tenant, [PermissionSlug::MEMBERSHIPS_MANAGE->value]);
        $membership = Membership::where('user_id', $admin->id)->first();

        $this->actingAs($admin);
        session(['tenant_id' => $tenant->id]);

        $this->patch("/memberships/{$membership->id}/deactivate")->assertForbidden();
    }

    public function test_cannot_change_own_role()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't8', 'is_active' => true]);
        $admin = $this->createTenantUserWithPermissions($tenant, [PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value]);
        $membership = Membership::where('user_id', $admin->id)->first();
        $newRole = Role::create(['name' => 'New', 'slug' => 'new-role', 'tenant_id' => $tenant->id]);

        $this->actingAs($admin);
        session(['tenant_id' => $tenant->id]);

        $this->post("/memberships/{$membership->id}/roles", [
            'role_id' => $newRole->id
        ])->assertForbidden();

        $roleToRemove = current($membership->roles()->pluck('roles.id')->toArray());
        $this->delete("/memberships/{$membership->id}/roles/{$roleToRemove}")->assertForbidden();
    }

    public function test_cannot_deactivate_last_active_admin()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't9', 'is_active' => true]);
        $admin1 = $this->createTenantUserWithPermissions($tenant, [
            PermissionSlug::MEMBERSHIPS_MANAGE->value,
            PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value
        ]);
        // To deactivate someone else, we need another admin. But we want to deactivate the last admin.
        // So admin1 tries to deactivate admin2, but admin2 is the only one with MEMBERSHIPS_ROLES_MANAGE?
        // No, if admin1 has it, then admin1 is ALSO an admin.
        // Let's create an external agent or another admin who tries to deactivate the last admin.
        $admin2 = $this->createTenantUserWithPermissions($tenant, [
            PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value
        ]);

        $admin1Membership = Membership::where('user_id', $admin1->id)->first();
        // Remove admin1's MEMBERSHIPS_ROLES_MANAGE so admin2 is the LAST admin.
        $admin1Role = $admin1Membership->roles->first();
        $admin1Role->permissions()->detach(\App\Modules\Tenancy\Models\Permission::where('slug', PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value)->first()->id);

        $this->actingAs($admin1);
        session(['tenant_id' => $tenant->id]);

        $admin2Membership = Membership::where('user_id', $admin2->id)->first();

        $response = $this->patch("/memberships/{$admin2Membership->id}/deactivate");
        // Wait, exceptions should be checked.
        $response->assertStatus(409); // Now expects the HTTP 409 conflict pattern

        $admin2Membership->refresh();
        $this->assertEquals(\App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE, $admin2Membership->status);
    }

    public function test_inactive_membership_receives_403_on_tenant_route()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't10', 'is_active' => true]);
        $user = $this->createTenantUserWithPermissions($tenant, []);
        $membership = Membership::where('user_id', $user->id)->first();
        $membership->update(['status' => \App\Modules\Tenancy\Models\Membership::STATUS_INACTIVE]);

        $this->actingAs($user);
        session(['tenant_id' => $tenant->id]);

        $this->get('/roles')->assertForbidden();
    }

    public function test_can_deactivate_admin_when_another_active_admin_exists()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't11', 'is_active' => true]);

        $adminA = $this->createTenantUserWithPermissions($tenant, [
            PermissionSlug::MEMBERSHIPS_MANAGE->value,
            PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value
        ]);
        $adminB = $this->createTenantUserWithPermissions($tenant, [
            PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value
        ]);

        $adminBMembership = Membership::where('user_id', $adminB->id)->first();

        $this->actingAs($adminA);
        session(['tenant_id' => $tenant->id]);

        $response = $this->patch("/memberships/{$adminBMembership->id}/deactivate");

        $response->assertRedirect();

        $adminBMembership->refresh();
        $this->assertEquals(\App\Modules\Tenancy\Models\Membership::STATUS_INACTIVE, $adminBMembership->status);

        $adminAMembership = Membership::where('user_id', $adminA->id)->first();
        $this->assertEquals(\App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE, $adminAMembership->status);

        $this->assertTrue(
            Membership::where('tenant_id', $tenant->id)
                ->where('status', \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE)
                ->whereHas('roles.permissions', fn($q) => $q->where('slug', PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value))
                ->count() >= 1
        );
    }

    public function test_reactivated_membership_regains_tenant_access()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't12', 'is_active' => true]);
        $user = $this->createTenantUserWithPermissions($tenant, [
            PermissionSlug::ROLES_VIEW->value
        ]);
        $userMembership = Membership::where('user_id', $user->id)->first();
        $userMembership->update(['status' => \App\Modules\Tenancy\Models\Membership::STATUS_INACTIVE]);

        $this->actingAs($user);
        session(['tenant_id' => $tenant->id]);
        $this->get('/roles')->assertForbidden();

        $admin = $this->createTenantUserWithPermissions($tenant, [PermissionSlug::MEMBERSHIPS_MANAGE->value]);
        $this->actingAs($admin);
        session(['tenant_id' => $tenant->id]);

        $this->patch("/memberships/{$userMembership->id}/activate")->assertRedirect();

        $this->actingAs($user);
        session(['tenant_id' => $tenant->id]);

        $this->get('/roles')->assertStatus(200);
    }
}
