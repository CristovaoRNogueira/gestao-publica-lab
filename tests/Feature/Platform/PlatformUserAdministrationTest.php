<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Platform\Models\PlatformRole;
use App\Modules\Platform\Models\PlatformPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformUserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformPermissionCatalogSeeder::class);
        $this->seed(\Database\Seeders\PermissionCatalogSeeder::class);
    }

    private function createPlatformAdmin(array $permissions = []): User
    {
        $admin = User::factory()->create();
        $role = PlatformRole::create(['name' => 'Admin Test', 'slug' => 'admin-test']);

        $permissionIds = PlatformPermission::whereIn('slug', $permissions)->pluck('id');
        $role->permissions()->sync($permissionIds);

        $admin->platformRoles()->sync([$role->id]);

        return $admin;
    }

    private function createTenantAdmin(): User
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'slug' => 'admin']);
        $membership->roles()->sync([$role->id]);

        return $user;
    }

    public function test_guest_cannot_access()
    {
        $this->get('/platform/users')->assertRedirect('/login');
        $this->get('/platform/users/1')->assertRedirect('/login');
        $this->patch('/platform/memberships/1/status')->assertRedirect('/login');
    }

    public function test_tenant_admin_without_platform_permission_cannot_access()
    {
        $tenantAdmin = $this->createTenantAdmin();
        $this->actingAs($tenantAdmin);

        $this->get('/platform/users')->assertForbidden();
        $this->get('/platform/users/' . $tenantAdmin->id)->assertForbidden();
    }

    public function test_platform_admin_without_membership_can_access()
    {
        $platformAdmin = $this->createPlatformAdmin(['platform.access', 'users.view']);
        $this->actingAs($platformAdmin);

        $this->get('/platform/users')->assertOk();
    }

    public function test_platform_admin_views_user_listing()
    {
        $platformAdmin = $this->createPlatformAdmin(['platform.access', 'users.view']);
        $this->actingAs($platformAdmin);
        User::factory()->count(3)->create();

        $response = $this->get('/platform/users');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/User/Index')
            ->has('users.data', 4) // admin + 3
        );
    }

    public function test_platform_admin_views_specific_user()
    {
        $platformAdmin = $this->createPlatformAdmin(['platform.access', 'users.view']);
        $this->actingAs($platformAdmin);

        $targetUser = User::factory()->create();

        $response = $this->get('/platform/users/' . $targetUser->id);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/User/Show')
            ->where('user.id', $targetUser->id)
        );
    }

    public function test_nonexistent_user_returns_404()
    {
        $platformAdmin = $this->createPlatformAdmin(['platform.access', 'users.view']);
        $this->actingAs($platformAdmin);

        $this->get('/platform/users/999999')->assertNotFound();
    }

    public function test_user_memberships_are_displayed()
    {
        $platformAdmin = $this->createPlatformAdmin(['platform.access', 'users.view']);
        $this->actingAs($platformAdmin);

        $targetUser = User::factory()->create();
        $tenant1 = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'T2', 'slug' => 't2', 'is_active' => true]);

        Membership::create(['user_id' => $targetUser->id, 'tenant_id' => $tenant1->id, 'is_active' => true]);
        Membership::create(['user_id' => $targetUser->id, 'tenant_id' => $tenant2->id, 'is_active' => false]);

        $response = $this->get('/platform/users/' . $targetUser->id);
        $response->assertInertia(fn ($page) => $page
            ->has('user.memberships', 2)
        );
    }

    public function test_membership_roles_are_displayed()
    {
        $platformAdmin = $this->createPlatformAdmin(['platform.access', 'users.view']);
        $this->actingAs($platformAdmin);

        $targetUser = User::factory()->create();
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);

        $membership = Membership::create(['user_id' => $targetUser->id, 'tenant_id' => $tenant->id, 'is_active' => true]);
        $role1 = Role::create(['tenant_id' => $tenant->id, 'name' => 'R1', 'slug' => 'r1']);
        $role2 = Role::create(['tenant_id' => $tenant->id, 'name' => 'R2', 'slug' => 'r2']);

        $membership->roles()->sync([$role1->id, $role2->id]);

        $response = $this->get('/platform/users/' . $targetUser->id);
        $response->assertInertia(fn ($page) => $page
            ->has('user.memberships.0.roles', 2)
        );
    }

    public function test_cannot_update_membership_without_manage_permission()
    {
        $platformAdmin = $this->createPlatformAdmin(['platform.access', 'users.view']); // no users.manage
        $this->actingAs($platformAdmin);

        $targetUser = User::factory()->create();
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $membership = Membership::create(['user_id' => $targetUser->id, 'tenant_id' => $tenant->id, 'is_active' => true]);

        $this->patch('/platform/memberships/' . $membership->id . '/status', ['is_active' => false])
            ->assertForbidden();

        $this->assertTrue($membership->fresh()->is_active);
    }

    public function test_platform_admin_can_update_membership_status()
    {
        $platformAdmin = $this->createPlatformAdmin(['platform.access', 'users.view', 'users.manage']);
        $this->actingAs($platformAdmin);

        $targetUser = User::factory()->create();
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $membership = Membership::create(['user_id' => $targetUser->id, 'tenant_id' => $tenant->id, 'is_active' => true]);

        $this->patch('/platform/memberships/' . $membership->id . '/status', ['is_active' => false])
            ->assertRedirect();

        $this->assertFalse($membership->fresh()->is_active);
    }

    public function test_deactivated_membership_blocks_real_tenant_access()
    {
        // 1. Desativar Membership via endpoint Platform
        $platformAdmin = $this->createPlatformAdmin(['platform.access', 'users.view', 'users.manage']);

        $targetUser = User::factory()->create();
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $membership = Membership::create(['user_id' => $targetUser->id, 'tenant_id' => $tenant->id, 'is_active' => true]);
        // Target User has memberships.roles.manage
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'slug' => 'admin']);
        $permission = \App\Modules\Tenancy\Models\Permission::where('slug', 'memberships.roles.manage')->first();
        $role->permissions()->sync([$permission->id]);
        $membership->roles()->sync([$role->id]);

        $this->actingAs($platformAdmin);
        $this->patch('/platform/memberships/' . $membership->id . '/status', ['is_active' => false]);
        $this->assertFalse($membership->fresh()->is_active);

        // 2. Usar o usuário dessa Membership
        auth()->logout();
        $this->actingAs($targetUser);

        // 3. Configurar tenant_id na sessão
        session(['tenant_id' => $tenant->id]);

        // 4. Acessar uma rota tenant-scoped real (ex: /memberships)
        // O ResolveTenant middleware ou MembershipPolicy bloqueará porque membership está inativa.
        // Actually, ResolveTenant drops the context if membership is inactive.
        $this->get('/memberships')->assertForbidden();
    }

    public function test_platform_admin_remains_globally_authorized()
    {
        $platformAdmin = $this->createPlatformAdmin(['platform.access', 'users.view', 'users.manage']);
        $this->actingAs($platformAdmin);
        $this->get('/platform/users')->assertOk();
    }

    public function test_platform_rbac_does_not_grant_tenant_rbac()
    {
        $platformAdmin = $this->createPlatformAdmin(['platform.access', 'users.view', 'users.manage']);
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);

        $this->actingAs($platformAdmin);
        session(['tenant_id' => $tenant->id]);

        // platform admin doesn't have a membership, so they shouldn't access tenant routes
        $this->get('/memberships')->assertForbidden();
    }

    public function test_user_index_avoids_n_plus_one()
    {
        $platformAdmin = $this->createPlatformAdmin(['platform.access', 'users.view']);
        $this->actingAs($platformAdmin);

        // warmup
        $this->get('/platform/users');

        // N=1
        User::factory()->count(1)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/platform/users');
        $queriesN1 = count(DB::getQueryLog());

        // N=5
        User::factory()->count(4)->create(); // total 5
        DB::flushQueryLog();
        $this->get('/platform/users');
        $queriesN5 = count(DB::getQueryLog());

        // N=15
        User::factory()->count(10)->create(); // total 15
        DB::flushQueryLog();
        $this->get('/platform/users');
        $queriesN15 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertEquals($queriesN1, $queriesN5);
        $this->assertEquals($queriesN5, $queriesN15);
    }

    public function test_user_show_avoids_n_plus_one()
    {
        $platformAdmin = $this->createPlatformAdmin(['platform.access', 'users.view']);
        $this->actingAs($platformAdmin);

        $targetUser = User::factory()->create();

        // warmup
        $this->get('/platform/users/' . $targetUser->id);

        // N=1 membership
        $tenant1 = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $mem1 = Membership::create(['user_id' => $targetUser->id, 'tenant_id' => $tenant1->id, 'is_active' => true]);
        $role1 = Role::create(['tenant_id' => $tenant1->id, 'name' => 'R1', 'slug' => 'r1']);
        $mem1->roles()->sync([$role1->id]);

        DB::enableQueryLog();
        $this->get('/platform/users/' . $targetUser->id);
        $queriesN1 = count(DB::getQueryLog());

        // N=5 memberships
        for ($i=2; $i<=5; $i++) {
            $t = Tenant::create(['name' => 'T'.$i, 'slug' => 't'.$i, 'is_active' => true]);
            $m = Membership::create(['user_id' => $targetUser->id, 'tenant_id' => $t->id, 'is_active' => true]);
            $r = Role::create(['tenant_id' => $t->id, 'name' => 'R'.$i, 'slug' => 'r'.$i]);
            $m->roles()->sync([$r->id]);
        }

        DB::flushQueryLog();
        $this->get('/platform/users/' . $targetUser->id);
        $queriesN5 = count(DB::getQueryLog());

        // N=25 memberships
        for ($i=6; $i<=25; $i++) {
            $t = Tenant::create(['name' => 'T'.$i, 'slug' => 't'.$i, 'is_active' => true]);
            $m = Membership::create(['user_id' => $targetUser->id, 'tenant_id' => $t->id, 'is_active' => true]);
            $r = Role::create(['tenant_id' => $t->id, 'name' => 'R'.$i, 'slug' => 'r'.$i]);
            $m->roles()->sync([$r->id]);
        }

        DB::flushQueryLog();
        $this->get('/platform/users/' . $targetUser->id);
        $queriesN25 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertEquals($queriesN1, $queriesN5);
        $this->assertEquals($queriesN5, $queriesN25);
    }

    public function test_platform_gates_receive_target_user_and_membership()
    {
        $platformAdmin = $this->createPlatformAdmin(['platform.access', 'users.view', 'users.manage']);
        $this->actingAs($platformAdmin);

        $targetUser = User::factory()->create();
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $membership = Membership::create(['user_id' => $targetUser->id, 'tenant_id' => $tenant->id, 'is_active' => true]);

        $gateArguments = [];
        \Illuminate\Support\Facades\Gate::after(function ($user, $ability, $result, $arguments) use (&$gateArguments) {
            $gateArguments[$ability] = $arguments;
        });

        $this->get('/platform/users');
        $this->assertEmpty($gateArguments['platform.users.view'] ?? [], 'Index should not receive a target resource.');

        $this->get('/platform/users/' . $targetUser->id);
        $this->assertInstanceOf(User::class, $gateArguments['platform.users.view'][0] ?? null, 'Show should receive target User.');
        $this->assertEquals($targetUser->id, $gateArguments['platform.users.view'][0]->id);

        $this->patch('/platform/memberships/' . $membership->id . '/status', ['is_active' => false]);
        $this->assertInstanceOf(Membership::class, $gateArguments['platform.users.manage'][0] ?? null, 'Update status should receive target Membership.');
        $this->assertEquals($membership->id, $gateArguments['platform.users.manage'][0]->id);
    }
}
