<?php

namespace Tests\Feature\Secretaria;

use App\Models\User;
use App\Modules\Secretaria\Models\Secretaria;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia;

class SecretariaTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveTenant(string $slug = 'test-tenant'): Tenant
    {
        return Tenant::create([
            'name' => 'Test Tenant',
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Index (viewAny)
    // -------------------------------------------------------------------------

    public function test_guest_cannot_list_secretarias(): void
    {
        $response = $this->get('/secretarias');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_without_tenant_cannot_list_secretarias(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/secretarias');

        $response->assertStatus(403);
    }

    public function test_member_can_list_secretarias(): void
    {
        $tenant = $this->createActiveTenant();
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        Secretaria::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Secretaria A',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->get('/secretarias');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Secretaria/Index')
            ->has('secretarias', 1)
            ->where('secretarias.0.name', 'Secretaria A')
        );
    }

    public function test_index_only_returns_secretarias_of_active_tenant(): void
    {
        $tenantA = $this->createActiveTenant('tenant-a');
        $tenantB = $this->createActiveTenant('tenant-b');

        $user = User::factory()->create();
        $user->tenants()->attach([$tenantA->id, $tenantB->id]);

        Secretaria::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'A1']);
        Secretaria::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'A2']);
        Secretaria::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'B1']);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenantA->id])
            ->get('/secretarias');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('secretarias', 2)
        );
    }

    // -------------------------------------------------------------------------
    // Store (create)
    // -------------------------------------------------------------------------

    public function test_member_can_create_secretaria(): void
    {
        $tenant = $this->createActiveTenant();
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->post('/secretarias', [
                'name' => 'Nova Secretaria',
                'description' => 'Descricao teste',
                'is_active' => true,
            ]);

        $response->assertRedirect('/secretarias');
        $this->assertDatabaseHas('secretarias', [
            'tenant_id' => $tenant->id,
            'name' => 'Nova Secretaria',
            'slug' => 'nova-secretaria',
        ]);
    }

    public function test_store_never_accepts_tenant_id_from_request(): void
    {
        $tenantA = $this->createActiveTenant('tenant-a');
        $tenantB = $this->createActiveTenant('tenant-b');

        $user = User::factory()->create();
        $user->tenants()->attach($tenantA);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenantA->id])
            ->post('/secretarias', [
                'name' => 'Malicious',
                'tenant_id' => $tenantB->id, // Attempt to inject tenant B
            ]);

        // Should be created under tenant A (active session)
        $this->assertDatabaseHas('secretarias', [
            'tenant_id' => $tenantA->id,
            'name' => 'Malicious',
        ]);

        $this->assertDatabaseMissing('secretarias', [
            'tenant_id' => $tenantB->id,
            'name' => 'Malicious',
        ]);
    }

    public function test_slug_is_generated_and_unique_per_tenant(): void
    {
        $tenant = $this->createActiveTenant();
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        // First one
        $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->post('/secretarias', ['name' => 'Educação']);

        // Second one with same name
        $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->post('/secretarias', ['name' => 'Educação']);

        $this->assertDatabaseHas('secretarias', ['slug' => 'educacao', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('secretarias', ['slug' => 'educacao-1', 'tenant_id' => $tenant->id]);
    }

    public function test_slug_is_not_globally_unique(): void
    {
        $tenantA = $this->createActiveTenant('a');
        $tenantB = $this->createActiveTenant('b');
        $user = User::factory()->create();
        $user->tenants()->attach([$tenantA->id, $tenantB->id]);

        $this->actingAs($user)->withSession(['tenant_id' => $tenantA->id])
            ->post('/secretarias', ['name' => 'Saúde']);

        $this->actingAs($user)->withSession(['tenant_id' => $tenantB->id])
            ->post('/secretarias', ['name' => 'Saúde']);

        $this->assertDatabaseHas('secretarias', ['slug' => 'saude', 'tenant_id' => $tenantA->id]);
        $this->assertDatabaseHas('secretarias', ['slug' => 'saude', 'tenant_id' => $tenantB->id]);
    }

    // -------------------------------------------------------------------------
    // Update (update)
    // -------------------------------------------------------------------------

    public function test_member_can_update_own_secretaria(): void
    {
        $tenant = $this->createActiveTenant();
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $secretaria = Secretaria::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->put("/secretarias/{$secretaria->id}", [
                'name' => 'New Name',
                'is_active' => false,
            ]);

        $response->assertRedirect('/secretarias');
        $this->assertDatabaseHas('secretarias', [
            'id' => $secretaria->id,
            'name' => 'New Name',
            'is_active' => false,
        ]);
    }

    public function test_member_cannot_update_secretaria_of_other_tenant(): void
    {
        $tenantA = $this->createActiveTenant('a');
        $tenantB = $this->createActiveTenant('b');
        $user = User::factory()->create();
        $user->tenants()->attach([$tenantA->id, $tenantB->id]);

        $secretariaB = Secretaria::factory()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Tenant B Sec',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenantA->id]) // Active is A
            ->put("/secretarias/{$secretariaB->id}", [ // Attempt update B
                'name' => 'Hacked',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('secretarias', [
            'id' => $secretariaB->id,
            'name' => 'Tenant B Sec',
        ]);
    }

    // -------------------------------------------------------------------------
    // Explicit Authorization Wiring Test
    // -------------------------------------------------------------------------

    public function test_authorization_path_is_fully_wired_via_controller(): void
    {
        $tenantA = $this->createActiveTenant('ta');
        $tenantB = $this->createActiveTenant('tb');

        $user = User::factory()->create();
        $user->tenants()->attach([$tenantA->id, $tenantB->id]);

        $secretariaA = Secretaria::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'A']);
        $secretariaB = Secretaria::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'B']);

        // Test 1: User with Tenant A session can access Secretaria A
        $response1 = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenantA->id])
            ->put("/secretarias/{$secretariaA->id}", [
                'name' => 'A Updated',
            ]);

        $response1->assertRedirect(); // Authorization passed, redirect after update

        // Test 2: User with Tenant A session CANNOT access Secretaria B
        $response2 = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenantA->id])
            ->put("/secretarias/{$secretariaB->id}", [
                'name' => 'B Hacked',
            ]);

        $response2->assertStatus(403); // Authorization failed
    }
}
