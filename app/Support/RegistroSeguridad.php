<?php

namespace App\Support;

use App\Models\User;
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
 * Solo se guarda: evento, ID de usuario, correo intentado (fallos y bloqueos), rol y resultado (eliminación de
 * cuenta), acción y roles (cambios de administrador), quién ejecutó un comando (usuario del sistema y equipo), IP,
 * agente de usuario (máx. 255 caracteres) y fecha. NUNCA la contraseña ni otras credenciales.
 * Eventos: login, login_fallido, bloqueo, logout, registro_honeypot, eliminacion_cuenta, rol_administrador,
 * administrador_principal, recuperacion_solicitada (correo enmascarado si no hay cuenta) y contrasena_restablecida.
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

    /**
     * Intento de eliminar la propia cuenta desde el perfil (ProfileController::destroy y el límite "eliminar-cuenta").
     * Resultados: eliminada, bloqueada_por_rol, bloqueada_por_historial, contrasena_incorrecta y limite_de_intentos.
     * Se guarda el rol y el resultado, nunca la contraseña escrita.
     */
    public function eliminacionCuenta(?User $usuario, string $resultado): void
    {
        $this->registrar('eliminacion_cuenta', $usuario?->getAuthIdentifier(), null, [
            'rol' => $usuario?->rol,
            'resultado' => $resultado,
        ]);
    }

    /**
     * Un usuario pasó a ser administrador (accion=promovido) o dejó de serlo (accion=retirado), desde la consola
     * (pol:promover-administrador y pol:retirar-administrador). Incluye quién lo ejecutó (usuario_so y equipo).
     */
    public function rolAdministrador(User $usuario, string $accion, string $rolAnterior, string $rolNuevo): void
    {
        $this->registrar('rol_administrador', $usuario->getAuthIdentifier(), null, [
            'accion' => $accion,
            'rol_anterior' => $rolAnterior,
            'rol_nuevo' => $rolNuevo,
            ...EjecutorConsola::identificar(),
        ]);
    }

    /**
     * El rol de administrador principal pasó a $nuevo (pol:transferir-principal). $anterior es null si no había
     * ninguno. El anterior sigue siendo administrador. Incluye quién lo ejecutó (usuario_so y equipo).
     */
    public function administradorPrincipal(User $nuevo, ?User $anterior): void
    {
        $this->registrar('administrador_principal', $nuevo->getAuthIdentifier(), null, [
            'principal_anterior_id' => $anterior?->getAuthIdentifier(),
            ...EjecutorConsola::identificar(),
        ]);
    }

    /**
     * Solicitud del enlace para restablecer la contraseña. Resultados: enviado, correo_inexistente,
     * cuenta_desactivada y limite. Si hay cuenta se guarda su ID; si no, el correo ENMASCARADO (enmascararCorreo()).
     * Nunca se guarda el token.
     */
    public function recuperacionSolicitada(?User $usuario, ?string $correo, string $resultado): void
    {
        $this->registrar('recuperacion_solicitada', $usuario?->getAuthIdentifier(),
            $usuario === null && $correo !== null ? self::enmascararCorreo($correo) : null,
            ['resultado' => $resultado],
        );
    }

    /** La contraseña se restableció con un enlace válido (nunca se guarda la contraseña ni el token). */
    public function contrasenaRestablecida(User $usuario): void
    {
        $this->registrar('contrasena_restablecida', $usuario->getAuthIdentifier());
    }

    /** "juan.perez@gmail.com" → "ju***@gmail.com". Sin "@" válido: "***". */
    public static function enmascararCorreo(string $correo): string
    {
        $correo = trim($correo);
        $arroba = mb_strrpos($correo, '@');

        if ($arroba === false || $arroba === 0) {
            return '***';
        }

        return mb_substr($correo, 0, min(2, $arroba)).'***'.mb_substr($correo, $arroba);
    }

    private function registrar(string $evento, mixed $usuarioId, mixed $correo = null, array $detalles = []): void
    {
        $contexto = array_filter([
            'evento' => $evento,
            'usuario_id' => $usuarioId,
            'correo' => is_string($correo) ? mb_substr($correo, 0, 255) : null,
            ...$detalles,
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
