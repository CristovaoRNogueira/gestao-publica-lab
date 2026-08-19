<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\OrganizationUnit;
use App\Modules\Tenancy\Models\Permission;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationUnitTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $globalAdminUser;
    private Membership $globalAdminMembership;

    private User $localAdminUser;
    private Membership $localAdminMembership;
    private OrganizationUnit $localRootUnit;

    private User $magicNullUser;
    private Membership $magicNullMembership;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Prefeitura', 'slug' => 'prefeitura', 'is_active' => true]);

        // Global Admin setup
        $this->globalAdminUser = User::factory()->create();
        $this->globalAdminMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->globalAdminUser->id,
            'status' => Membership::STATUS_ACTIVE,
        ]);
        $this->grantPermissions($this->tenant, $this->globalAdminMembership, [
            PermissionSlug::ORGANIZATION_SCOPE_GLOBAL->value,
            PermissionSlug::ORGANIZATION_UNITS_VIEW->value,
            PermissionSlug::ORGANIZATION_UNITS_CREATE->value,
            PermissionSlug::ORGANIZATION_UNITS_UPDATE->value,
            PermissionSlug::ORGANIZATION_UNITS_DELETE->value,
        ]);

        // Local Admin Setup
        $this->localRootUnit = OrganizationUnit::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Secretaria A',
            'slug' => 'secretaria-a',
            'type' => 'Secretaria'
        ]);

        $this->localAdminUser = User::factory()->create();
        $this->localAdminMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->localAdminUser->id,
            'status' => Membership::STATUS_ACTIVE,
            'organization_unit_id' => $this->localRootUnit->id,
        ]);
        $this->grantPermissions($this->tenant, $this->localAdminMembership, [
            PermissionSlug::ORGANIZATION_UNITS_VIEW->value,
            PermissionSlug::ORGANIZATION_UNITS_CREATE->value,
            PermissionSlug::ORGANIZATION_UNITS_UPDATE->value,
            PermissionSlug::ORGANIZATION_UNITS_DELETE->value,
        ]);

        // Magic Null User Setup
        $this->magicNullUser = User::factory()->create();
        $this->magicNullMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->magicNullUser->id,
            'status' => Membership::STATUS_ACTIVE,
            'organization_unit_id' => null, // The magic null
        ]);
        $this->grantPermissions($this->tenant, $this->magicNullMembership, [
            PermissionSlug::ORGANIZATION_UNITS_VIEW->value,
            PermissionSlug::ORGANIZATION_UNITS_CREATE->value,
            PermissionSlug::ORGANIZATION_UNITS_UPDATE->value,
            PermissionSlug::ORGANIZATION_UNITS_DELETE->value,
        ]);
    }

    private function grantPermissions(Tenant $tenant, Membership $membership, array $permissionSlugs): void
    {
        $role = Role::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Role_' . uniqid(), 'slug' => 'role_' . uniqid()]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(['name' => $slug, 'slug' => $slug]);
            if (!$role->permissions->contains($permission->id)) {
                $role->permissions()->attach($permission);
            }
        }

        if (!$membership->roles->contains($role->id)) {
            $membership->roles()->attach($role);
        }
    }

    public function test_magic_null_without_global_scope_is_denied()
    {
        // Try to update an existing unit
        $response = $this->actingAs($this->magicNullUser)
                         ->withSession(['tenant_id' => $this->tenant->id])
                         ->put('/organization-units/' . $this->localRootUnit->id, [
                             'name' => 'Hacked',
                             'type' => 'Secretaria'
                         ]);

        $response->assertStatus(403);
    }

    public function test_global_scope_admin_can_manage_all_units()
    {
        // View
        $response = $this->actingAs($this->globalAdminUser)
                         ->withSession(['tenant_id' => $this->tenant->id])
                         ->get('/organization-units');
        $response->assertStatus(200);

        // Create root unit
        $response = $this->actingAs($this->globalAdminUser)
                         ->withSession(['tenant_id' => $this->tenant->id])
                         ->post('/organization-units', [
                             'name' => 'Secretaria B',
                             'type' => 'Secretaria'
                         ]);
        $response->assertRedirect('/organization-units');
        $this->assertDatabaseHas('organization_units', ['name' => 'Secretaria B']);

        // Update local unit
        $response = $this->actingAs($this->globalAdminUser)
                         ->withSession(['tenant_id' => $this->tenant->id])
                         ->put('/organization-units/' . $this->localRootUnit->id, [
                             'name' => 'Secretaria A updated',
                             'type' => 'Secretaria'
                         ]);
        $response->assertRedirect('/organization-units');
        $this->assertDatabaseHas('organization_units', ['name' => 'Secretaria A updated']);
    }

    public function test_local_admin_can_only_manage_descendant_units_hierarchy()
    {
        // Create child unit
        $response = $this->actingAs($this->localAdminUser)
                         ->withSession(['tenant_id' => $this->tenant->id])
                         ->post('/organization-units', [
                             'name' => 'Departamento A1',
                             'type' => 'Departamento',
                             'parent_id' => $this->localRootUnit->id
                         ]);
        $response->assertRedirect('/organization-units');
        $child = OrganizationUnit::where('name', 'Departamento A1')->first();

        // Cannot create root unit
        $response = $this->actingAs($this->localAdminUser)
                         ->withSession(['tenant_id' => $this->tenant->id])
                         ->post('/organization-units', [
                             'name' => 'Secretaria B',
                             'type' => 'Secretaria'
                         ]);
        $response->assertStatus(403);

        // Cannot access outside scope
        $otherUnit = OrganizationUnit::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Other Branch',
            'slug' => 'other-branch',
            'type' => 'Secretaria'
        ]);

        $response = $this->actingAs($this->localAdminUser)
                         ->withSession(['tenant_id' => $this->tenant->id])
                         ->get('/organization-units/' . $otherUnit->id . '/edit');
        $response->assertStatus(403);
    }

    public function test_cannot_move_unit_to_its_own_descendant()
    {
        $child = OrganizationUnit::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Departamento A1',
            'slug' => 'dep-a1',
            'type' => 'Departamento',
            'parent_id' => $this->localRootUnit->id
        ]);

        $response = $this->actingAs($this->globalAdminUser)
                         ->withSession(['tenant_id' => $this->tenant->id])
                         ->put('/organization-units/' . $this->localRootUnit->id, [
                             'name' => 'Secretaria A',
                             'type' => 'Secretaria',
                             'parent_id' => $child->id
                         ]);
        $response->assertStatus(422); // Cyclic loop protection
    }

    public function test_delete_block_when_having_children_or_members()
    {
        // Add member
        $anotherUser = User::factory()->create();
        Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $anotherUser->id,
            'status' => Membership::STATUS_ACTIVE,
            'organization_unit_id' => $this->localRootUnit->id,
        ]);

        $response = $this->actingAs($this->globalAdminUser)
                         ->withSession(['tenant_id' => $this->tenant->id])
                         ->delete('/organization-units/' . $this->localRootUnit->id);

        $response->assertStatus(409)
                 ->assertJson(['message' => 'Não é possível excluir esta unidade porque existem membros vinculados a ela.']);
    }

    public function test_delete_block_when_having_children()
    {
        OrganizationUnit::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Child Unit',
            'slug' => 'child-unit',
            'type' => 'Departamento',
            'parent_id' => $this->localRootUnit->id
        ]);

        $response = $this->actingAs($this->globalAdminUser)
                         ->withSession(['tenant_id' => $this->tenant->id])
                         ->delete('/organization-units/' . $this->localRootUnit->id);

        $response->assertStatus(409)
                 ->assertJson(['message' => 'Não é possível excluir esta unidade porque ela possui subunidades.']);
    }

    public function test_cross_tenant_protection()
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);
        $otherUnit = OrganizationUnit::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Unit',
            'slug' => 'other-unit',
            'type' => 'Secretaria'
        ]);

        $response = $this->actingAs($this->globalAdminUser)
                         ->withSession(['tenant_id' => $this->tenant->id])
                         ->put('/organization-units/' . $otherUnit->id, [
                             'name' => 'Hacked',
                             'type' => 'Secretaria'
                         ]);

        $response->assertStatus(403); // Different tenant
    }
}
