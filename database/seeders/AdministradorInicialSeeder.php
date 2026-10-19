<?php

namespace Database\Seeders;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Crea el administrador inicial SOLO si todavía no existe. Si ya existe no modifica sus datos (ni la contraseña,
 * ni el nombre, ni el rol): ejecutarlo de nuevo, aunque sea por error, no tiene efectos.
 * Única excepción: si no hay ningún administrador principal y el inicial es un administrador activo, lo marca
 * como principal (es_principal), sin tocar nada más. Nunca crea un segundo principal ni "repara" su rol.
 * La contraseña (config pol.administrador_inicial.password = INITIAL_ADMIN_PASSWORD) debe cumplir la misma
 * política que el registro web. Los demás administradores: pol:promover-administrador y pol:retirar-administrador.
 */
class AdministradorInicialSeeder extends Seeder
{
    public const CORREO = 'ladyalanoca123@gmail.com';

    public function run(): void
    {
        $administrador = User::query()->where('email', self::CORREO)->first();
        $hayPrincipal = User::query()->where('es_principal', true)->exists();

        if ($administrador === null) {
            $this->crear(marcarPrincipal: ! $hayPrincipal);

            return;
        }

        if (! $hayPrincipal && $administrador->estado && $administrador->hasRole('administrador')) {
            $administrador->forceFill(['es_principal' => true])->save();
            $this->command?->info('El administrador inicial ya existe; solo se marcó como administrador principal (no se cambió ningún otro dato).');

            return;
        }

        $this->command?->info('El administrador inicial ya existe; no se modificó.');

        if (! $hayPrincipal) {
            $this->command?->warn('No hay ningún administrador principal: desígnalo con php artisan pol:transferir-principal {correo}.');
        }
    }

    private function crear(bool $marcarPrincipal): void
    {
        $password = config('pol.administrador_inicial.password');

        // La misma política (Password::defaults()) y los mismos mensajes que el registro web.
        $validacion = Validator::make(
            ['password' => is_string($password) ? $password : ''],
            ['password' => ['required', 'string', Password::defaults()]],
            (new RegisterRequest)->messages(),
        );

        if ($validacion->fails()) {
            throw new \RuntimeException(
                'No se creó el administrador inicial: INITIAL_ADMIN_PASSWORD (archivo .env) no cumple la política de contraseñas. '
                .implode(' ', $validacion->errors()->all())
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
            'es_principal' => $marcarPrincipal,
            'email_verified_at' => now(),
        ])->save();

        $this->command?->info($marcarPrincipal ? 'Administrador inicial creado como administrador principal.' : 'Administrador inicial creado.');
    }
}
