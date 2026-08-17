<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePolicyTest extends TestCase
{
    use RefreshDatabase;

    private RolePolicy $policy;
    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = $this->app->make(TenantContext::class);
        $this->policy = new RolePolicy($this->context);
    }

    private function grantPermission(Tenant $tenant, Membership $membership, string $permissionSlug): void
    {
        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Role with ' . $permissionSlug,
            'slug' => 'role-' . str_replace('.', '-', $permissionSlug) . '-' . uniqid(),
        ]);

        $permission = Permission::firstOrCreate(['slug' => $permissionSlug, 'name' => $permissionSlug]);
        $role->permissions()->attach($permission->id);
        $membership->roles()->attach($role->id);
        $membership->load('roles.permissions');
    }

    public function test_view_any_requires_tenant_and_permission()
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->assertFalse($this->policy->viewAny($user));

        $this->context->set($tenant, $membership);
        $this->assertFalse($this->policy->viewAny($user));

        $this->grantPermission($tenant, $membership, PermissionSlug::ROLES_VIEW->value);
        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_view_requires_ownership_and_permission()
    {
        $user = User::factory()->create();
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenantA->id]);

        $roleA = Role::create(['tenant_id' => $tenantA->id, 'name' => 'A', 'slug' => 'a']);
        $roleB = Role::create(['tenant_id' => $tenantB->id, 'name' => 'B', 'slug' => 'b']);

        $this->context->set($tenantA, $membership);

        $this->assertFalse($this->policy->view($user, $roleA));

        $this->grantPermission($tenantA, $membership, PermissionSlug::ROLES_VIEW->value);

        $this->assertTrue($this->policy->view($user, $roleA));
        $this->assertFalse($this->policy->view($user, $roleB));
    }

    public function test_create_requires_tenant_and_permission()
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->context->set($tenant, $membership);
        $this->assertFalse($this->policy->create($user));

        $this->grantPermission($tenant, $membership, PermissionSlug::ROLES_CREATE->value);
        $this->assertTrue($this->policy->create($user));
    }

    public function test_update_requires_ownership_and_permission()
    {
        $user = User::factory()->create();
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenantA->id]);

        $roleA = Role::create(['tenant_id' => $tenantA->id, 'name' => 'A', 'slug' => 'a']);
        $roleB = Role::create(['tenant_id' => $tenantB->id, 'name' => 'B', 'slug' => 'b']);

        $this->context->set($tenantA, $membership);

        $this->assertFalse($this->policy->update($user, $roleA));

        $this->grantPermission($tenantA, $membership, PermissionSlug::ROLES_UPDATE->value);

        $this->assertTrue($this->policy->update($user, $roleA));
        $this->assertFalse($this->policy->update($user, $roleB));
    }

    public function test_delete_requires_ownership_and_permission()
    {
        $user = User::factory()->create();
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenantA->id]);

        $roleA = Role::create(['tenant_id' => $tenantA->id, 'name' => 'A', 'slug' => 'a']);
        $roleB = Role::create(['tenant_id' => $tenantB->id, 'name' => 'B', 'slug' => 'b']);

        $this->context->set($tenantA, $membership);

        $this->assertFalse($this->policy->delete($user, $roleA));

        $this->grantPermission($tenantA, $membership, PermissionSlug::ROLES_DELETE->value);

        $this->assertTrue($this->policy->delete($user, $roleA));
        $this->assertFalse($this->policy->delete($user, $roleB));
    }

    public function test_view_permissions_requires_ownership_and_permission()
    {
        $user = User::factory()->create();
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenantA->id]);

        $roleA = Role::create(['tenant_id' => $tenantA->id, 'name' => 'A', 'slug' => 'a']);
        $roleB = Role::create(['tenant_id' => $tenantB->id, 'name' => 'B', 'slug' => 'b']);

        $this->context->set($tenantA, $membership);

        $this->assertFalse($this->policy->viewPermissions($user, $roleA));

        $this->grantPermission($tenantA, $membership, PermissionSlug::ROLES_PERMISSIONS_MANAGE->value);

        $this->assertTrue($this->policy->viewPermissions($user, $roleA));
        $this->assertFalse($this->policy->viewPermissions($user, $roleB));
    }

    public function test_manage_permissions_requires_ownership_and_permission()
    {
        $user = User::factory()->create();
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenantA->id]);

        $roleA = Role::create(['tenant_id' => $tenantA->id, 'name' => 'A', 'slug' => 'a']);
        $roleB = Role::create(['tenant_id' => $tenantB->id, 'name' => 'B', 'slug' => 'b']);

        $this->context->set($tenantA, $membership);

        $this->assertFalse($this->policy->managePermissions($user, $roleA));

        $this->grantPermission($tenantA, $membership, PermissionSlug::ROLES_PERMISSIONS_MANAGE->value);

        $this->assertTrue($this->policy->managePermissions($user, $roleA));
        $this->assertFalse($this->policy->managePermissions($user, $roleB));
    }
}
