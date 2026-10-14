<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_adopcion', function (Blueprint $table) {
            // Respuestas completas del cuestionario A2 (ver app/Support/CuestionarioAdopcion.php).
            $table->jsonb('respuestas')->nullable();
            $table->string('version_cuestionario', 20)->nullable();
            // Copia de la evaluación calculada al enviar la solicitud.
            $table->string('nivel_alerta', 10)->nullable()->index();
            $table->jsonb('alertas')->nullable();
            $table->string('version_reglas', 20)->nullable();
        });

        DB::statement(
            'ALTER TABLE solicitudes_adopcion ADD CONSTRAINT solicitudes_nivel_alerta_valido '
            ."CHECK (nivel_alerta IS NULL OR nivel_alerta IN ('verde', 'amarillo', 'rojo'))"
        );

        // El cuestionario nuevo ya no pregunta por "espacio adecuado";
        // la columna se conserva para las solicitudes del formato anterior.
        DB::statement('ALTER TABLE solicitudes_adopcion ALTER COLUMN tiene_espacio_adecuado DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE solicitudes_adopcion DROP CONSTRAINT IF EXISTS solicitudes_nivel_alerta_valido');

        Schema::table('solicitudes_adopcion', function (Blueprint $table) {
            $table->dropColumn(['respuestas', 'version_cuestionario', 'nivel_alerta', 'alertas', 'version_reglas']);
        });
    }
};
