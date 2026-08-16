<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Middleware\ResolveTenant;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantSelectionAfterLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test route behind auth + ResolveTenant middleware
        Route::middleware(['web', 'auth', ResolveTenant::class])->get('/_test/tenant-aware', function (TenantContext $context) {
            return response()->json([
                'tenant_id' => $context->getTenant()?->id,
            ]);
        });
    }

    public function test_login_with_one_membership_auto_selects_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Single Tenant',
            'slug' => 'single',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['password' => 'password']);
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertEquals($tenant->id, session('tenant_id'));
    }

    public function test_login_with_zero_memberships_has_no_active_tenant(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('tenant_id'));
    }

    public function test_login_with_multiple_memberships_does_not_auto_select(): void
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1', 'slug' => 't1', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'Tenant 2', 'slug' => 't2', 'is_active' => true]);

        $user = User::factory()->create(['password' => 'password']);
        Membership::create(['tenant_id' => $tenant1->id, 'user_id' => $user->id, 'is_active' => true]);
        Membership::create(['tenant_id' => $tenant2->id, 'user_id' => $user->id, 'is_active' => true]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('tenant_id'));
    }

    public function test_tenant_aware_route_blocked_without_tenant_in_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/_test/tenant-aware');

        $response->assertStatus(403);
    }

    public function test_tenant_aware_route_works_with_valid_tenant_in_session(): void
    {
        $tenant = Tenant::create([
            'name' => 'Active Tenant',
            'slug' => 'active',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->get('/_test/tenant-aware');

        $response->assertStatus(200);
        $response->assertJson(['tenant_id' => $tenant->id]);
    }

    public function test_login_with_one_inactive_tenant_has_no_active_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Inactive Tenant',
            'slug' => 'inactive',
            'is_active' => false,
        ]);

        $user = User::factory()->create(['password' => 'password']);
        Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('tenant_id'));
    }

    public function test_revoked_tenant_in_session_is_ignored_in_inertia_shared_props(): void
    {
        $tenant = Tenant::create([
            'name' => 'Revoked Tenant',
            'slug' => 'revoked',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $membership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);

        // Revoke membership after it was granted
        $membership->delete();

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->get('/dashboard');

        $response->assertRedirect('/onboarding');
    }
}
