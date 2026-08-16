<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantInvitationUITest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionCatalogSeeder::class);
    }

    protected function createMemberWithPermissions(array $permissions)
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant-' . uniqid(), 'is_active' => true]);
        $user = User::factory()->create();

        $membership = Membership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Role',
            'slug' => 'test-role',
        ]);

        $permissionIds = \App\Modules\Tenancy\Models\Permission::whereIn('slug', $permissions)->pluck('id');
        $role->permissions()->sync($permissionIds);

        $membership->roles()->attach($role->id);

        $this->actingAs($user);
        $this->withSession(['tenant_id' => $tenant->id]);

        return [$user, $tenant, $role];
    }

    public function test_guest_cannot_access_invitation_endpoints()
    {
        $this->get('/invitations')->assertRedirect('/login');
        $this->get('/invitations/create')->assertRedirect('/login');
    }

    public function test_user_without_invitations_view_receives_403()
    {
        [$user, $tenant] = $this->createMemberWithPermissions([]);

        $this->get('/invitations')->assertStatus(403);
    }

    public function test_user_with_invitations_view_can_access_index()
    {
        [$user, $tenant] = $this->createMemberWithPermissions([PermissionSlug::INVITATIONS_VIEW->value]);

        $this->get('/invitations')->assertOk();
    }

    public function test_user_with_invitations_manage_can_access_create()
    {
        [$user, $tenant] = $this->createMemberWithPermissions([
            PermissionSlug::INVITATIONS_VIEW->value,
            PermissionSlug::INVITATIONS_MANAGE->value
        ]);

        $this->get('/invitations/create')->assertOk();
    }

    public function test_index_avoids_n_plus_one_queries()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermissions([PermissionSlug::INVITATIONS_VIEW->value]);

        for ($i = 0; $i < 5; $i++) {
            TenantInvitation::create([
                'tenant_id' => $tenant->id,
                'email' => "test{$i}@example.com",
                'role_id' => $role->id,
                'token_hash' => "hash{$i}",
                'status' => 'pending',
                'invited_by_user_id' => $user->id,
                'expires_at' => now()->addDays(3),
            ]);
        }

        // Base query
        $this->get('/invitations')->assertOk();

        $queryCountBase = 0;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$queryCountBase) {
            $queryCountBase++;
        });

        $this->get('/invitations')->assertOk();

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::forgetRecordModificationState();
        \Illuminate\Support\Facades\Event::forget('Illuminate\Database\Events\QueryExecuted');

        for ($i = 5; $i < 15; $i++) {
            TenantInvitation::create([
                'tenant_id' => $tenant->id,
                'email' => "test{$i}@example.com",
                'role_id' => $role->id,
                'token_hash' => "hash{$i}",
                'status' => 'pending',
                'invited_by_user_id' => $user->id,
                'expires_at' => now()->addDays(3),
            ]);
        }

        $queryCountLoaded = 0;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$queryCountLoaded) {
            $queryCountLoaded++;
        });

        $this->get('/invitations')->assertOk();

        // The number of queries should not scale with N (should be basically same as base, maybe 1 more if pagination computes different)
        $this->assertLessThanOrEqual($queryCountBase + 2, $queryCountLoaded);
    }

    public function test_endpoints_return_correct_inertia_responses()
    {
        [$user, $tenant, $role] = $this->createMemberWithPermissions([
            PermissionSlug::INVITATIONS_VIEW->value,
            PermissionSlug::INVITATIONS_MANAGE->value
        ]);

        $this->get('/invitations')
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Invitation/Index'));

        $this->get('/invitations/create')
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Invitation/Create'));
    }
}
