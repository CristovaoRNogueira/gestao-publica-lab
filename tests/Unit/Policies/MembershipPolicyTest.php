<?php

namespace Tests\Unit\Policies;

use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Policies\MembershipPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;
    private MembershipPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = new TenantContext();
        $this->policy = new MembershipPolicy($this->context);
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

    public function test_assignRole_returns_true_with_permission_and_same_tenant()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->grantPermission($tenant, $membership, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);
        $this->context->set($tenant, $membership);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->grantPermission($tenant, $membership, PermissionSlug::ORGANIZATION_SCOPE_GLOBAL->value);
        $this->assertTrue($this->policy->assignRole($user, $targetMembership));
    }

    public function test_assignRole_returns_false_without_permission()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->context->set($tenant, $membership);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->assertFalse($this->policy->assignRole($user, $targetMembership));
    }

    public function test_assignRole_returns_false_different_tenant()
    {
        $tenant1 = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'T2', 'slug' => 't2', 'is_active' => true]);

        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant1->id, 'user_id' => $user->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->grantPermission($tenant1, $membership, PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);
        $this->context->set($tenant1, $membership);

        $targetMembership = Membership::create(['tenant_id' => $tenant2->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->assertFalse($this->policy->assignRole($user, $targetMembership));
    }
}