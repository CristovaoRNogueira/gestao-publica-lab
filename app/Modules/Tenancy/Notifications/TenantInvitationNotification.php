<?php

namespace App\Modules\Tenancy\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Modules\Tenancy\Models\TenantInvitation;

class TenantInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly TenantInvitation $invitation,
        public readonly string $token
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenantName = $this->invitation->tenant->name;
        $inviterName = $this->invitation->inviter->name;
        $url = url('/invites/' . $this->token);

        return (new MailMessage)
            ->subject("Convite para participar de {$tenantName}")
            ->line("Você foi convidado por {$inviterName} para participar de {$tenantName}.")
            ->action('Aceitar Convite', $url)
            ->line("Este convite expira em " . $this->invitation->expires_at->format('d/m/Y H:i') . ".");
    }
}
