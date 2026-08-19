<?php

namespace Tests\Unit\Services;

use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Models\User;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Services\CreateTenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTenantServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreateTenantService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionCatalogSeeder::class);
        $this->service = new CreateTenantService();
    }

    public function test_tenant_creation_bootstraps_rbac_for_owner()
    {
        $owner = User::factory()->create();

        $tenant = $this->service->execute($owner, [
            'name' => 'Meu Primeiro Tenant',
            'slug' => 'meu-primeiro-tenant',
        ]);

        // Tenant was created
        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals('Meu Primeiro Tenant', $tenant->name);
        $this->assertTrue($tenant->is_active);

        // Membership was created for owner and is active
        $membership = Membership::where('tenant_id', $tenant->id)
            ->where('user_id', $owner->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertEquals(\App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE, $membership->status);

        // Admin role was created
        $role = Role::where('tenant_id', $tenant->id)
            ->where('slug', 'admin')
            ->first();

        $this->assertNotNull($role);
        $this->assertEquals('Administrador', $role->name);

        // Global permissions exist
        $expectedSlugs = PermissionSlug::defaultAdminSlugs();
        $permissions = Permission::whereIn('slug', $expectedSlugs)->get();
        $this->assertEquals(14, $permissions->count());

        // Role has permissions
        foreach ($permissions as $permission) {
            $this->assertTrue($role->permissions->contains($permission->id));
        }

        // Membership has role
        $this->assertTrue($membership->roles->contains($role->id));

        // Membership effectively has exactly the expected permissions
        $actualSlugs = $membership->roles->flatMap->permissions->pluck('slug')->toArray();
        sort($expectedSlugs);
        sort($actualSlugs);
        $this->assertEquals($expectedSlugs, $actualSlugs);
    }

    public function test_rbac_bootstrap_independent_executions_avoid_contamination()
    {
        $owner1 = User::factory()->create();
        $tenant1 = $this->service->execute($owner1, [
            'name' => 'Tenant 1',
        ]);

        $owner2 = User::factory()->create();
        $tenant2 = $this->service->execute($owner2, [
            'name' => 'Tenant 2',
        ]);

        $role1 = Role::where('tenant_id', $tenant1->id)->where('slug', 'admin')->first();
        $role2 = Role::where('tenant_id', $tenant2->id)->where('slug', 'admin')->first();

        // 1. Cada Tenant possui sua própria Role admin
        $this->assertNotEquals($role1->id, $role2->id);

        $expectedSlugs = PermissionSlug::defaultAdminSlugs();
        $permissions = Permission::whereIn('slug', $expectedSlugs)->get();

        // 2. Não existe cross-tenant contamination nas permissions das Roles
        foreach ($permissions as $permission) {
            $this->assertTrue($role1->permissions->contains($permission->id));
            $this->assertTrue($role2->permissions->contains($permission->id));
        }

        // 3. Somente as Permissions esperadas existem
        foreach ($expectedSlugs as $slug) {
            $this->assertEquals(1, Permission::where('slug', $slug)->count());
        }

        // 4. Cada Membership recebe somente a Role do seu Tenant
        $membership1 = Membership::where('tenant_id', $tenant1->id)->where('user_id', $owner1->id)->first();
        $membership2 = Membership::where('tenant_id', $tenant2->id)->where('user_id', $owner2->id)->first();

        $this->assertTrue($membership1->roles->contains($role1->id));
        $this->assertFalse($membership1->roles->contains($role2->id));

        $this->assertTrue($membership2->roles->contains($role2->id));
        $this->assertFalse($membership2->roles->contains($role1->id));
    }

    public function test_tenant_creation_rollback_on_partial_failure()
    {
        $owner = User::factory()->create();

        $initialTenantCount = Tenant::count();
        $initialMembershipCount = Membership::count();
        $initialRoleCount = Role::count();
        $initialPermissionCount = Permission::count();

        // Hook into the Role created event to throw an exception AFTER
        // Tenant, Membership, and Permission have been created inside the transaction.
        Role::created(function () {
            throw new \Exception('Forced failure after partial bootstrap');
        });

        try {
            $this->service->execute($owner, [
                'name' => 'Should Fail',
                'slug' => 'should-fail',
            ]);
            $this->fail('Expected an exception');
        } catch (\Exception $e) {
            $this->assertEquals('Forced failure after partial bootstrap', $e->getMessage());

            // Rollback expected: Tenant, Membership, Role and Permission should not exist
            $this->assertEquals($initialTenantCount, Tenant::count());
            $this->assertEquals($initialMembershipCount, Membership::count());
            $this->assertEquals($initialRoleCount, Role::count());
            $this->assertEquals($initialPermissionCount, Permission::count());
        }

        // Clean up the event listener to avoid polluting other tests
        $dispatcher = Role::getEventDispatcher();
        $dispatcher->forget('eloquent.created: ' . Role::class);
    }
}
