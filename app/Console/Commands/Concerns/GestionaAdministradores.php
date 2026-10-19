<?php

namespace App\Console\Commands\Concerns;

use App\Models\User;

/**
 * Partes comunes de pol:promover-administrador y pol:retirar-administrador: buscar al usuario por correo,
 * mostrar sus datos y pedir confirmación en español. Nunca se pide, muestra ni cambia una contraseña.
 */
trait GestionaAdministradores
{
    private const ROLES = ['administrador' => 'Administrador', 'voluntario' => 'Voluntario', 'ciudadano' => 'Ciudadano'];

    private function buscarUsuario(string $correo): ?User
    {
        return User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($correo))])->first();
    }

    private function mostrarUsuario(User $usuario): void
    {
        $this->table(['Nombre', 'Correo', 'Rol actual', 'Estado'], [[
            trim("{$usuario->nombres} {$usuario->apellidos}"),
            $usuario->email,
            self::ROLES[$usuario->rol] ?? $usuario->rol,
            $usuario->estado ? 'Activa' : 'Desactivada',
        ]]);
    }

    /** Pide "sí" o "no" (con --force no pregunta). Cualquier otra respuesta cuenta como "no". */
    private function confirmar(string $pregunta): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $respuesta = mb_strtolower(trim((string) $this->ask("{$pregunta} Escribe «sí» para confirmar o «no» para cancelar", 'no')));

        return in_array($respuesta, ['sí', 'si', 's'], true);
    }
}
