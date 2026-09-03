<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdministradorInicialSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('INITIAL_ADMIN_PASSWORD');

        if (! is_string($password) || $password === '') {
            throw new \RuntimeException(
                'Define INITIAL_ADMIN_PASSWORD en el archivo .env.'
            );
        }

        User::updateOrCreate(
            ['correo' => 'ladyalanoca123@gmail.com'],
            [
                'nombres' => 'Lady',
                'apellidos' => 'Alanoca',
                'telefono' => null,
                'password' => Hash::make($password),
                'rol' => 'administrador',
                'estado' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}