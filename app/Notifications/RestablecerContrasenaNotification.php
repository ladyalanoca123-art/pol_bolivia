<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enlace para restablecer la contraseña, en español y con la identidad de POL-Bolivia. Reemplaza a la
 * notificación de Laravel (User::sendPasswordResetNotification). La duración del enlace se lee de
 * config/auth.php; el enlace se arma con la ruta password.reset y la URL de la aplicación (APP_URL).
 */
class RestablecerContrasenaNotification extends Notification
{
    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $minutos = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Restablece tu contraseña – POL-Bolivia')
            ->greeting("Hola, {$notifiable->nombres}:")
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta de POL-Bolivia. Para elegir una nueva, usa este botón:')
            ->action('Restablecer contraseña', $this->enlace($notifiable))
            ->line('Este enlace dura '.($minutos === 1 ? '1 minuto' : "{$minutos} minutos").' y solo se puede usar una vez.')
            ->line('Si no pediste este cambio, ignora este mensaje; tu contraseña no cambiará.');
    }

    public function enlace(User $notifiable): string
    {
        return route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
    }
}
