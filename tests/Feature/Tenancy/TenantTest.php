<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a dummy route to test the middleware
        Route::middleware(['web', ResolveTenant::class])->get('/_test/tenant', function (TenantContext $context) {
            return response()->json([
                'tenant_id' => $context->getTenant()?->id,
            ]);
        });
    }

    public function test_tenant_can_be_created()
    {
        $tenant = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Acme Corp',
            'is_active' => true,
        ]);
    }

    public function test_authenticated_user_has_tenant()
    {
        $tenant = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
        ]);

        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $this->assertTrue($user->tenants->contains($tenant));
    }

    public function test_request_without_valid_tenant_is_rejected()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/_test/tenant');

        $response->assertStatus(403);
    }

    public function test_user_cannot_access_tenant_they_do_not_belong_to()
    {
        $tenant1 = Tenant::create(['name' => 'Tenant 1', 'slug' => 't1']);
        $tenant2 = Tenant::create(['name' => 'Tenant 2', 'slug' => 't2']);

        $user = User::factory()->create();
        $user->tenants()->attach($tenant1);

        // Try to access tenant 2 via session
        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant2->id])
            ->get('/_test/tenant');

        $response->assertStatus(403);
        $response->assertSessionMissing('tenant_id');
    }

    public function test_inactive_tenant_is_rejected()
    {
        $tenant = Tenant::create([
            'name' => 'Inactive Tenant',
            'slug' => 'inactive',
            'is_active' => false,
        ]);

        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->get('/_test/tenant');

        $response->assertStatus(403);
        $response->assertSessionMissing('tenant_id');
    }

    public function test_tenant_context_returns_correct_tenant()
    {
        $tenant = Tenant::create([
            'name' => 'Active Tenant',
            'slug' => 'active',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->get('/_test/tenant');

        $response->assertStatus(200);
        $response->assertJson([
            'tenant_id' => $tenant->id,
        ]);
    }
}
