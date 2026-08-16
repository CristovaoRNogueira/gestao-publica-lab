<?php

namespace App\Modules\Tenancy\Services;

use App\Models\User;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Modules\Tenancy\Notifications\TenantInvitationNotification;
use Illuminate\Validation\ValidationException;
use App\Modules\Tenancy\Enums\PermissionSlug;

class CreateInvitationService
{
    public function execute(string $email, int $roleId, int $tenantId, User $inviter): TenantInvitation
    {
        $normalizedEmail = strtolower(trim($email));

        // Ensure role belongs to tenant
        $role = Role::with('permissions')->where('id', $roleId)->where('tenant_id', $tenantId)->firstOrFail();

        // Prevent Privilege Escalation
        $requiresAdmin = $role->permissions->contains('slug', PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value);
        if ($requiresAdmin) {
            $inviterMembership = $inviter->memberships()->where('tenant_id', $tenantId)->where('is_active', true)->first();
            if (!$inviterMembership || !$inviterMembership->hasPermission(PermissionSlug::MEMBERSHIPS_ROLES_MANAGE->value)) {
                throw ValidationException::withMessages(['role_id' => 'Você não tem permissão para convidar administradores.']);
            }
        }

        // Check if user is already a member (case-insensitive)
        $user = User::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();
        if ($user && $user->memberships()->where('tenant_id', $tenantId)->exists()) {
            throw ValidationException::withMessages(['email' => 'Usuário já é membro deste Tenant.']);
        }

        // Validate pending duplication
        $existing = TenantInvitation::where('tenant_id', $tenantId)
            ->where('email', $normalizedEmail)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            throw ValidationException::withMessages(['email' => 'Já existe um convite pendente para este e-mail.']);
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $invitation = TenantInvitation::create([
            'tenant_id' => $tenantId,
            'email' => $normalizedEmail,
            'role_id' => $role->id,
            'token_hash' => $tokenHash,
            'status' => 'pending',
            'invited_by_user_id' => $inviter->id,
            'expires_at' => now()->addHours(72),
        ]);

        Notification::route('mail', $normalizedEmail)
            ->notify(new TenantInvitationNotification($invitation, $token));

        return $invitation;
    }
}
