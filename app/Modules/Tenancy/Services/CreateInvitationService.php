<?php

namespace App\Modules\Tenancy\Services;

use App\Models\User;
use App\Modules\Tenancy\Models\Role;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Modules\Tenancy\Notifications\TenantInvitationNotification;
use Illuminate\Validation\ValidationException;

class CreateInvitationService
{
    public function execute(string $email, int $roleId, int $tenantId, User $inviter): TenantInvitation
    {
        $normalizedEmail = strtolower(trim($email));

        // Ensure role belongs to tenant
        $role = Role::where('id', $roleId)->where('tenant_id', $tenantId)->firstOrFail();

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
