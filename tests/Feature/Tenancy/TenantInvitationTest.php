<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Modules\Tenancy\Notifications\TenantInvitationNotification;
use Tests\TestCase;
use App\Modules\Tenancy\Enums\PermissionSlug;
use Illuminate\Support\Str;

class TenantInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionCatalogSeeder::class);
    }

    private function createAdmin(Tenant $tenant): User
    {
        $admin = User::factory()->create();
        $membership = Membership::create(['user_id' => $admin->id, 'tenant_id' => $tenant->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'slug' => 'admin']);
        // Sync permissions manually for the test
        $permissions = \App\Modules\Tenancy\Models\Permission::whereIn('slug', [
            PermissionSlug::INVITATIONS_VIEW->value,
            PermissionSlug::INVITATIONS_MANAGE->value,
            PermissionSlug::ORGANIZATION_SCOPE_GLOBAL->value
        ])->pluck('id');
        $role->permissions()->sync($permissions);
        $membership->roles()->sync([$role->id]);

        return $admin;
    }

    public function test_authorized_tenant_admin_can_create_invitation()
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't1', 'is_active' => true]);
        $admin = $this->createAdmin($tenant);
        $role = Role::create(['name' => 'Test', 'slug' => 'test-role', 'tenant_id' => $tenant->id]);

        $this->actingAs($admin);
        session(['tenant_id' => $tenant->id]);

        $response = $this->post('/invitations', [
            'email' => 'TEST@example.com',
            'role_id' => $role->id,
        ]);

        $response->assertRedirect('/invitations');
        $response->assertSessionHas('success', 'Convite enviado com sucesso.');

        $this->assertDatabaseHas('tenant_invitations', [
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'status' => 'pending',
        ]);

        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            TenantInvitationNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'test@example.com';
            }
        );
    }

    public function test_unauthorized_tenant_user_cannot_create_invitation()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't2', 'is_active' => true]);
        $user = User::factory()->create();
        Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $role = Role::create(['name' => 'Test', 'slug' => 'test-role2', 'tenant_id' => $tenant->id]);

        $this->actingAs($user);
        session(['tenant_id' => $tenant->id]);

        $this->post('/invitations', [
            'email' => 'test@example.com',
            'role_id' => $role->id,
        ])->assertForbidden();
    }

    public function test_guest_cannot_create_invitation()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't3', 'is_active' => true]);
        $role = Role::create(['name' => 'Test', 'slug' => 'test-role3', 'tenant_id' => $tenant->id]);

        session(['tenant_id' => $tenant->id]);

        $this->post('/invitations', [
            'email' => 'test@example.com',
            'role_id' => $role->id,
        ])->assertRedirect('/login');
    }

    public function test_foreign_tenant_role_cannot_be_invited()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't4', 'is_active' => true]);
        $admin = $this->createAdmin($tenant);

        $otherTenant = Tenant::create(['name' => 'Test', 'slug' => 't5', 'is_active' => true]);
        $foreignRole = Role::create(['name' => 'Test', 'slug' => 'test-role4', 'tenant_id' => $otherTenant->id]);

        $this->actingAs($admin);
        session(['tenant_id' => $tenant->id]);

        $this->post('/invitations', [
            'email' => 'test@example.com',
            'role_id' => $foreignRole->id,
        ])->assertInvalid(['role_id']);
    }

    public function test_authorized_tenant_admin_cannot_invite_active_member()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't101', 'is_active' => true]);
        $admin = $this->createAdmin($tenant);
        $role = Role::create(['name' => 'Test', 'slug' => 'test-role101', 'tenant_id' => $tenant->id]);

        $user = User::factory()->create(['email' => 'USER@EXAMPLE.COM']);
        Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $this->actingAs($admin);
        session(['tenant_id' => $tenant->id]);

        $this->post('/invitations', [
            'email' => 'user@example.com', // Different casing
            'role_id' => $role->id,
        ])->assertSessionHasErrors(['email']);
    }

    public function test_pending_duplicate_invitation_is_rejected()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't100', 'is_active' => true]);
        $admin = $this->createAdmin($tenant);
        $role = Role::create(['name' => 'Test', 'slug' => 'test-role100', 'tenant_id' => $tenant->id]);

        TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'status' => 'pending',
            'role_id' => $role->id,
        ]);

        $this->actingAs($admin);
        session(['tenant_id' => $tenant->id]);

        $this->post('/invitations', [
            'email' => 'TEST@example.com',
            'role_id' => $role->id,
        ])->assertSessionHasErrors();
    }

    public function test_resend_invalidates_old_token_and_renews_expiration()
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't7', 'is_active' => true]);
        $admin = $this->createAdmin($tenant);

        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'pending',
            'expires_at' => now()->subDay(),
        ]);
        $oldHash = $invitation->token_hash;

        $this->actingAs($admin);
        session(['tenant_id' => $tenant->id]);

        $this->post("/invitations/{$invitation->id}/resend")->assertRedirect();

        $invitation->refresh();

        $this->assertNotEquals($oldHash, $invitation->token_hash);
        $this->assertTrue($invitation->expires_at->isFuture());

        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            TenantInvitationNotification::class
        );
    }

    public function test_public_guest_can_view_valid_invitation_for_new_user()
    {
        $token = Str::random(32);
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't8', 'is_active' => true]);
        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
            'email' => 'new-guest@example.com',
        ]);

        $this->get("/invites/{$token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Invites/Accept')
                ->where('isValid', true)
                ->where('tenantName', $invitation->tenant->name)
                ->where('isAuthenticated', false)
                ->where('userExists', false)
            );
    }

    public function test_public_guest_can_view_valid_invitation_for_existing_user()
    {
        User::factory()->create(['email' => 'existing-guest@example.com']);
        $token = Str::random(32);
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't8b', 'is_active' => true]);
        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
            'email' => 'existing-guest@example.com',
        ]);

        $this->get("/invites/{$token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Invites/Accept')
                ->where('isValid', true)
                ->where('tenantName', $invitation->tenant->name)
                ->where('isAuthenticated', false)
                ->where('userExists', true)
            );
    }

    public function test_invalid_token_is_rejected_generically()
    {
        $this->get("/invites/invalid_token")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Invites/Accept')
                ->where('isValid', false)
            );
    }

    public function test_expired_invitation_is_rejected()
    {
        $token = Str::random(32);
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't9', 'is_active' => true]);
        TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
            'expires_at' => now()->subHour(),
        ]);

        $this->get("/invites/{$token}")
            ->assertInertia(fn ($page) => $page->where('isValid', false));
    }

    public function test_revoked_invitation_is_rejected()
    {
        $token = Str::random(32);
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't10', 'is_active' => true]);
        TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'token_hash' => hash('sha256', $token),
            'status' => 'revoked',
        ]);

        $this->get("/invites/{$token}")
            ->assertInertia(fn ($page) => $page->where('isValid', false));
    }

    public function test_guest_cannot_accept()
    {
        $token = Str::random(32);
        $this->post("/invites/{$token}")->assertRedirect('/login');
    }

    public function test_authenticated_wrong_email_cannot_accept()
    {
        $user = User::factory()->create(['email' => 'wrong@example.com']);
        $token = Str::random(32);
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't11', 'is_active' => true]);
        TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'right@example.com',
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
        ]);

        $this->actingAs($user);
        $this->post("/invites/{$token}")->assertForbidden();
    }

    public function test_existing_user_can_accept_invitation()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Str::random(32);
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't12', 'is_active' => true]);
        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'USER@example.com', // test normalization
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
        ]);

        $this->actingAs($user);
        $this->post("/invites/{$token}")->assertRedirect('/pending-approval');

        $invitation->refresh();
        $this->assertEquals('accepted', $invitation->status);
        $this->assertNotNull($invitation->accepted_at);

        $this->assertDatabaseHas('memberships', [
            'user_id' => $user->id,
            'tenant_id' => $invitation->tenant_id,
            'status' => \App\Modules\Tenancy\Models\Membership::STATUS_PENDING,
        ]);

        $membership = Membership::where('user_id', $user->id)->where('tenant_id', $invitation->tenant_id)->first();
        $this->assertTrue($membership->roles->contains($invitation->role_id));
    }

    public function test_user_with_active_membership_cannot_accept_invitation()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't13', 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);

        $token = Str::random(32);
        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user@example.com',
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
        ]);

        $this->actingAs($user);
        // Will abort with 409
        $this->post("/invites/{$token}")->assertStatus(409);

        $invitation->refresh();
        $this->assertEquals('pending', $invitation->status);
    }

    public function test_user_with_inactive_membership_reactivates_and_accepts()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't14', 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_INACTIVE]);

        $token = Str::random(32);
        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user@example.com',
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
        ]);

        $this->actingAs($user);
        $this->post("/invites/{$token}")->assertRedirect('/pending-approval');

        $invitation->refresh();
        $this->assertEquals('accepted', $invitation->status);

        $membership->refresh();
        $this->assertEquals(\App\Modules\Tenancy\Models\Membership::STATUS_PENDING, $membership->status);
        $this->assertTrue($membership->roles->contains($invitation->role_id));
    }
    public function test_new_user_starts_registration_via_invitation()
    {
        $token = Str::random(32);
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't15', 'is_active' => true]);
        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'newuser@example.com',
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
        ]);

        $response = $this->get("/invites/{$token}");
        $response->assertSessionHas('pending_invitation.email', 'newuser@example.com');
        $response->assertSessionHas('url.intended');

        $registerResponse = $this->get('/register');
        $registerResponse->assertInertia(fn ($page) => $page->where('inviteEmail', 'newuser@example.com'));
    }

    public function test_registration_with_different_email_is_rejected_if_invitation_pending()
    {
        session(['pending_invitation' => ['email' => 'newuser@example.com', 'token' => 'abc']]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'hacker@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseMissing('users', ['email' => 'hacker@example.com']);
    }

    public function test_registration_returns_to_invitation_original_url()
    {
        session([
            'pending_invitation' => ['email' => 'newuser@example.com', 'token' => 'abc'],
            'url.intended' => 'http://localhost/invites/abc'
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('http://localhost/invites/abc');
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_used_invitation_is_rejected()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Str::random(32);
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't16', 'is_active' => true]);
        TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user@example.com',
            'token_hash' => hash('sha256', $token),
            'status' => 'accepted',
        ]);

        $this->actingAs($user);
        $this->post("/invites/{$token}")->assertStatus(400); // Exception status if handled, or 500 depending on handler
    }

    public function test_user_with_rejected_membership_reactivates_and_accepts()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't17', 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE]);
        $membership = Membership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_REJECTED]);

        $token = Str::random(32);
        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user@example.com',
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
        ]);

        $this->actingAs($user);
        $this->post("/invites/{$token}")->assertRedirect('/pending-approval');

        $membership->refresh();
        $this->assertEquals(\App\Modules\Tenancy\Models\Membership::STATUS_PENDING, $membership->status);
    }

    public function test_tenant_admin_cannot_invite_to_organization_unit_outside_scope()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't18', 'is_active' => true]);
        $role = Role::create(['name' => 'Test', 'slug' => 'test-role18', 'tenant_id' => $tenant->id]);

        $unitA = \App\Modules\Tenancy\Models\OrganizationUnit::create(['tenant_id' => $tenant->id, 'name' => 'A', 'slug' => 'a', 'type' => 'secretaria']);
        $unitB = \App\Modules\Tenancy\Models\OrganizationUnit::create(['tenant_id' => $tenant->id, 'name' => 'B', 'slug' => 'b', 'type' => 'secretaria']);

        // Admin belongs to unitA, does not have global scope
        $admin = User::factory()->create();
        $membership = Membership::create(['user_id' => $admin->id, 'tenant_id' => $tenant->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE, 'organization_unit_id' => $unitA->id]);
        $permissions = \App\Modules\Tenancy\Models\Permission::whereIn('slug', [
            PermissionSlug::INVITATIONS_VIEW->value,
            PermissionSlug::INVITATIONS_MANAGE->value,
        ])->pluck('id');
        $role->permissions()->sync($permissions);
        $membership->roles()->sync([$role->id]);

        $this->actingAs($admin);
        session(['tenant_id' => $tenant->id]);

        // Attempting to invite to Unit B should fail validation
        $this->post('/invitations', [
            'email' => 'test@example.com',
            'role_id' => $role->id,
            'organization_unit_id' => $unitB->id
        ])->assertSessionHasErrors(['organization_unit_id']);
    }

    public function test_tenant_admin_can_invite_to_descendant_organization_unit()
    {
        Notification::fake();
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't19', 'is_active' => true]);
        $role = Role::create(['name' => 'Test', 'slug' => 'test-role19', 'tenant_id' => $tenant->id]);

        $unitA = \App\Modules\Tenancy\Models\OrganizationUnit::create(['tenant_id' => $tenant->id, 'name' => 'A', 'slug' => 'a', 'type' => 'secretaria']);
        $unitA1 = \App\Modules\Tenancy\Models\OrganizationUnit::create(['tenant_id' => $tenant->id, 'name' => 'A1', 'slug' => 'a1', 'parent_id' => $unitA->id, 'type' => 'departamento']);

        // Admin belongs to unitA
        $admin = User::factory()->create();
        $membership = Membership::create(['user_id' => $admin->id, 'tenant_id' => $tenant->id, 'status' => \App\Modules\Tenancy\Models\Membership::STATUS_ACTIVE, 'organization_unit_id' => $unitA->id]);
        $permissions = \App\Modules\Tenancy\Models\Permission::whereIn('slug', [
            PermissionSlug::INVITATIONS_VIEW->value,
            PermissionSlug::INVITATIONS_MANAGE->value,
        ])->pluck('id');
        $role->permissions()->sync($permissions);
        $membership->roles()->sync([$role->id]);

        $this->actingAs($admin);
        session(['tenant_id' => $tenant->id]);

        // Attempting to invite to Unit A1 should succeed
        $this->post('/invitations', [
            'email' => 'test@example.com',
            'role_id' => $role->id,
            'organization_unit_id' => $unitA1->id
        ])->assertRedirect();

        $this->assertDatabaseHas('tenant_invitations', [
            'email' => 'test@example.com',
            'organization_unit_id' => $unitA1->id
        ]);
    }

    public function test_forged_organization_unit_id_from_other_tenant_is_rejected()
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 't20', 'is_active' => true]);
        $otherTenant = Tenant::create(['name' => 'Test', 'slug' => 't21', 'is_active' => true]);
        $role = Role::create(['name' => 'Test', 'slug' => 'test-role20', 'tenant_id' => $tenant->id]);
        $foreignUnit = \App\Modules\Tenancy\Models\OrganizationUnit::create(['tenant_id' => $otherTenant->id, 'name' => 'Foreign', 'slug' => 'f', 'type' => 'secretaria']);

        $admin = $this->createAdmin($tenant);

        $this->actingAs($admin);
        session(['tenant_id' => $tenant->id]);

        $this->post('/invitations', [
            'email' => 'test@example.com',
            'role_id' => $role->id,
            'organization_unit_id' => $foreignUnit->id
        ])->assertSessionHasErrors(['organization_unit_id']);
    }
}
