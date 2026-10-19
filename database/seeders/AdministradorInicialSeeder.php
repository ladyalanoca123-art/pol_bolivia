<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea el administrador inicial SOLO si todavía no existe. Si ya existe no modifica nada (ni la contraseña,
 * ni el nombre, ni el rol): ejecutarlo de nuevo, aunque sea por error, no tiene efectos.
 * Los demás administradores se agregan o retiran con pol:promover-administrador y pol:retirar-administrador.
 */
class AdministradorInicialSeeder extends Seeder
{
    public const CORREO = 'ladyalanoca123@gmail.com';

    public const LONGITUD_MINIMA_CLAVE = 8;

    public function run(): void
    {
        if (User::query()->where('email', self::CORREO)->exists()) {
            $this->command?->info('El administrador inicial ya existe; no se modificó.');

            return;
        }

        $password = env('INITIAL_ADMIN_PASSWORD');

        if (! is_string($password) || mb_strlen($password) < self::LONGITUD_MINIMA_CLAVE) {
            throw new \RuntimeException(
                'No se creó el administrador inicial: define INITIAL_ADMIN_PASSWORD en el archivo .env con al menos '
                .self::LONGITUD_MINIMA_CLAVE.' caracteres.'
            );
        }

        (new User)->forceFill([
            'nombres' => 'Lady',
            'apellidos' => 'Alanoca',
            'email' => self::CORREO,
            'telefono' => null,
            'password' => Hash::make($password),
            'rol' => 'administrador',
            'estado' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->command?->info('Administrador inicial creado.');
    }
}
