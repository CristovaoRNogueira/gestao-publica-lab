<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Enums\PermissionSlug;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\OrganizationUnit;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;
use App\Notifications\AccountActivationNotification;
use Mockery;
use App\Modules\Tenancy\Services\RoleAssignmentService;
use Exception;

class TenantManualMemberAdditionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $globalAdminUser;
    private Membership $globalAdminMembership;
    private Role $adminRole;
    private Role $localAdminRole;
    private Role $memberRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionCatalogSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Add Member Test', 'slug' => 'add-test', 'is_active' => true]);

        $this->globalAdminUser = User::factory()->create();

        $this->globalAdminMembership = Membership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->globalAdminUser->id,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $this->adminRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Global',
            'slug' => 'admin-global-1',
        ]);
        $permissionId1 = \App\Modules\Tenancy\Models\Permission::where('slug', PermissionSlug::MEMBERSHIPS_MANAGE->value)->first()->id;
        $permissionId2 = \App\Modules\Tenancy\Models\Permission::where('slug', PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value)->first()->id;
        $permissionId3 = \App\Modules\Tenancy\Models\Permission::where('slug', PermissionSlug::ORGANIZATION_SCOPE_GLOBAL->value)->first()->id;
        $this->adminRole->permissions()->attach([$permissionId1, $permissionId2, $permissionId3]);

        $this->localAdminRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Local',
            'slug' => 'admin-local-1',
        ]);
        $this->localAdminRole->permissions()->attach([$permissionId1, $permissionId2]);

        $this->memberRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Membro',
            'slug' => 'membro-1',
        ]);

        $this->globalAdminMembership->roles()->attach($this->adminRole->id);
    }

    public function test_admin_adds_existing_user()
    {
        Notification::fake();
        $existingUser = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", [
                'name' => 'Should Not Matter',
                'email' => $existingUser->email,
                'role_id' => $this->memberRole->id,
                'organization_unit_id' => null,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.success', 'Membro adicionado com sucesso.');

        $this->assertDatabaseHas('memberships', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $existingUser->id,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $this->assertTrue(Hash::check('password123', $existingUser->fresh()->password));
        Notification::assertNothingSent();
    }

    public function test_new_user_is_created_with_passive_identity_and_receives_notification()
    {
        Notification::fake();

        $email = 'new.user@example.com';

        $response = $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", [
                'name' => 'New User',
                'email' => $email,
                'role_id' => $this->memberRole->id,
                'organization_unit_id' => null,
            ]);

        $response->assertRedirect();
        $newUser = User::where('email', $email)->first();
        $this->assertNotNull($newUser);

        Notification::assertSentTo($newUser, AccountActivationNotification::class);
    }

    public function test_new_user_cannot_login_before_password_definition()
    {
        Notification::fake();
        $email = 'passive@example.com';

        $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", [
                'name' => 'Passive User',
                'email' => $email,
                'role_id' => $this->memberRole->id,
            ]);

        $this->post('/logout');

        // Cannot login with empty password
        $this->post('/login', ['email' => $email, 'password' => ''])
            ->assertSessionHasErrors(['password']);

        // Cannot login with common passwords
        $this->post('/login', ['email' => $email, 'password' => 'password'])
            ->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_e2e_passive_identity_activation_flow()
    {
        Notification::fake();
        $email = 'e2e@example.com';

        // 1. Admin creates user manually
        $response = $this->actingAs($this->globalAdminUser)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", [
                'name' => 'E2E User',
                'email' => $email,
                'role_id' => $this->memberRole->id,
            ]);
        $response->assertRedirect();

        $newUser = User::where('email', $email)->first();
        $this->assertNotNull($newUser);

        // 2. Membership is active
        $this->assertDatabaseHas('memberships', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $newUser->id,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        // 3 & 4. User receives AccountActivationNotification
        Notification::assertSentTo($newUser, AccountActivationNotification::class, function ($notification, $channels) use ($newUser) {
            // Extract the token to simulate the received link
            $this->activationToken = $notification->token;
            return true;
        });

        // Ensure admin is logged out before guest can reset password
        $this->post('/logout');

        // 5 & 6 & 7. User accesses reset link and defines first password
        $resetResponse = $this->post('/reset-password', [
            'token' => $this->activationToken,
            'email' => $email,
            'password' => 'NewStrongPassword123!',
            'password_confirmation' => 'NewStrongPassword123!',
        ]);
        $resetResponse->assertSessionHasNoErrors();
        $resetResponse->assertRedirect('/dashboard');

        // Verify password changed
        $this->assertTrue(Hash::check('NewStrongPassword123!', $newUser->fresh()->password));

        // 8. Token is no longer reusable
        $this->post('/logout'); // ensure guest before testing token again
        $reuseResponse = $this->post('/reset-password', [
            'token' => $this->activationToken,
            'email' => $email,
            'password' => 'AnotherPassword123!',
            'password_confirmation' => 'AnotherPassword123!',
        ]);
        // Laravel reset password returns back with error or throws Invalid
        $reuseResponse->assertInvalid(['email']); // 'passwords.token' error is usually bound to email field in Laravel

        // 9. User can login
        $this->post('/logout'); // ensure guest
        $loginResponse = $this->post('/login', ['email' => $email, 'password' => 'NewStrongPassword123!']);
        $loginResponse->assertRedirect();
        $this->assertAuthenticatedAs($newUser);

        // 10 & 11. TenantResolver recognizes Membership active & accesses Tenant
        $dashboardResponse = $this->get('/dashboard');
        $dashboardResponse->assertOk();
    }

    public function test_cannot_add_if_membership_is_pending_inactive_or_rejected()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        Membership::create(['tenant_id' => $this->tenant->id, 'user_id' => $user1->id, 'status' => Membership::STATUS_PENDING]);
        Membership::create(['tenant_id' => $this->tenant->id, 'user_id' => $user2->id, 'status' => Membership::STATUS_INACTIVE]);
        Membership::create(['tenant_id' => $this->tenant->id, 'user_id' => $user3->id, 'status' => Membership::STATUS_REJECTED]);

        $this->actingAs($this->globalAdminUser)->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", ['name' => 'U1', 'email' => $user1->email, 'role_id' => $this->memberRole->id])
            ->assertStatus(409)->assertSee('Este usuário já possui uma solicitação de acesso aguardando aprovação.');

        $this->actingAs($this->globalAdminUser)->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", ['name' => 'U2', 'email' => $user2->email, 'role_id' => $this->memberRole->id])
            ->assertStatus(409)->assertSee('Este usuário possui um vínculo inativo com esta organização.');

        $this->actingAs($this->globalAdminUser)->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", ['name' => 'U3', 'email' => $user3->email, 'role_id' => $this->memberRole->id])
            ->assertStatus(409)->assertSee('Este usuário possui um vínculo recusado com esta organização.');
    }

    public function test_cannot_add_if_invitation_pending()
    {
        $email = 'invited@example.com';
        TenantInvitation::create([
            'tenant_id' => $this->tenant->id, 'email' => $email, 'role_id' => $this->memberRole->id,
            'status' => 'pending', 'token_hash' => hash('sha256', 'abc'), 'expires_at' => now()->addDays(1),
            'invited_by_user_id' => $this->globalAdminUser->id,
        ]);

        $this->actingAs($this->globalAdminUser)->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", ['name' => 'Invited', 'email' => $email, 'role_id' => $this->memberRole->id])
            ->assertStatus(409)->assertSee('Já existe um convite pendente para este endereço de e-mail.');
    }

    public function test_cannot_assign_role_of_another_tenant()
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);
        $otherRole = Role::create(['tenant_id' => $otherTenant->id, 'name' => 'Other Role', 'slug' => 'other-role']);

        $this->actingAs($this->globalAdminUser)->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", ['name' => 'X', 'email' => 'x@example.com', 'role_id' => $otherRole->id])
            ->assertStatus(400);
    }

    public function test_cannot_assign_role_outside_authority()
    {
        $superRole = Role::create(['tenant_id' => $this->tenant->id, 'name' => 'Super', 'slug' => 'super']);
        $permissionId = \App\Modules\Tenancy\Models\Permission::create(['name' => 'S', 'slug' => 'some.super.permission', 'group' => 'geral'])->id;
        $superRole->permissions()->attach($permissionId);

        $this->actingAs($this->globalAdminUser)->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", ['name' => 'X', 'email' => 'x@example.com', 'role_id' => $superRole->id])
            ->assertForbidden()->assertSee('Você não pode atribuir esta função.');
    }

    public function test_cannot_add_without_manage_capability()
    {
        $unauthAdminUser = User::factory()->create();
        $unauthAdminMembership = Membership::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $unauthAdminUser->id, 'status' => Membership::STATUS_ACTIVE,
        ]);
        $basicRole = Role::create(['tenant_id' => $this->tenant->id, 'name' => 'Basic', 'slug' => 'basic']);
        $unauthAdminMembership->roles()->attach($basicRole->id);

        $this->actingAs($unauthAdminUser)->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", ['name' => 'X', 'email' => 'x@example.com', 'role_id' => $this->memberRole->id])
            ->assertForbidden();
    }

    public function test_organization_scope_hierarchies()
    {
        $parentUnit = OrganizationUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Parent', 'type' => 'departamento', 'slug' => 'parent']);
        $siblingUnit = OrganizationUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Sibling', 'type' => 'departamento', 'slug' => 'sibling']);
        $targetUnit = OrganizationUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Target', 'type' => 'departamento', 'slug' => 'target', 'parent_id' => $parentUnit->id]);
        $descendantUnit = OrganizationUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Descendant', 'type' => 'departamento', 'slug' => 'desc', 'parent_id' => $targetUnit->id]);

        $localAdmin = User::factory()->create();
        $localMembership = Membership::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $localAdmin->id, 'status' => Membership::STATUS_ACTIVE, 'organization_unit_id' => $targetUnit->id,
        ]);
        $localMembership->roles()->attach($this->localAdminRole->id);

        // Same unit -> Allowed
        $this->actingAs($localAdmin)->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", ['name' => 'U1', 'email' => 'u1@example.com', 'role_id' => $this->memberRole->id, 'organization_unit_id' => $targetUnit->id])
            ->assertRedirect();

        // Descendant -> Allowed
        $this->actingAs($localAdmin)->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", ['name' => 'U2', 'email' => 'u2@example.com', 'role_id' => $this->memberRole->id, 'organization_unit_id' => $descendantUnit->id])
            ->assertRedirect();

        // Parent -> Forbidden
        $this->actingAs($localAdmin)->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", ['name' => 'U3', 'email' => 'u3@example.com', 'role_id' => $this->memberRole->id, 'organization_unit_id' => $parentUnit->id])
            ->assertForbidden();

        // Sibling -> Forbidden
        $this->actingAs($localAdmin)->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", ['name' => 'U4', 'email' => 'u4@example.com', 'role_id' => $this->memberRole->id, 'organization_unit_id' => $siblingUnit->id])
            ->assertForbidden();

        // Target global + actor local -> Forbidden
        $this->actingAs($localAdmin)->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", ['name' => 'U5', 'email' => 'u5@example.com', 'role_id' => $this->memberRole->id, 'organization_unit_id' => null])
            ->assertForbidden();

        // Target global + actor global -> Allowed (tested in other tests)
    }

    public function test_cannot_assign_unit_of_another_tenant()
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);
        $otherUnit = OrganizationUnit::create(['tenant_id' => $otherTenant->id, 'name' => 'Other Unit', 'type' => 'departamento', 'slug' => 'other-u']);

        $this->actingAs($this->globalAdminUser)->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", ['name' => 'X', 'email' => 'x@example.com', 'role_id' => $this->memberRole->id, 'organization_unit_id' => $otherUnit->id])
            ->assertStatus(400);
    }

    public function test_cannot_add_self()
    {
        $this->actingAs($this->globalAdminUser)->withSession(['tenant_id' => $this->tenant->id])
            ->post("/memberships/manual", ['name' => 'Myself', 'email' => $this->globalAdminUser->email, 'role_id' => $this->memberRole->id])
            ->assertForbidden();
    }

    public function test_transaction_rollback_if_role_assignment_fails()
    {
        Notification::fake();

        $this->mock(RoleAssignmentService::class, function ($mock) {
            $mock->shouldReceive('assignRole')->andThrow(new Exception('Intentional failure'));
        });

        $email = 'rollback@example.com';

        try {
            $this->actingAs($this->globalAdminUser)->withSession(['tenant_id' => $this->tenant->id])
                ->post("/memberships/manual", ['name' => 'R', 'email' => $email, 'role_id' => $this->memberRole->id]);
        } catch (Exception $e) {
            // Expected
        }

        $this->assertDatabaseMissing('users', ['email' => $email]);
        Notification::assertNothingSent();
    }
}
