<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSelectionTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createActiveTenant(string $slug = 'test-tenant'): Tenant
    {
        return Tenant::create([
            'name' => 'Test Tenant',
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Guest
    // -------------------------------------------------------------------------

    public function test_guest_cannot_select_tenant(): void
    {
        $tenant = $this->createActiveTenant();

        $response = $this->post('/tenant/select', ['tenant_id' => $tenant->id]);

        $response->assertRedirect('/login');
        $this->assertNull(session('tenant_id'));
    }

    // -------------------------------------------------------------------------
    // Tenant válido
    // -------------------------------------------------------------------------

    public function test_user_can_select_valid_tenant(): void
    {
        $tenant = $this->createActiveTenant();
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $response = $this->actingAs($user)
            ->post('/tenant/select', ['tenant_id' => $tenant->id]);

        $response->assertRedirect('/dashboard');
    }

    public function test_tenant_id_is_stored_in_session_after_selection(): void
    {
        $tenant = $this->createActiveTenant();
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $this->actingAs($user)
            ->post('/tenant/select', ['tenant_id' => $tenant->id]);

        $this->assertEquals($tenant->id, session('tenant_id'));
    }

    public function test_selecting_tenant_redirects_to_dashboard(): void
    {
        $tenant = $this->createActiveTenant();
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $response = $this->actingAs($user)
            ->post('/tenant/select', ['tenant_id' => $tenant->id]);

        $response->assertRedirect('/dashboard');
    }

    // -------------------------------------------------------------------------
    // Tenant inexistente
    // -------------------------------------------------------------------------

    public function test_user_cannot_select_nonexistent_tenant(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/tenant/select', ['tenant_id' => 99999]);

        $response->assertStatus(403);
        $this->assertNull(session('tenant_id'));
    }

    // -------------------------------------------------------------------------
    // Tenant inativo
    // -------------------------------------------------------------------------

    public function test_user_cannot_select_inactive_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Inactive Tenant',
            'slug' => 'inactive',
            'is_active' => false,
        ]);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $response = $this->actingAs($user)
            ->post('/tenant/select', ['tenant_id' => $tenant->id]);

        $response->assertStatus(403);
        $this->assertNull(session('tenant_id'));
    }

    // -------------------------------------------------------------------------
    // Sem membership
    // -------------------------------------------------------------------------

    public function test_user_cannot_select_tenant_without_membership(): void
    {
        $tenant = $this->createActiveTenant();
        $user = User::factory()->create();
        // User is NOT attached to the tenant

        $response = $this->actingAs($user)
            ->post('/tenant/select', ['tenant_id' => $tenant->id]);

        $response->assertStatus(403);
        $this->assertNull(session('tenant_id'));
    }

    // -------------------------------------------------------------------------
    // Validação de entrada
    // -------------------------------------------------------------------------

    public function test_tenant_id_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/tenant/select', []);

        $response->assertSessionHasErrors('tenant_id');
        $this->assertNull(session('tenant_id'));
    }

    public function test_tenant_id_must_be_integer(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/tenant/select', ['tenant_id' => 'not-an-integer']);

        $response->assertSessionHasErrors('tenant_id');
        $this->assertNull(session('tenant_id'));
    }

    // -------------------------------------------------------------------------
    // Shared props (Inertia)
    // -------------------------------------------------------------------------

    public function test_dashboard_exposes_multiple_tenants_in_shared_props(): void
    {
        $tenant1 = Tenant::create(['name' => 'Tenant A', 'slug' => 'ta', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'Tenant B', 'slug' => 'tb', 'is_active' => true]);

        $user = User::factory()->create();
        $user->tenants()->attach([$tenant1->id, $tenant2->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('auth.tenant', null)
            ->has('auth.tenants', 2)
        );
    }

    // -------------------------------------------------------------------------
    // Regressão — auto-select de único tenant ainda funciona
    // -------------------------------------------------------------------------

    public function test_single_tenant_auto_select_still_works_after_login(): void
    {
        $tenant = $this->createActiveTenant('single');
        $user = User::factory()->create(['password' => 'password']);
        $user->tenants()->attach($tenant);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertEquals($tenant->id, session('tenant_id'));
    }
}
