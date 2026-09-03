<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $path = database_path('sql/pol_bolivia.sql');

        if (! file_exists($path)) {
        throw new \RuntimeException(
                'No se encontró el archivo database/sql/pol_bolivia.sql'
            );
        }

        $sql = file_get_contents($path);

        /*
         * Las tablas ya son creadas por las migraciones anteriores.
         * Del SQL original se quitan solamente los CREATE TABLE;
         * se conservan índices, constraints, funciones, triggers y vistas.
         */
        $sql = preg_replace(
            '/CREATE TABLE\s+'
            . '(usuarios|mascotas|fotos_mascota|solicitudes_adopcion|'
            . 'seguimientos_adopcion|visitas_seguimiento|incidencias|'
            . 'postulaciones_voluntariado|voluntarios)'
            . '\s*\(.*?\n\);\s*/is',
            '',
            $sql
        );

        if ($sql === null) {
            throw new RuntimeException(
                'No fue posible preparar la lógica de PostgreSQL.'
            );
        }

        DB::unprepared($sql);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP VIEW IF EXISTS vista_participacion_voluntarios;
            DROP VIEW IF EXISTS vista_seguimientos_activos;
            DROP VIEW IF EXISTS vista_mascotas_disponibles_catalogo;
            DROP VIEW IF EXISTS vista_adopciones_realizadas;
            DROP VIEW IF EXISTS vista_resumen_general;
            DROP VIEW IF EXISTS mascotas_con_rango;

            DROP FUNCTION IF EXISTS procesar_baja_voluntario() CASCADE;
            DROP FUNCTION IF EXISTS proteger_voluntario_asignado() CASCADE;
            DROP FUNCTION IF EXISTS procesar_aprobacion_postulacion() CASCADE;
            DROP FUNCTION IF EXISTS validar_postulante_valido() CASCADE;
            DROP FUNCTION IF EXISTS validar_voluntario_desde_postulacion() CASCADE;
            DROP FUNCTION IF EXISTS validar_cambio_estado_postulacion() CASCADE;
            DROP FUNCTION IF EXISTS validar_revisor_postulacion() CASCADE;
            DROP FUNCTION IF EXISTS procesar_aceptacion_solicitud() CASCADE;
            DROP FUNCTION IF EXISTS proteger_estado_mascota() CASCADE;
            DROP FUNCTION IF EXISTS validar_cierre_seguimiento() CASCADE;
            DROP FUNCTION IF EXISTS validar_atendida_por_incidencia() CASCADE;
            DROP FUNCTION IF EXISTS validar_cambio_estado_incidencia() CASCADE;
            DROP FUNCTION IF EXISTS validar_atendida_por_incidencia() CASCADE;
            DROP FUNCTION IF EXISTS validar_reportante_incidencia() CASCADE;
            DROP FUNCTION IF EXISTS validar_visita_del_mismo_seguimiento() CASCADE;
            DROP FUNCTION IF EXISTS validar_limite_visitas() CASCADE;
            DROP FUNCTION IF EXISTS validar_visita_seguimiento() CASCADE;
            DROP FUNCTION IF EXISTS validar_rol_voluntario() CASCADE;
            DROP FUNCTION IF EXISTS validar_solicitud_aceptada_para_seguimiento() CASCADE;
            DROP FUNCTION IF EXISTS validar_cambio_estado_solicitud() CASCADE;
            DROP FUNCTION IF EXISTS validar_datos_inmutables_solicitud() CASCADE;
            DROP FUNCTION IF EXISTS validar_nueva_solicitud_pendiente() CASCADE;
            DROP FUNCTION IF EXISTS validar_solicitante_valido() CASCADE;
            DROP FUNCTION IF EXISTS validar_mascota_disponible() CASCADE;
            DROP FUNCTION IF EXISTS validar_minimo_fotos() CASCADE;
            DROP FUNCTION IF EXISTS validar_multimedia_mascota_publicada() CASCADE;
            DROP FUNCTION IF EXISTS gestionar_foto_principal() CASCADE;
            DROP FUNCTION IF EXISTS validar_limite_multimedia() CASCADE;
            DROP FUNCTION IF EXISTS actualizar_updated_at() CASCADE;
        SQL);
    }
};