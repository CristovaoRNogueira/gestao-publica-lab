<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Platform\Context\PlatformContext;
use App\Modules\Platform\Models\PlatformPermission;
use App\Modules\Platform\Models\PlatformRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_does_not_possess_platform_permission()
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant', 'is_active' => true]);
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $role = Role::create(['name' => 'Tenant Admin', 'slug' => 'tenant-admin', 'tenant_id' => $tenant->id]);
        $permission = Permission::create(['name' => 'Platform Access Fake', 'slug' => 'platform.access']);

        $role->permissions()->attach($permission->id);
        $membership->roles()->attach($role->id);

        $context = new PlatformContext();
        $context->set($user);

        $this->assertFalse($context->hasPermission('platform.access'), 'Tenant permissions should not leak into PlatformContext');
    }

    public function test_platform_admin_possesses_platform_access()
    {
        $user = User::factory()->create();
        $role = PlatformRole::create(['name' => 'Platform Admin', 'slug' => 'platform-admin']);
        $permission = PlatformPermission::create(['name' => 'Access', 'slug' => 'platform.access']);

        $role->permissions()->attach($permission->id);
        $user->platformRoles()->attach($role->id);

        $context = new PlatformContext();
        $context->set($user);

        $this->assertTrue($context->hasPermission('platform.access'));
    }

    public function test_tenant_role_does_not_grant_platform_permission()
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $role = Role::create(['name' => 'Tenant Role', 'slug' => 'tenant-role', 'tenant_id' => $tenant->id]);
        $membership->roles()->attach($role->id);

        // Even if we name the tenant role "platform-admin"
        $role->update(['slug' => 'platform-admin']);

        $context = new PlatformContext();
        $context->set($user);

        $this->assertFalse($context->hasPermission('platform.access'));
    }

    public function test_platform_role_does_not_grant_tenant_permission()
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $role = PlatformRole::create(['name' => 'Platform Admin', 'slug' => 'platform-admin']);
        $permission = PlatformPermission::create(['name' => 'Fake Tenant Perm', 'slug' => 'memberships.roles.manage']);

        $role->permissions()->attach($permission->id);
        $user->platformRoles()->attach($role->id);

        $this->assertFalse($membership->hasPermission('memberships.roles.manage'), 'Platform permissions should not leak into Tenant Membership');
    }
}
