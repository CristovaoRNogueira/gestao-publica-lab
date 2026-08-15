<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Modules\Secretaria\Models\Secretaria;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Policies\SecretariaPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretariaPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;
    private SecretariaPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = app(TenantContext::class);
        $this->policy = new SecretariaPolicy($this->context);
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
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $this->grantPermission($tenant, $membership, 'secretarias.view');

        $this->context->set($tenant, $membership);

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_viewAny_returns_false_without_permission(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't1', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

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
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $this->grantPermission($tenant, $membership, 'secretarias.create');

        $this->context->set($tenant, $membership);

        $this->assertTrue($this->policy->create($user));
    }

    public function test_create_returns_false_without_permission(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't3', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $this->context->set($tenant, $membership);

        $this->assertFalse($this->policy->create($user));
    }

    public function test_update_returns_true_for_own_secretaria_with_permission(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't4', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $this->grantPermission($tenant, $membership, 'secretarias.update');

        $secretaria = Secretaria::factory()->create(['tenant_id' => $tenant->id]);

        $this->context->set($tenant, $membership);

        $this->assertTrue($this->policy->update($user, $secretaria));
    }

    public function test_update_returns_false_without_permission(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't4', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $secretaria = Secretaria::factory()->create(['tenant_id' => $tenant->id]);

        $this->context->set($tenant, $membership);

        $this->assertFalse($this->policy->update($user, $secretaria));
    }

    public function test_update_returns_false_for_foreign_secretaria_even_with_permission(): void
    {
        $tenantA = Tenant::create(['name' => 'TA', 'slug' => 'ta2', 'is_active' => true]);
        $tenantB = Tenant::create(['name' => 'TB', 'slug' => 'tb2', 'is_active' => true]);

        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenantA->id, 'user_id' => $user->id, 'is_active' => true]);

        $this->grantPermission($tenantA, $membership, 'secretarias.update');

        $secretaria = Secretaria::factory()->create(['tenant_id' => $tenantB->id]);

        $this->context->set($tenantA, $membership);

        $this->assertFalse($this->policy->update($user, $secretaria));
    }
}
