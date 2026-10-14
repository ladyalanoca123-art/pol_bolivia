<?php

namespace App\Support;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Registra los eventos de autenticación en el canal de log "seguridad" (storage/logs/seguridad-AAAA-MM-DD.log).
 *
 * Se usa un archivo y no la tabla "auditorias" porque esa tabla registra cambios de datos
 * (creado / actualizado / eliminado) y su pantalla solo filtra esas acciones; además, los intentos
 * fallidos de un ataque no deben generar escrituras en la base de datos.
 *
 * Solo se guarda: evento, ID de usuario, correo intentado (fallos y bloqueos), IP, agente de usuario
 * (máx. 255 caracteres) y fecha. NUNCA la contraseña ni otras credenciales.
 * Eventos: login, login_fallido, bloqueo, logout y registro_honeypot.
 */
class RegistroSeguridad
{
    public function __construct(private Request $request)
    {
    }

    public function login(Login $evento): void
    {
        $this->registrar('login', $evento->user?->getAuthIdentifier());
    }

    public function fallido(Failed $evento): void
    {
        // De las credenciales solo se toma el correo; la contraseña se descarta.
        $this->registrar('login_fallido', $evento->user?->getAuthIdentifier(), $evento->credentials['email'] ?? null);
    }

    public function bloqueo(Lockout $evento): void
    {
        $this->registrar('bloqueo', null, $evento->request->input('email'));
    }

    public function logout(Logout $evento): void
    {
        $this->registrar('logout', $evento->user?->getAuthIdentifier());
    }

    /**
     * Registro rechazado porque se completó el campo trampa (honeypot) "sitio_web".
     * No se guarda lo que se escribió ni ningún otro dato del formulario: solo IP, agente y fecha.
     * El volumen queda acotado por el límite throttle:registro (5 por minuto y 20 por hora por IP).
     */
    public function honeypotRegistro(): void
    {
        $this->registrar('registro_honeypot', null);
    }

    private function registrar(string $evento, mixed $usuarioId, mixed $correo = null): void
    {
        $contexto = array_filter([
            'evento' => $evento,
            'usuario_id' => $usuarioId,
            'correo' => is_string($correo) ? mb_substr($correo, 0, 255) : null,
            'ip' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 255),
            'fecha' => now()->toIso8601String(),
        ], fn ($valor) => $valor !== null && $valor !== '');

        // Si el archivo no se puede escribir (permisos, disco lleno), la autenticación no debe fallar.
        // Se deja un aviso genérico en el log principal, sin el correo ni otros datos de la petición.
        try {
            Log::channel('seguridad')->info('seguridad.'.$evento, $contexto);
        } catch (\Throwable) {
            rescue(fn () => Log::warning('No se pudo escribir el registro de seguridad.', ['evento' => $evento]), report: false);
        }
    }
}
