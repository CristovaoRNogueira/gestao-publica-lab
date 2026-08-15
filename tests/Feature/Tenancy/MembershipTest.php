<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Middleware\ResolveTenant;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Services\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Test route that exposes TenantContext details
        Route::middleware(['web', 'auth', ResolveTenant::class])
            ->get('/_test/membership-context', function (TenantContext $context) {
                return response()->json([
                    'tenant_id' => $context->getTenant()?->id,
                    'membership_id' => $context->getMembership()?->id,
                    'membership_tenant_id' => $context->getMembership()?->tenant_id,
                    'membership_user_id' => $context->getMembership()?->user_id,
                ]);
            });
    }

    // -------------------------------------------------------------------------
    // Membership state — TenantResolver
    // -------------------------------------------------------------------------

    public function test_active_membership_allows_tenant_resolution(): void
    {
        $tenant = Tenant::create(['name' => 'Active', 'slug' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $resolver = app(TenantResolver::class);
        $resolved = $resolver->resolve($tenant->id, $user);

        $this->assertNotNull($resolved);
        $this->assertEquals($tenant->id, $resolved->tenant->id);
        $this->assertEquals($user->id, $resolved->membership->user_id);
        $this->assertEquals($tenant->id, $resolved->membership->tenant_id);
    }

    public function test_inactive_membership_blocks_tenant_resolution(): void
    {
        $tenant = Tenant::create(['name' => 'Active', 'slug' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => false]);

        $resolver = app(TenantResolver::class);
        $resolved = $resolver->resolve($tenant->id, $user);

        $this->assertNull($resolved);
    }

    public function test_absent_membership_blocks_tenant_resolution(): void
    {
        $tenant = Tenant::create(['name' => 'Active', 'slug' => 'active', 'is_active' => true]);
        $user = User::factory()->create();
        // No membership created

        $resolver = app(TenantResolver::class);
        $resolved = $resolver->resolve($tenant->id, $user);

        $this->assertNull($resolved);
    }

    public function test_resolver_returns_null_for_inactive_membership(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 'res-inactive', 'is_active' => true]);
        $user = User::factory()->create();
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => false]);

        $resolver = app(TenantResolver::class);
        $result = $resolver->resolve($tenant->id, $user);

        $this->assertNull($result);
    }

    public function test_resolver_loads_correct_membership_per_tenant(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'res-a', 'is_active' => true]);
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'res-b', 'is_active' => true]);
        $user = User::factory()->create();

        $membershipA = Membership::create(['tenant_id' => $tenantA->id, 'user_id' => $user->id, 'is_active' => true]);
        $membershipB = Membership::create(['tenant_id' => $tenantB->id, 'user_id' => $user->id, 'is_active' => true]);

        $resolver = app(TenantResolver::class);

        $resolvedA = $resolver->resolve($tenantA->id, $user);
        $this->assertNotNull($resolvedA);
        $this->assertEquals($tenantA->id, $resolvedA->tenant->id);
        $this->assertEquals($membershipA->id, $resolvedA->membership->id);
        $this->assertEquals($tenantA->id, $resolvedA->membership->tenant_id);

        $resolvedB = $resolver->resolve($tenantB->id, $user);
        $this->assertNotNull($resolvedB);
        $this->assertEquals($tenantB->id, $resolvedB->tenant->id);
        $this->assertEquals($membershipB->id, $resolvedB->membership->id);
        $this->assertEquals($tenantB->id, $resolvedB->membership->tenant_id);
    }

    // -------------------------------------------------------------------------
    // Membership state — auto-select after login
    // -------------------------------------------------------------------------

    public function test_user_with_active_and_inactive_memberships(): void
    {
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'mix-a', 'is_active' => true]);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'mix-b', 'is_active' => true]);

        $user = User::factory()->create(['password' => 'password']);
        Membership::create(['tenant_id' => $tenantA->id, 'user_id' => $user->id, 'is_active' => true]);
        Membership::create(['tenant_id' => $tenantB->id, 'user_id' => $user->id, 'is_active' => false]);

        // Only one active membership → auto-select tenant A
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertEquals($tenantA->id, session('tenant_id'));

        // Tenant B should be inaccessible via ResolveTenant
        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenantB->id])
            ->get('/_test/membership-context');

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // TenantContext + Membership
    // -------------------------------------------------------------------------

    public function test_tenant_context_returns_correct_membership(): void
    {
        $tenant = Tenant::create(['name' => 'Ctx', 'slug' => 'ctx', 'is_active' => true]);
        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->get('/_test/membership-context');

        $response->assertStatus(200);
        $response->assertJson([
            'tenant_id' => $tenant->id,
            'membership_id' => $membership->id,
            'membership_tenant_id' => $tenant->id,
            'membership_user_id' => $user->id,
        ]);
    }

    public function test_tenant_context_membership_and_tenant_are_consistent(): void
    {
        $tenant = Tenant::create(['name' => 'Consistent', 'slug' => 'consistent', 'is_active' => true]);
        $user = User::factory()->create();
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->get('/_test/membership-context');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals($data['tenant_id'], $data['membership_tenant_id']);
    }

    // -------------------------------------------------------------------------
    // Regressão — SecretariaPolicy continua funcionando sem RBAC
    // -------------------------------------------------------------------------

    public function test_secretaria_policy_unchanged(): void
    {
        $tenant = Tenant::create(['name' => 'Policy', 'slug' => 'policy', 'is_active' => true]);
        $user = User::factory()->create();
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        // viewAny should work (TenantContext has tenant)
        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->get('/secretarias');

        $response->assertStatus(200);
    }
}
