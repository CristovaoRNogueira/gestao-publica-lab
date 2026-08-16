<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Support\Facades\DB;

class RevokeInvitationService
{
    public function execute(TenantInvitation $invitation): TenantInvitation
    {
        return DB::transaction(function () use ($invitation) {
            $invitation = TenantInvitation::where('id', $invitation->id)->lockForUpdate()->firstOrFail();

            if ($invitation->status !== 'pending') {
                throw new \Exception('Apenas convites pendentes podem ser revogados.');
            }

            $invitation->update([
                'status' => 'revoked',
                'revoked_at' => now(),
            ]);

            return $invitation;
        });
    }
}
