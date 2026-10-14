<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuestionario V1 de postulación de voluntariado (10 preguntas).
     * Las columnas son opcionales en la base solo para conservar las postulaciones
     * anteriores ya aprobadas (vinculadas a voluntarios activos); el formulario las exige.
     * disponibilidad, habilidades y zona_preferencia se siguen llenando porque
     * PostgreSQL las copia al perfil del voluntario al aprobar (procesar_aprobacion_postulacion).
     */
    public function up(): void
    {
        Schema::table('postulaciones_voluntariado', function (Blueprint $table) {
            $table->boolean('experiencia_previa')->nullable();
            $table->text('experiencia_detalle')->nullable();
            $table->jsonb('areas_interes')->nullable();
            $table->jsonb('dias_disponibles')->nullable();
            $table->jsonb('franjas_horarias')->nullable();
            $table->boolean('confirma_mayor_edad')->nullable();
            $table->boolean('declara_informacion_veraz')->nullable();
            $table->string('version_cuestionario', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('postulaciones_voluntariado', function (Blueprint $table) {
            $table->dropColumn([
                'experiencia_previa', 'experiencia_detalle', 'areas_interes', 'dias_disponibles',
                'franjas_horarias', 'confirma_mayor_edad', 'declara_informacion_veraz', 'version_cuestionario',
            ]);
        });
    }
};
