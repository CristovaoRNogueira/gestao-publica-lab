<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\OrganizationUnit;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantMembershipApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $globalAdminUser;
    private Membership $globalAdminMembership;
    private Role $adminRole;
    private Role $localAdminRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionCatalogSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Approval Test', 'slug' => 'app-test', 'is_active' => true]);

        $this->adminRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Global',
            'slug' => 'admin-global-1',
        ]);
        $permissionId = \App\Modules\Tenancy\Models\Permission::where('slug', PermissionSlug::MEMBERSHIPS_MANAGE->value)->first()->id;
        $globalScopeId = \App\Modules\Tenancy\Models\Permission::where('slug', PermissionSlug::ORGANIZATION_SCOPE_GLOBAL->value)->first()->id;
        $this->adminRole->permissions()->attach([$permissionId, $globalScopeId]);

        $this->globalAdminUser = User::factory()->create();
        $this->globalAdminMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->globalAdminUser->id,
            'status' => Membership::STATUS_ACTIVE,
            'organization_unit_id' => null,
        ]);
        $this->globalAdminMembership->roles()->attach($this->adminRole->id);

        $this->localAdminRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Local',
            'slug' => 'admin-local-1',
        ]);
        $this->localAdminRole->permissions()->attach($permissionId);
    }

    private function createPendingMembership(?int $unitId = null): Membership
    {
        $user = User::factory()->create();
        return Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'status' => Membership::STATUS_PENDING,
            'organization_unit_id' => $unitId,
        ]);
    }

    public function test_global_admin_approves_global_pending()
    {
        $pending = $this->createPendingMembership();

        $response = $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$pending->id}/approve");

        $response->assertSessionHas('flash.success');
        $this->assertEquals(Membership::STATUS_ACTIVE, $pending->fresh()->status);
    }

    public function test_global_admin_rejects_global_pending()
    {
        $pending = $this->createPendingMembership();

        $response = $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$pending->id}/reject");

        $response->assertSessionHas('flash.success');
        $this->assertEquals(Membership::STATUS_REJECTED, $pending->fresh()->status);
    }

    public function test_local_admin_approves_own_unit()
    {
        $unit = OrganizationUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Unit 1', 'type' => 'secretaria', 'slug' => 'unit-1-'.uniqid()]);

        $localAdminUser = User::factory()->create();
        $localAdminMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $localAdminUser->id,
            'status' => Membership::STATUS_ACTIVE,
            'organization_unit_id' => $unit->id,
        ]);
        $localAdminMembership->roles()->attach($this->localAdminRole->id);

        $pending = $this->createPendingMembership($unit->id);

        $response = $this->actingAs($localAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$pending->id}/approve");

        $response->assertSessionHas('flash.success');
        $this->assertEquals(Membership::STATUS_ACTIVE, $pending->fresh()->status);
    }

    public function test_local_admin_approves_descendant()
    {
        $unit = OrganizationUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Unit 1', 'type' => 'secretaria', 'slug' => 'unit-1-'.uniqid()]);
        $childUnit = OrganizationUnit::create(['tenant_id' => $this->tenant->id, 'parent_id' => $unit->id, 'name' => 'Unit 2', 'type' => 'departamento', 'slug' => 'unit-2-'.uniqid()]);

        $localAdminUser = User::factory()->create();
        $localAdminMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $localAdminUser->id,
            'status' => Membership::STATUS_ACTIVE,
            'organization_unit_id' => $unit->id,
        ]);
        $localAdminMembership->roles()->attach($this->localAdminRole->id);

        $pending = $this->createPendingMembership($childUnit->id);

        $response = $this->actingAs($localAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$pending->id}/approve");

        $response->assertSessionHas('flash.success');
        $this->assertEquals(Membership::STATUS_ACTIVE, $pending->fresh()->status);
    }

    public function test_local_admin_cannot_approve_parent()
    {
        $unit = OrganizationUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Unit 1', 'type' => 'secretaria', 'slug' => 'unit-1-'.uniqid()]);
        $childUnit = OrganizationUnit::create(['tenant_id' => $this->tenant->id, 'parent_id' => $unit->id, 'name' => 'Unit 2', 'type' => 'departamento', 'slug' => 'unit-2-'.uniqid()]);

        $localAdminUser = User::factory()->create();
        $localAdminMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $localAdminUser->id,
            'status' => Membership::STATUS_ACTIVE,
            'organization_unit_id' => $childUnit->id,
        ]);
        $localAdminMembership->roles()->attach($this->localAdminRole->id);

        $pending = $this->createPendingMembership($unit->id);

        $response = $this->actingAs($localAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$pending->id}/approve");

        $response->assertForbidden();
        $this->assertEquals(Membership::STATUS_PENDING, $pending->fresh()->status);
    }

    public function test_local_admin_cannot_approve_sibling()
    {
        $parentUnit = OrganizationUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Unit 1', 'type' => 'secretaria', 'slug' => 'unit-1-'.uniqid()]);
        $unit1 = OrganizationUnit::create(['tenant_id' => $this->tenant->id, 'parent_id' => $parentUnit->id, 'name' => 'Unit 2', 'type' => 'departamento', 'slug' => 'unit-2-'.uniqid()]);
        $unit2 = OrganizationUnit::create(['tenant_id' => $this->tenant->id, 'parent_id' => $parentUnit->id, 'name' => 'Unit 3', 'type' => 'departamento', 'slug' => 'unit-3-'.uniqid()]);

        $localAdminUser = User::factory()->create();
        $localAdminMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $localAdminUser->id,
            'status' => Membership::STATUS_ACTIVE,
            'organization_unit_id' => $unit1->id,
        ]);
        $localAdminMembership->roles()->attach($this->localAdminRole->id);

        $pending = $this->createPendingMembership($unit2->id);

        $response = $this->actingAs($localAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$pending->id}/approve");

        $response->assertForbidden();
    }

    public function test_unauthorized_user_cannot_approve()
    {
        $userWithoutPerms = User::factory()->create();
        Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $userWithoutPerms->id,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $pending = $this->createPendingMembership();

        $response = $this->actingAs($userWithoutPerms)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$pending->id}/approve");

        $response->assertForbidden();
    }

    public function test_user_cannot_approve_itself()
    {
        $pendingUser = User::factory()->create();
        $pending = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $pendingUser->id,
            'status' => Membership::STATUS_PENDING,
        ]);

        $response = $this->actingAs($pendingUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$pending->id}/approve");

        $response->assertForbidden();
    }

    public function test_local_admin_cannot_approve_global_target()
    {
        $unit = OrganizationUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Unit 1', 'type' => 'secretaria', 'slug' => 'unit-1-'.uniqid()]);

        $localAdminUser = User::factory()->create();
        $localAdminMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $localAdminUser->id,
            'status' => Membership::STATUS_ACTIVE,
            'organization_unit_id' => $unit->id,
        ]);
        $localAdminMembership->roles()->attach($this->localAdminRole->id);

        $pendingGlobal = $this->createPendingMembership(null);

        $response = $this->actingAs($localAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$pendingGlobal->id}/approve");

        $response->assertForbidden();
        $this->assertEquals(Membership::STATUS_PENDING, $pendingGlobal->fresh()->status);
    }

    public function test_active_cannot_approve_active_or_rejected()
    {
        $activeMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->create()->id,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$activeMembership->id}/approve");

        $response->assertForbidden();
    }

    public function test_active_cannot_be_rejected()
    {
        $activeMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->create()->id,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$activeMembership->id}/reject");

        $response->assertForbidden();
    }

    public function test_inactive_cannot_be_approved()
    {
        $inactiveMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->create()->id,
            'status' => Membership::STATUS_INACTIVE,
        ]);

        $response = $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$inactiveMembership->id}/approve");

        $response->assertForbidden();
    }

    public function test_inactive_cannot_be_rejected()
    {
        $inactiveMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->create()->id,
            'status' => Membership::STATUS_INACTIVE,
        ]);

        $response = $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$inactiveMembership->id}/reject");

        $response->assertForbidden();
    }

    public function test_rejected_cannot_be_approved()
    {
        $rejectedMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->create()->id,
            'status' => Membership::STATUS_REJECTED,
        ]);

        $response = $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$rejectedMembership->id}/approve");

        $response->assertForbidden();
    }

    public function test_rejected_cannot_be_rejected()
    {
        $rejectedMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->create()->id,
            'status' => Membership::STATUS_REJECTED,
        ]);

        $response = $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$rejectedMembership->id}/reject");

        $response->assertForbidden();
    }

    public function test_cross_tenant_pending_cannot_be_approved()
    {
        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-tenant', 'is_active' => true]);
        $pendingCross = Membership::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => User::factory()->create()->id,
            'status' => Membership::STATUS_PENDING,
            'organization_unit_id' => null,
        ]);

        $response = $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$pendingCross->id}/approve");

        $response->assertForbidden();
    }

    public function test_cross_tenant_pending_cannot_be_rejected()
    {
        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-tenant', 'is_active' => true]);
        $pendingCross = Membership::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => User::factory()->create()->id,
            'status' => Membership::STATUS_PENDING,
            'organization_unit_id' => null,
        ]);

        $response = $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$pendingCross->id}/reject");

        $response->assertForbidden();
    }

    public function test_approved_membership_is_resolved_by_tenant_resolver()
    {
        $pending = $this->createPendingMembership();

        $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$pending->id}/approve");

        // Simulating the user trying to access the tenant
        $response = $this->actingAs($pending->user)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get("/dashboard");

        $response->assertOk(); // Since status is active, ResolveTenant middleware permits access
    }

    public function test_rejected_membership_remains_unresolved_by_tenant_resolver()
    {
        $pending = $this->createPendingMembership();

        $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$pending->id}/reject");

        $response = $this->actingAs($pending->user)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get("/dashboard");

        $response->assertRedirect(); // Blocked by ResolveTenant middleware or dashboard logic
    }

    public function test_second_decision_on_same_membership_fails()
    {
        $pending = $this->createPendingMembership();

        $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$pending->id}/approve");

        // Tentando rejeitar algo já aprovado
        $response = $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->patch("/memberships/{$pending->id}/reject");

        $response->assertForbidden();
    }

    public function test_concurrent_approve_reject_does_not_allow_double_transition()
    {
        $pending = $this->createPendingMembership();

        $service = app(\App\Modules\Tenancy\Services\MembershipStatusService::class);

        $service->approve($pending);

        // A segunda transação concorrente que chamaria reject ou approve abortará com 409
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Apenas solicitações pendentes');

        $service->reject($pending);
    }
}
