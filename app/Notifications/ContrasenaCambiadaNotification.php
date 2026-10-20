<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de que la contraseña cambió: al restablecerla con el enlace (Auth\NewPasswordController, se cierran todas
 * las sesiones) o desde «Mi perfil» (Auth\PasswordController, $desdePerfil: se cierran las de otros dispositivos).
 */
class ContrasenaCambiadaNotification extends Notification
{
    public function __construct(public readonly bool $desdePerfil = false) {}

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
            ->line($this->desdePerfil
                ? 'Por seguridad, se cerraron las sesiones que tenías abiertas en otros dispositivos.'
                : 'Por seguridad, se cerraron las sesiones que tenías abiertas.')
            ->action('Iniciar sesión', route('login'));
    }
}
