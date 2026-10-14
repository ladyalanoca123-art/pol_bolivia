<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimina el formato anterior de solicitud de adopción.
     * Desde ahora toda solicitud usa el cuestionario A2 (respuestas + alertas obligatorias).
     */
    public function up(): void
    {
        if (DB::table('solicitudes_adopcion')->whereNull('respuestas')->exists()) {
            throw new RuntimeException(
                'Hay solicitudes con el formato anterior. Elimínelas o conviértalas antes de ejecutar esta migración.'
            );
        }

        Schema::table('solicitudes_adopcion', function ($table) {
            $table->dropColumn(['tiene_espacio_adecuado', 'comentarios_solicitante']);
        });

        foreach (['respuestas', 'version_cuestionario', 'nivel_alerta', 'alertas', 'version_reglas'] as $columna) {
            DB::statement("ALTER TABLE solicitudes_adopcion ALTER COLUMN {$columna} SET NOT NULL");
        }
    }

    public function down(): void
    {
        foreach (['respuestas', 'version_cuestionario', 'nivel_alerta', 'alertas', 'version_reglas'] as $columna) {
            DB::statement("ALTER TABLE solicitudes_adopcion ALTER COLUMN {$columna} DROP NOT NULL");
        }

        Schema::table('solicitudes_adopcion', function ($table) {
            $table->boolean('tiene_espacio_adecuado')->nullable();
            $table->text('comentarios_solicitante')->nullable();
        });
    }
};
