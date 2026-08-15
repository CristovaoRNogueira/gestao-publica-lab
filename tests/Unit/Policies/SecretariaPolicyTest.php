<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Modules\Secretaria\Models\Secretaria;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Membership;
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

    public function test_viewAny_returns_true_when_tenant_is_active(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't1', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $this->context->set($tenant, $membership);

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_viewAny_returns_false_without_tenant_context(): void
    {
        $user = User::factory()->create();

        $this->context->set(null, null);

        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_create_returns_true_when_tenant_is_active(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't3', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $this->context->set($tenant, $membership);

        $this->assertTrue($this->policy->create($user));
    }

    public function test_update_returns_true_for_own_secretaria(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't4', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $secretaria = Secretaria::factory()->create(['tenant_id' => $tenant->id]);

        $this->context->set($tenant, $membership);

        $this->assertTrue($this->policy->update($user, $secretaria));
    }

    public function test_update_returns_false_for_foreign_secretaria(): void
    {
        $tenantA = Tenant::create(['name' => 'TA', 'slug' => 'ta2', 'is_active' => true]);
        $tenantB = Tenant::create(['name' => 'TB', 'slug' => 'tb2', 'is_active' => true]);

        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenantA->id, 'user_id' => $user->id, 'is_active' => true]);

        $secretaria = Secretaria::factory()->create(['tenant_id' => $tenantB->id]);

        $this->context->set($tenantA, $membership);

        $this->assertFalse($this->policy->update($user, $secretaria));
    }
}
