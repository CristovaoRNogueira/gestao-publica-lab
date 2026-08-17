<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class AccountActivationNotification extends ResetPassword
{
    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Convite Administrativo - Gestão Pública Lab')
            ->line('Você foi adicionado à plataforma Gestão Pública Lab por um administrador.')
            ->line('Para acessar a plataforma, você precisa definir sua senha inicial.')
            ->action('Definir Minha Senha', $url)
            ->line('Este link de definição de senha expirará em ' . config('auth.passwords.'.config('auth.defaults.passwords').'.expire') . ' minutos.')
            ->line('Se você não esperava este convite, nenhuma ação é necessária.');
    }
}
