<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
    }

    public function test_user_with_no_memberships_goes_to_onboarding()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/onboarding');
    }

    public function test_user_with_pending_membership_goes_to_pending_approval()
    {
        $user = User::factory()->create();
        Membership::create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'status' => Membership::STATUS_PENDING]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/pending-approval');
    }

    public function test_user_with_inactive_membership_only_goes_to_access_denied()
    {
        $user = User::factory()->create();
        Membership::create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'status' => Membership::STATUS_INACTIVE]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/access-denied');
    }

    public function test_user_with_rejected_membership_only_goes_to_access_denied()
    {
        $user = User::factory()->create();
        Membership::create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'status' => Membership::STATUS_REJECTED]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/access-denied');
    }

    public function test_user_with_active_membership_goes_to_normal_dashboard()
    {
        $user = User::factory()->create();
        Membership::create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'status' => Membership::STATUS_ACTIVE]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }

    public function test_user_with_active_and_inactive_memberships_goes_to_normal_dashboard()
    {
        $user = User::factory()->create();
        Membership::create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'status' => Membership::STATUS_INACTIVE]);
        $tenant2 = Tenant::create(['name' => 'T2', 'slug' => 't2', 'is_active' => true]);
        Membership::create(['tenant_id' => $tenant2->id, 'user_id' => $user->id, 'status' => Membership::STATUS_ACTIVE]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }
}
