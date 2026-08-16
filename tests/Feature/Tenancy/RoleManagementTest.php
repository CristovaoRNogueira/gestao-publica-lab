<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionCatalogSeeder::class);
    }

    private function createMemberWithPermissions(array $permissionSlugs): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'is_active' => true]);
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Role',
            'slug' => 'admin-role'
        ]);

        foreach ($permissionSlugs as $slug) {
            $permission = \App\Modules\Tenancy\Models\Permission::where('slug', $slug)->first();
            if ($permission) {
                $role->permissions()->attach($permission->id);
            }
        }
        $membership->roles()->attach($role->id);

        $this->actingAs($user);
        session(['tenant_id' => $tenant->id]);

        return [$user, $tenant, $role];
    }

    public function test_authorized_user_can_create_role()
    {
        [$user, $tenant] = $this->createMemberWithPermissions([PermissionSlug::ROLES_CREATE->value, PermissionSlug::ROLES_VIEW->value]);

        $response = $this->post('/roles', [
            'name' => 'New Role',
            'slug' => 'new-role',
            'description' => 'Role description'
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('roles', [
            'tenant_id' => $tenant->id,
            'name' => 'New Role',
            'slug' => 'new-role'
        ]);
    }

    public function test_unauthorized_user_cannot_create_role()
    {
        [$user, $tenant] = $this->createMemberWithPermissions([PermissionSlug::ROLES_VIEW->value]);

        $response = $this->post('/roles', [
            'name' => 'New Role',
            'slug' => 'new-role'
        ]);

        $response->assertForbidden();
    }

    public function test_slug_immutable_on_update()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermissions([PermissionSlug::ROLES_UPDATE->value, PermissionSlug::ROLES_VIEW->value]);

        $roleToUpdate = Role::create(['tenant_id' => $tenant->id, 'name' => 'Old Name', 'slug' => 'old-slug']);

        $response = $this->put("/roles/{$roleToUpdate->id}", [
            'name' => 'New Name',
            'slug' => 'new-slug-hacked'
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['slug']);

        $roleToUpdate->refresh();
        $this->assertEquals('Old Name', $roleToUpdate->name); // Update failed, name also remains unchanged
        $this->assertEquals('old-slug', $roleToUpdate->slug); // Slug remains unchanged
    }

    public function test_duplicate_slug_inside_tenant_rejected()
    {
        [$user, $tenant] = $this->createMemberWithPermissions([PermissionSlug::ROLES_CREATE->value, PermissionSlug::ROLES_VIEW->value]);

        Role::create(['tenant_id' => $tenant->id, 'name' => 'Existing', 'slug' => 'existing-slug']);

        $response = $this->post('/roles', [
            'name' => 'Another',
            'slug' => 'existing-slug'
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_same_slug_allowed_in_different_tenant()
    {
        [$user, $tenant] = $this->createMemberWithPermissions([PermissionSlug::ROLES_CREATE->value, PermissionSlug::ROLES_VIEW->value]);

        $otherTenant = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'is_active' => true]);
        Role::create(['tenant_id' => $otherTenant->id, 'name' => 'Existing', 'slug' => 'existing-slug']);

        $response = $this->post('/roles', [
            'name' => 'Another',
            'slug' => 'existing-slug'
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('roles', [
            'tenant_id' => $tenant->id,
            'slug' => 'existing-slug'
        ]);
    }

    public function test_role_cannot_cross_tenant()
    {
        [$user, $tenant] = $this->createMemberWithPermissions([PermissionSlug::ROLES_UPDATE->value, PermissionSlug::ROLES_VIEW->value, PermissionSlug::ROLES_DELETE->value]);

        $otherTenant = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'is_active' => true]);
        $otherRole = Role::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'slug' => 'other']);

        $this->get("/roles/{$otherRole->id}")->assertNotFound();
        $this->put("/roles/{$otherRole->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->delete("/roles/{$otherRole->id}")->assertNotFound();
    }

    public function test_role_cannot_delete_while_memberships_exist()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermissions([PermissionSlug::ROLES_DELETE->value]);

        $response = $this->delete("/roles/{$role->id}");
        $response->assertStatus(409); // From CannotDeleteRoleInUseException
    }

    public function test_role_cannot_delete_while_pending_invitation_exists()
    {
        [$user, $tenant] = $this->createMemberWithPermissions([PermissionSlug::ROLES_DELETE->value, PermissionSlug::ROLES_VIEW->value]);

        $roleToDelete = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role Delete', 'slug' => 'role-delete']);

        TenantInvitation::create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'role_id' => $roleToDelete->id,
            'token_hash' => 'hash',
            'status' => 'pending',
            'invited_by_user_id' => $user->id,
            'expires_at' => now()->addDays(1),
        ]);

        $response = $this->delete("/roles/{$roleToDelete->id}");
        $response->assertStatus(409);
        $this->assertDatabaseHas('roles', ['id' => $roleToDelete->id]);
    }

    public function test_role_can_delete_after_memberships_invitations_removed()
    {
        [$user, $tenant] = $this->createMemberWithPermissions([PermissionSlug::ROLES_DELETE->value, PermissionSlug::ROLES_VIEW->value]);

        $roleToDelete = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role Delete', 'slug' => 'role-delete']);

        $response = $this->delete("/roles/{$roleToDelete->id}");
        $response->assertRedirect();
        $this->assertDatabaseMissing('roles', ['id' => $roleToDelete->id]);
    }

    public function test_role_index_avoids_n_plus_one()
    {
        [$user, $tenant] = $this->createMemberWithPermissions([PermissionSlug::ROLES_VIEW->value]);

        for ($i = 0; $i < 5; $i++) {
            Role::create(['tenant_id' => $tenant->id, 'name' => "Role $i", 'slug' => "role-$i"]);
        }

        // We run it once to warm up any caches/sessions
        $this->get('/roles')->assertOk();

        $queryCount = 0;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$queryCount) {
            $queryCount++;
        });

        $this->get('/roles')->assertOk();

        $this->assertLessThan(15, $queryCount);
    }

    public function test_role_show_avoids_n_plus_one()
    {
        [$user, $tenant] = $this->createMemberWithPermissions([PermissionSlug::ROLES_VIEW->value]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Role N1', 'slug' => 'role-n1']);

        // Base case
        $this->get("/roles/{$role->id}")->assertOk();

        $queryCountBase = 0;
        \Illuminate\Support\Facades\DB::listen(function () use (&$queryCountBase) {
            $queryCountBase++;
        });

        $this->get("/roles/{$role->id}")->assertOk();

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::forgetRecordModificationState();
        \Illuminate\Support\Facades\Event::forget('Illuminate\Database\Events\QueryExecuted');

        // Add 10 permissions and 10 memberships
        for ($i = 0; $i < 10; $i++) {
            $perm = \App\Modules\Tenancy\Models\Permission::create(['slug' => "perm-{$i}", 'name' => "Perm {$i}"]);
            $role->permissions()->attach($perm->id);

            $u = User::factory()->create();
            $m = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $u->id]);
            $m->roles()->attach($role->id);
        }

        // Run after loading data
        $this->get("/roles/{$role->id}")->assertOk();

        $queryCountLoaded = 0;
        \Illuminate\Support\Facades\DB::listen(function () use (&$queryCountLoaded) {
            $queryCountLoaded++;
        });

        $this->get("/roles/{$role->id}")->assertOk();

        // The number of queries should be the same regardless of members and permissions count (because of eager loading)
        // Might be slightly larger by 1 or 2 if some cache misses happen, but should absolutely not scale with N
        $this->assertLessThanOrEqual($queryCountBase + 2, $queryCountLoaded);
    }
}
