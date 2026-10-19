<?php

namespace App\Support;

/**
 * Quién ejecuta un comando de consola: el usuario del sistema operativo y el nombre del equipo.
 * Los comandos de administradores lo guardan en el log "seguridad" (App\Support\RegistroSeguridad) y, mientras
 * corre durante(), también en la auditoría (columna user_agent: "consola: usuario@equipo"), porque desde la
 * consola no hay un usuario web que registrar.
 */
final class EjecutorConsola
{
    private static bool $activo = false;

    /** @return array{usuario_so: string, equipo: string} */
    public static function identificar(): array
    {
        $usuario = getenv('USERNAME') ?: getenv('USER') ?: null;

        if (! $usuario && function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $usuario = posix_getpwuid(posix_geteuid())['name'] ?? null;
        }

        return [
            'usuario_so' => mb_substr((string) ($usuario ?: get_current_user() ?: 'desconocido'), 0, 100),
            'equipo' => mb_substr((string) (gethostname() ?: php_uname('n') ?: 'desconocido'), 0, 100),
        ];
    }

    /** Texto para la auditoría mientras corre durante(); null fuera de él. */
    public static function descripcion(): ?string
    {
        if (! self::$activo) {
            return null;
        }

        ['usuario_so' => $usuario, 'equipo' => $equipo] = self::identificar();

        return "consola: {$usuario}@{$equipo}";
    }

    /**
     * Ejecuta $operacion identificando al ejecutor en la auditoría de los cambios que haga.
     *
     * @template T
     *
     * @param  callable(): T  $operacion
     * @return T
     */
    public static function durante(callable $operacion): mixed
    {
        self::$activo = true;

        try {
            return $operacion();
        } finally {
            self::$activo = false;
        }
    }
}
