<?php

namespace App\Notifications;

use App\Support\RegistroSeguridad;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al correo ANTERIOR de que el correo de la cuenta se cambió desde «Mi perfil» (ProfileController::update).
 * El correo nuevo va enmascarado: si alguien ajeno lo cambió, el aviso no le muestra a quién.
 */
class CorreoCambiadoNotification extends Notification
{
    public function __construct(
        public readonly string $nombres,
        public readonly string $correoNuevo,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $correo = RegistroSeguridad::enmascararCorreo($this->correoNuevo);

        return (new MailMessage)
            ->subject('El correo de tu cuenta cambió – POL-Bolivia')
            ->greeting("Hola, {$this->nombres}:")
            ->line("El correo de tu cuenta de POL-Bolivia se cambió a {$correo}. Si no fuiste tú, contacta a la administración.")
            ->line('Desde ahora, los avisos y los enlaces para restablecer la contraseña llegarán al correo nuevo.');
    }
}
