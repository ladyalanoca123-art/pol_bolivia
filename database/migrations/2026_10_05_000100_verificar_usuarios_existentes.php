<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La verificación de correo se activó el 5 de octubre de 2026.
     * Las cuentas creadas antes se consideran verificadas para no bloquearlas;
     * las cuentas nuevas sí deben confirmar su correo.
     */
    public function up(): void
    {
        DB::table('usuarios')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // No se revierte: no es posible saber qué cuentas estaban sin verificar.
    }
};
