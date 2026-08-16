<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Platform\Enums\PlatformPermissionSlug;
use App\Modules\Platform\Models\PlatformPermission;
use App\Modules\Platform\Models\PlatformRole;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformTenantAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function createPlatformAdmin(array $permissions = []): User
    {
        $user = User::factory()->create();
        $role = PlatformRole::create(['name' => 'Admin', 'slug' => 'admin']);

        foreach ($permissions as $slug) {
            $permission = PlatformPermission::firstOrCreate(['slug' => $slug, 'name' => $slug]);
            $role->permissions()->attach($permission->id);
        }

        $user->platformRoles()->attach($role->id);
        return $user;
    }

    public function test_guest_cannot_access()
    {
        $this->get('/platform/tenants')->assertRedirect('/login');
    }

    public function test_tenant_admin_without_platform_permission_cannot_access()
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true]);

        $this->actingAs($user)->get('/platform/tenants')->assertStatus(403);
    }

    public function test_platform_admin_without_tenant_membership_can_access()
    {
        $admin = $this->createPlatformAdmin([PlatformPermissionSlug::TENANTS_VIEW->value]);

        $this->actingAs($admin)->get('/platform/tenants')->assertStatus(200);
    }

    public function test_platform_admin_views_listing()
    {
        $admin = $this->createPlatformAdmin([PlatformPermissionSlug::TENANTS_VIEW->value]);
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);

        $response = $this->actingAs($admin)->get('/platform/tenants');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Tenant/Index')
            ->has('tenants', 1)
        );
    }

    public function test_platform_admin_views_specific_tenant()
    {
        $admin = $this->createPlatformAdmin([PlatformPermissionSlug::TENANTS_VIEW->value]);
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);

        $response = $this->actingAs($admin)->get("/platform/tenants/{$tenant->id}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Tenant/Show')
            ->where('tenant.id', $tenant->id)
        );
    }

    public function test_nonexistent_tenant_returns_404()
    {
        $admin = $this->createPlatformAdmin([PlatformPermissionSlug::TENANTS_VIEW->value]);

        $this->actingAs($admin)->get("/platform/tenants/9999")->assertStatus(404);
    }

    public function test_cannot_update_status_without_manage_permission()
    {
        $admin = $this->createPlatformAdmin([PlatformPermissionSlug::TENANTS_VIEW->value]); // Only View
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);

        $this->actingAs($admin)->patch("/platform/tenants/{$tenant->id}/status", [
            'is_active' => false
        ])->assertStatus(403);

        $this->assertTrue($tenant->fresh()->is_active);
    }

    public function test_platform_admin_with_manage_can_update_status()
    {
        $admin = $this->createPlatformAdmin([
            PlatformPermissionSlug::TENANTS_VIEW->value,
            PlatformPermissionSlug::TENANTS_MANAGE->value
        ]);
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);

        $this->actingAs($admin)->patch("/platform/tenants/{$tenant->id}/status", [
            'is_active' => false
        ])->assertRedirect();

        $this->assertFalse($tenant->fresh()->is_active);
    }

    public function test_deactivated_tenant_blocks_tenant_context()
    {
        $admin = $this->createPlatformAdmin([PlatformPermissionSlug::TENANTS_MANAGE->value]);
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true]);

        // Disable tenant
        $this->actingAs($admin)->patch("/platform/tenants/{$tenant->id}/status", [
            'is_active' => false
        ]);

        // Tenant context request fails (ResolveTenant aborts 403)
        $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->get('/secretarias')->assertStatus(403);
    }

    public function test_avoids_n_plus_one()
    {
        $admin = $this->createPlatformAdmin([PlatformPermissionSlug::TENANTS_VIEW->value]);

        $getQueriesForTenants = function (int $count) use ($admin) {
            Tenant::query()->delete();

            for ($i = 0; $i < $count; $i++) {
                Tenant::create(['name' => "T$i", 'slug' => "t$i", 'is_active' => true]);
            }

            DB::enableQueryLog();
            DB::flushQueryLog();

            $this->actingAs($admin)->get('/platform/tenants');

            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        // Warmup
        $getQueriesForTenants(1);

        $queriesWithOne = $getQueriesForTenants(1);
        $queriesWithFive = $getQueriesForTenants(5);
        $queriesWithTwentyFive = $getQueriesForTenants(25);

        $this->assertEquals($queriesWithOne, $queriesWithFive, "Query count grew between 1 and 5 tenants. N+1 detected.");
        $this->assertEquals($queriesWithFive, $queriesWithTwentyFive, "Query count grew between 5 and 25 tenants. N+1 detected.");
    }

    public function test_gates_receive_target_tenant()
    {
        $admin = $this->createPlatformAdmin([
            PlatformPermissionSlug::TENANTS_VIEW->value,
            PlatformPermissionSlug::TENANTS_MANAGE->value
        ]);
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);

        // Intercept Gates to assert that $tenant is passed
        $viewGatePassedTenant = false;
        $manageGatePassedTenant = false;

        \Illuminate\Support\Facades\Gate::define('platform.tenants.view', function ($user, $targetTenant = null) use (&$viewGatePassedTenant) {
            if ($targetTenant instanceof Tenant) {
                $viewGatePassedTenant = true;
            }
            return true;
        });

        \Illuminate\Support\Facades\Gate::define('platform.tenants.manage', function ($user, $targetTenant = null) use (&$manageGatePassedTenant) {
            if ($targetTenant instanceof Tenant) {
                $manageGatePassedTenant = true;
            }
            return true;
        });

        // Test index (does not pass tenant)
        $this->actingAs($admin)->get('/platform/tenants');
        $this->assertFalse($viewGatePassedTenant, 'Index should not pass a target tenant.');

        // Test show (passes tenant)
        $this->actingAs($admin)->get("/platform/tenants/{$tenant->id}");
        $this->assertTrue($viewGatePassedTenant, 'Show must pass the target tenant to the Gate.');

        // Test updateStatus (passes tenant)
        $this->actingAs($admin)->patch("/platform/tenants/{$tenant->id}/status", ['is_active' => false]);
        $this->assertTrue($manageGatePassedTenant, 'UpdateStatus must pass the target tenant to the Gate.');
    }
}
