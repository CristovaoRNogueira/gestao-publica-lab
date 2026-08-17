<?php

namespace Tests\Unit\Services;

use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Models\User;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Exceptions\CannotRemoveLastAdminException;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Services\RoleAssignmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Tests\TestCase;

class RoleAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;
    private RoleAssignmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = new TenantContext();
        $this->service = new RoleAssignmentService($this->context);
    }

    public function test_assign_role_cross_tenant_target_membership_throws_exception()
    {
        $tenant1 = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'T2', 'slug' => 't2', 'is_active' => true]);

        $actorMembership = Membership::create(['tenant_id' => $tenant1->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->context->set($tenant1, $actorMembership);

        $targetMembership = Membership::create(['tenant_id' => $tenant2->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $role = Role::create(['tenant_id' => $tenant1->id, 'name' => 'Test', 'slug' => 'test']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Target membership não pertence ao tenant ativo.');
        $this->service->assignRole($actorMembership, $targetMembership, $role->id);
    }

    public function test_assign_role_cross_tenant_role_throws_404()
    {
        $tenant1 = Tenant::create(['name' => 'T1', 'slug' => 't1', 'is_active' => true]);
        $tenant2 = Tenant::create(['name' => 'T2', 'slug' => 't2', 'is_active' => true]);

        $actorMembership = Membership::create(['tenant_id' => $tenant1->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->context->set($tenant1, $actorMembership);

        $targetMembership = Membership::create(['tenant_id' => $tenant1->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $role = Role::create(['tenant_id' => $tenant2->id, 'name' => 'Test', 'slug' => 'test']);

        $this->expectException(ModelNotFoundException::class);
        $this->service->assignRole($actorMembership, $targetMembership, $role->id);
    }

    public function test_assign_role_invalid_actor_context_throws_exception()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);

        $actorMembership1 = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $actorMembership2 = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->context->set($tenant, $actorMembership1);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Test', 'slug' => 'test']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Actor membership inválida para o contexto atual.');
        $this->service->assignRole($actorMembership2, $targetMembership, $role->id);
    }

    public function test_assign_role_inactive_membership_throws_exception()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $actorMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->context->set($tenant, $actorMembership);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'is_active' => false]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Test', 'slug' => 'test']);

        $this->expectException(\App\Modules\Tenancy\Exceptions\CannotAssignRoleToInactiveMembershipException::class);
        $this->expectExceptionMessage('Não é possível atribuir papéis a uma associação inativa.');
        $this->service->assignRole($actorMembership, $targetMembership, $role->id);
    }

    public function test_revoke_role_invalid_actor_context_throws_exception()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);

        $actorMembership1 = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $actorMembership2 = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->context->set($tenant, $actorMembership1);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Test', 'slug' => 'test']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Actor membership inválida para o contexto atual.');
        $this->service->revokeRole($actorMembership2, $targetMembership, $role);
    }

    public function test_revoke_role_inactive_membership_is_allowed()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $actorMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->context->set($tenant, $actorMembership);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'is_active' => false]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Test', 'slug' => 'test']);
        $targetMembership->roles()->attach($role);

        $this->service->revokeRole($actorMembership, $targetMembership, $role);
        $this->assertCount(0, $targetMembership->fresh()->roles);
    }

    public function test_revoke_last_admin_role_throws_exception()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $actorMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->context->set($tenant, $actorMembership);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'slug' => 'admin']);
        $permission = Permission::create(['name' => \App\Modules\Tenancy\Enums\PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value, 'slug' => \App\Modules\Tenancy\Enums\PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value]);
        $role->permissions()->attach($permission);

        $targetMembership->roles()->attach($role);

        $this->expectException(CannotRemoveLastAdminException::class);
        $this->service->revokeRole($actorMembership, $targetMembership, $role);
    }

    public function test_revoke_admin_role_when_another_active_admin_exists_is_allowed()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $actorMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->context->set($tenant, $actorMembership);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $otherMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'slug' => 'admin']);
        $permission = Permission::create(['name' => \App\Modules\Tenancy\Enums\PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value, 'slug' => \App\Modules\Tenancy\Enums\PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value]);
        $role->permissions()->attach($permission);

        $targetMembership->roles()->attach($role);
        $otherMembership->roles()->attach($role);

        $this->service->revokeRole($actorMembership, $targetMembership, $role);
        $this->assertCount(0, $targetMembership->fresh()->roles);
    }

    public function test_revoke_admin_role_when_another_admin_is_inactive_throws_exception()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $actorMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->context->set($tenant, $actorMembership);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $inactiveAdmin = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'is_active' => false]);

        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'slug' => 'admin']);
        $permission = Permission::create(['name' => \App\Modules\Tenancy\Enums\PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value, 'slug' => \App\Modules\Tenancy\Enums\PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value]);
        $role->permissions()->attach($permission);

        $targetMembership->roles()->attach($role);
        $inactiveAdmin->roles()->attach($role);

        $this->expectException(CannotRemoveLastAdminException::class);
        $this->service->revokeRole($actorMembership, $targetMembership, $role);
    }

    public function test_assign_role_idempotent()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $actorMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->context->set($tenant, $actorMembership);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Test', 'slug' => 'test']);

        $this->service->assignRole($actorMembership, $targetMembership, $role->id);
        // Attempt again
        $this->service->assignRole($actorMembership, $targetMembership, $role->id);

        $this->assertCount(1, $targetMembership->fresh()->roles);
    }

    public function test_revoke_role_idempotent()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $actorMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->context->set($tenant, $actorMembership);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Test', 'slug' => 'test']);

        // Revoke when not attached should not throw exception
        $this->service->revokeRole($actorMembership, $targetMembership, $role);
        $this->assertCount(0, $targetMembership->fresh()->roles);
    }

    public function test_revoke_unassigned_admin_role_is_idempotent()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $actorMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->context->set($tenant, $actorMembership);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $otherAdminRole = Role::create(['tenant_id' => $tenant->id, 'name' => 'Other Admin', 'slug' => 'other_admin']);
        $permission = Permission::create(['name' => \App\Modules\Tenancy\Enums\PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value, 'slug' => \App\Modules\Tenancy\Enums\PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value]);
        $otherAdminRole->permissions()->attach($permission);
        $targetMembership->roles()->attach($otherAdminRole);

        $unassignedAdminRole = Role::create(['tenant_id' => $tenant->id, 'name' => 'Unassigned Admin', 'slug' => 'unassigned_admin']);
        $unassignedAdminRole->permissions()->attach($permission);

        // Attempting to revoke an unassigned admin role should be a no-op, even if it provides manage permissions.
        // It should NOT throw CannotRemoveLastAdminException because it's not being removed.
        $this->service->revokeRole($actorMembership, $targetMembership, $unassignedAdminRole);

        $this->assertCount(1, $targetMembership->fresh()->roles);
        $this->assertTrue($targetMembership->fresh()->roles->contains($otherAdminRole->id));
    }

    public function test_assign_role_propagates_non_unique_query_exception()
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'is_active' => true]);
        $actorMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $this->context->set($tenant, $actorMembership);

        $targetMembership = Membership::create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Test', 'slug' => 'test']);

        // Mock the relation to throw a generic QueryException
        $targetMock = \Mockery::mock($targetMembership)->makePartial();
        $targetMock->shouldReceive('roles')->andThrow(new QueryException('connection', 'SELECT 1', [], new \Exception('Generic error')));

        $this->expectException(QueryException::class);
        $this->service->assignRole($actorMembership, $targetMock, $role->id);
    }
}