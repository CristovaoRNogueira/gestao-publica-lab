<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Modules\Tenancy\Notifications\TenantInvitationNotification;
use Illuminate\Support\Str;

class ResendInvitationService
{
    public function execute(TenantInvitation $invitation): TenantInvitation
    {
        return DB::transaction(function () use ($invitation) {
            $invitation = TenantInvitation::where('id', $invitation->id)->lockForUpdate()->firstOrFail();

            if ($invitation->status !== 'pending') {
                throw new \Exception('Apenas convites pendentes podem ser reenviados.');
            }

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $invitation->update([
                'token_hash' => $tokenHash,
                'expires_at' => now()->addHours(72),
            ]);

            Notification::route('mail', $invitation->email)
                ->notify(new TenantInvitationNotification($invitation, $token));

            return $invitation;
        });
    }
}
