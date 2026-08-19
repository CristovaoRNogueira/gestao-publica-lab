<?php

namespace Tests\Unit\Policies;

use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Models\User;
use App\Modules\Tenancy\Models\OrganizationUnit;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Policies\OrganizationUnitPolicy;
use App\Modules\Tenancy\Services\OrganizationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationUnitPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;
    private OrganizationUnitPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = app(TenantContext::class);
        $this->policy = new OrganizationUnitPolicy(app(OrganizationScope::class));
    }

    private function grantPermission(Tenant $tenant, Membership $membership, string $permissionSlug): void
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
    }

    public function test_viewAny_returns_true_when_tenant_is_active_and_has_permission(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't1', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->grantPermission($tenant, $membership, PermissionSlug::ORGANIZATION_UNITS_VIEW->value);

        $this->context->set($tenant, $membership);

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_viewAny_returns_false_without_permission(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't1', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->context->set($tenant, $membership);

        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_viewAny_returns_false_without_tenant_context(): void
    {
        $user = User::factory()->create();

        $this->context->set(null, null);

        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_create_returns_true_when_tenant_is_active_and_has_permission(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't3', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->grantPermission($tenant, $membership, PermissionSlug::ORGANIZATION_UNITS_CREATE->value);

        $this->context->set($tenant, $membership);

        $this->assertTrue($this->policy->create($user));
    }

    public function test_create_returns_false_without_permission(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't3', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->context->set($tenant, $membership);

        $this->assertFalse($this->policy->create($user));
    }

    public function test_update_returns_true_for_own_organization_unit_with_permission(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't4', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->grantPermission($tenant, $membership, PermissionSlug::ORGANIZATION_UNITS_UPDATE->value);

        $unit = OrganizationUnit::create(['tenant_id' => $tenant->id, 'name' => 'A', 'slug' => 'a', 'type' => 'Unit']);
        $membership->update(['organization_unit_id' => $unit->id]); // give it a unit

        $this->context->set($tenant, $membership);

        $response = $this->policy->update($user, $unit);
        $this->assertTrue($response === true || (is_object($response) && $response->allowed()));
    }

    public function test_update_returns_false_without_permission(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't4', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $unit = OrganizationUnit::create(['tenant_id' => $tenant->id, 'name' => 'A', 'slug' => 'a', 'type' => 'Unit']);

        $this->context->set($tenant, $membership);

        $response = $this->policy->update($user, $unit);
        $this->assertTrue($response === false || (is_object($response) && $response->denied()));
    }

    public function test_update_returns_false_for_foreign_organization_unit_even_with_permission(): void
    {
        $tenantA = Tenant::create(['name' => 'TA', 'slug' => 'ta2', 'is_active' => true]);
        $tenantB = Tenant::create(['name' => 'TB', 'slug' => 'tb2', 'is_active' => true]);

        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenantA->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->grantPermission($tenantA, $membership, PermissionSlug::ORGANIZATION_UNITS_UPDATE->value);

        $unitB = OrganizationUnit::create(['tenant_id' => $tenantB->id, 'name' => 'B', 'slug' => 'b', 'type' => 'Unit']);

        $this->context->set($tenantA, $membership);

        $response = $this->policy->update($user, $unitB);
        $this->assertTrue($response === false || (is_object($response) && $response->denied()));
    }
}
