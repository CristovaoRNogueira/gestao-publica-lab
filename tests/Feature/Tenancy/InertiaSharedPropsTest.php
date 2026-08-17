<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Tenancy\Models\Tenant;
use App\Models\User;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Services\RoleAssignmentService;
use App\Modules\Tenancy\Services\RolePermissionService;
use App\Modules\Tenancy\Services\RoleService;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaSharedPropsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogSeeder::class);
    }

    public function test_shared_props_include_capabilities_for_active_tenant()
    {
        $user = User::factory()->create();
        $tenant = Tenant::create([
            'name' => 'Tenant Test',
            'slug' => 'tenant-test',
        ]);

        $membership = $tenant->memberships()->create([
            'user_id' => $user->id,
            'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE,
        ]);

        $role = $tenant->roles()->create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
        $role->permissions()->attach(
            \App\Modules\Tenancy\Models\Permission::where('slug', PermissionSlug::ROLES_VIEW->value)->firstOrFail()
        );

        $membership->roles()->attach($role);


        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('auth.user')
                ->has('auth.tenant')
                ->has('auth.tenants')
                ->has('auth.capabilities')
                ->where('auth.capabilities.0', 'roles.view')
            );

        // Prove that capabilities extraction doesn't trigger N+1
        $queryCount = 0;
        \Illuminate\Support\Facades\DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        // The actual extraction is done via $resolved->membership->roles->flatMap->permissions...
        // Let's resolve the tenant manually to simulate what middleware does after the DB is loaded.
        $resolver = app(\App\Modules\Tenancy\Services\TenantResolver::class);
        $resolved = $resolver->resolve($tenant->id, $user);

        $queriesBeforeExtraction = $queryCount;
        $capabilities = $resolved->membership->roles
            ->flatMap->permissions
            ->pluck('slug')
            ->unique()
            ->values()
            ->toArray();
        $queriesAfterExtraction = $queryCount;

        $this->assertEquals($queriesBeforeExtraction, $queriesAfterExtraction);
        $this->assertContains('roles.view', $capabilities);
    }

    public function test_shared_props_flash_messages()
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'Active', 'slug' => 'active']);
        $user->memberships()->create(['tenant_id' => $tenant->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->actingAs($user)
            ->withSession([
                'success' => 'Test success',
                'error' => 'Test error',
            ])
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.success', 'Test success')
                ->where('flash.error', 'Test error')
            );
    }
}
