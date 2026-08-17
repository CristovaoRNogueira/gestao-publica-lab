<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionCatalogSeeder::class);
    }

    public function test_guest_cannot_create_tenant()
    {
        $response = $this->post('/tenants', [
            'name' => 'New Tenant',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_create_tenant_and_becomes_admin_with_capabilities()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/tenants', [
            'name' => 'New Tenant',
            'slug' => 'new-tenant',
        ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('success', 'Tenant criado com sucesso.');

        // Validate Tenant
        $tenant = Tenant::where('slug', 'new-tenant')->first();
        $this->assertNotNull($tenant);
        $this->assertEquals('New Tenant', $tenant->name);
        $this->assertTrue($tenant->is_active);

        // Validate session is updated with new tenant
        $response->assertSessionHas('tenant_id', $tenant->id);

        // Validate Membership
        $membership = Membership::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertEquals(\App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE, $membership->status);

        // Validate Admin Role
        $this->assertEquals(1, $membership->roles()->count());
        $role = $membership->roles()->first();
        $this->assertEquals('admin', $role->slug);
        $this->assertEquals($tenant->id, $role->tenant_id);

        // Validate Capabilities
        $this->assertEquals(12, $role->permissions()->count());

        $capabilities = $role->permissions()->pluck('slug')->toArray();
        $expectedCapabilities = PermissionSlug::defaultAdminSlugs();

        sort($capabilities);
        sort($expectedCapabilities);
        $this->assertEquals($expectedCapabilities, $capabilities);
    }

    public function test_name_is_required()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/tenants', []);

        $response->assertSessionHasErrors('name');
    }

    public function test_slug_is_auto_generated()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/tenants', [
            'name' => 'My Auto Generated Tenant',
        ]);

        $response->assertRedirect('/dashboard');

        $tenant = Tenant::latest('id')->first();
        $this->assertEquals('my-auto-generated-tenant', $tenant->slug);
    }

    public function test_explicit_slug_collision_fails_validation()
    {
        Tenant::create(['name' => 'Existing', 'slug' => 'existing']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/tenants', [
            'name' => 'New Name',
            'slug' => 'existing',
        ]);

        $response->assertSessionHasErrors('slug');
        $this->assertEquals(1, Tenant::count());
    }

    public function test_generated_slug_collision_is_caught_and_fails_validation()
    {
        Tenant::create(['name' => 'Existing', 'slug' => 'existing']);

        $user = User::factory()->create();

        // This would generate 'existing' as slug, bypassing the FormRequest unique check
        // because the slug is null in the request, and is generated in the Service.
        $response = $this->actingAs($user)->post('/tenants', [
            'name' => 'Existing',
        ]);

        $response->assertSessionHasErrors('slug');
        $this->assertEquals(1, Tenant::count());
    }

    public function test_generated_empty_slug_fails_validation()
    {
        $user = User::factory()->create();

        // Str::slug('   ') becomes ''
        $response = $this->actingAs($user)->post('/tenants', [
            'name' => '   ', // This would pass 'required' depending on trimming, let's use emojis or something that strips to empty
        ]);

        // Wait, Laravel trims string fields by default, so '   ' becomes null, failing the 'required' rule on name.
        // Let's use a string that bypasses required but generates empty slug.
        // For example, an emoji only string or symbols that Str::slug removes entirely.
        $response = $this->actingAs($user)->post('/tenants', [
            'name' => '★★★',
        ]);

        $response->assertSessionHasErrors('slug');
        $this->assertEquals(0, Tenant::count());
    }

    public function test_unrelated_query_exception_is_propagated()
    {
        $user = User::factory()->create();

        // Force a QueryException that is NOT a unique violation on tenants_slug_unique.
        // For instance, by making the name exceed the database column size limit (if applicable),
        // or by manipulating the DB context.
        // We can simply drop the tenants table or similar in a transaction to cause a general QueryException,
        // or mock the DB facade if possible. But an easier way is to just use a very long string
        // that violates the database max length if it's set to 255.
        // Wait, the FormRequest limits 'name' to 255. Let's bypass FormRequest by calling the service directly.

        $this->expectException(\Illuminate\Database\QueryException::class);

        $service = app(\App\Modules\Tenancy\Services\CreateTenantService::class);
        $service->execute($user, [
            'name' => null, // Violates NOT NULL constraint, triggering QueryException in both Postgres and SQLite
            'slug' => 'valid-slug',
        ]);
    }

    public function test_authenticated_user_without_membership_is_redirected_to_onboarding()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/onboarding');
    }

    public function test_authenticated_user_with_only_inactive_memberships_is_redirected_to_onboarding()
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'Inactive', 'slug' => 'inactive']);
        Membership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/onboarding');
    }

    public function test_authenticated_user_with_membership_accesses_dashboard_normally()
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'Active', 'slug' => 'active']);
        Membership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }

    public function test_onboarding_route_returns_200_for_authenticated_user()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/onboarding');

        $response->assertOk();
    }

    public function test_onboarding_route_redirects_to_login_for_guest()
    {
        $response = $this->get('/onboarding');

        $response->assertRedirect('/login');
    }
}
