<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso de que la contraseña se restableció (App\Http\Controllers\Auth\NewPasswordController). */
class ContrasenaCambiadaNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu contraseña fue cambiada – POL-Bolivia')
            ->greeting("Hola, {$notifiable->nombres}:")
            ->line('Tu contraseña de POL-Bolivia fue cambiada. Si no fuiste tú, contacta a la administración.')
            ->line('Por seguridad, se cerraron las sesiones que tenías abiertas.')
            ->action('Iniciar sesión', route('login'));
    }
}
