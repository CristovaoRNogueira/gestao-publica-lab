<?php

namespace App\Modules\Tenancy\Services;

use App\Models\User;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Support\Facades\DB;

class AcceptInvitationService
{
    public function execute(string $token, User $user): TenantInvitation
    {
        $tokenHash = hash('sha256', $token);

        return DB::transaction(function () use ($tokenHash, $user) {
            $invitation = TenantInvitation::where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (!$invitation) {
                throw new \Exception('Convite não encontrado ou inválido.');
            }

            if ($invitation->status !== 'pending') {
                throw new \Exception('Este convite não está mais pendente.');
            }

            if ($invitation->expires_at < now()) {
                $invitation->update(['status' => 'expired']);
                throw new \Exception('Este convite expirou.');
            }

            if (strtolower(trim($invitation->email)) !== strtolower(trim($user->email))) {
                throw new \Exception('O e-mail do usuário logado não corresponde ao convite.', 403);
            }

            // Validate if role still exists and belongs to tenant
            if (!$invitation->role || $invitation->role->tenant_id !== $invitation->tenant_id) {
                throw new \Exception('A permissão associada a este convite não é mais válida.');
            }

            // Create membership or activate existing inactive one
            $membership = $user->memberships()->firstOrCreate(
                ['tenant_id' => $invitation->tenant_id],
                ['is_active' => true]
            );

            // If it already existed and was active, throw an exception
            if (!$membership->wasRecentlyCreated && $membership->is_active) {
                throw new \Exception('Usuário já é membro ativo deste Tenant.', 409);
            }

            $membership->update(['is_active' => true]);

            // Sync the role
            $membership->roles()->syncWithoutDetaching([$invitation->role_id]);

            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            return $invitation;
        });
    }
}
