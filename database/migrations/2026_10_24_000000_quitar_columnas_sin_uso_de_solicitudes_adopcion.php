<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quita de solicitudes_adopcion las columnas que dejaron de usarse al simplificar el flujo de adopción:
 * entrevista (4), visita previa (3), fecha de entrega acordada y «permitir otra adopción». Con ellas se van
 * sus restricciones CHECK, y las dos funciones que las mencionaban se reemplazan por versiones sin ellas.
 *
 * Datos: en la base real solo la solicitud #5 (cancelada) tenía una entrevista registrada; ese dato se conserva
 * en la auditoría, que no se puede modificar ni borrar.
 */
return new class extends Migration
{
    private const COLUMNAS = [
        'entrevista_fecha', 'entrevista_medio', 'entrevista_resultado', 'entrevista_notas',
        'visita_previa_fecha', 'visita_previa_resultado', 'visita_previa_notas',
        'fecha_entrega_acordada', 'permitir_otra_adopcion',
    ];

    public function up(): void
    {
        // Primero las funciones: si no, seguirían leyendo columnas que ya no existen.
        DB::unprepared(self::FUNCIONES_NUEVAS);

        DB::unprepared(<<<'SQL'
            ALTER TABLE solicitudes_adopcion
                DROP CONSTRAINT IF EXISTS solicitudes_entrevista_medio_valido,
                DROP CONSTRAINT IF EXISTS solicitudes_entrevista_resultado_valido,
                DROP CONSTRAINT IF EXISTS solicitudes_visita_previa_resultado_valido,
                DROP CONSTRAINT IF EXISTS solicitudes_entrevista_completa,
                DROP CONSTRAINT IF EXISTS solicitudes_visita_previa_completa;
        SQL);

        DB::statement('ALTER TABLE solicitudes_adopcion '.implode(', ', array_map(fn ($c) => "DROP COLUMN IF EXISTS {$c}", self::COLUMNAS)));
    }

    /** Vuelve a crear las columnas (vacías) con sus restricciones y las funciones anteriores. Los valores borrados no vuelven. */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE solicitudes_adopcion
                ADD COLUMN IF NOT EXISTS entrevista_fecha DATE NULL,
                ADD COLUMN IF NOT EXISTS entrevista_medio VARCHAR(20) NULL,
                ADD COLUMN IF NOT EXISTS entrevista_resultado VARCHAR(20) NULL,
                ADD COLUMN IF NOT EXISTS entrevista_notas TEXT NULL,
                ADD COLUMN IF NOT EXISTS visita_previa_fecha DATE NULL,
                ADD COLUMN IF NOT EXISTS visita_previa_resultado VARCHAR(20) NULL,
                ADD COLUMN IF NOT EXISTS visita_previa_notas TEXT NULL,
                ADD COLUMN IF NOT EXISTS fecha_entrega_acordada DATE NULL,
                ADD COLUMN IF NOT EXISTS permitir_otra_adopcion BOOLEAN NOT NULL DEFAULT FALSE;

            ALTER TABLE solicitudes_adopcion
                ADD CONSTRAINT solicitudes_entrevista_medio_valido CHECK (entrevista_medio IS NULL OR entrevista_medio IN ('llamada', 'whatsapp', 'presencial')),
                ADD CONSTRAINT solicitudes_entrevista_resultado_valido CHECK (entrevista_resultado IS NULL OR entrevista_resultado IN ('favorable', 'no_favorable')),
                ADD CONSTRAINT solicitudes_visita_previa_resultado_valido CHECK (visita_previa_resultado IS NULL OR visita_previa_resultado IN ('favorable', 'no_favorable')),
                ADD CONSTRAINT solicitudes_entrevista_completa CHECK (
                    (entrevista_fecha IS NULL AND entrevista_medio IS NULL AND entrevista_resultado IS NULL)
                    OR (entrevista_fecha IS NOT NULL AND entrevista_medio IS NOT NULL AND entrevista_resultado IS NOT NULL)),
                ADD CONSTRAINT solicitudes_visita_previa_completa CHECK ((visita_previa_fecha IS NULL) = (visita_previa_resultado IS NULL));
        SQL);

        DB::unprepared(self::FUNCIONES_ANTERIORES);
    }

    private const FUNCIONES_NUEVAS = <<<'SQL'
        CREATE OR REPLACE FUNCTION proteger_datos_solicitud()
        RETURNS TRIGGER AS $$
        BEGIN
            IF TG_OP = 'INSERT' THEN
                IF NEW.aprobada_at IS NOT NULL OR NEW.numero_acta IS NOT NULL
                   OR NEW.entregada_at IS NOT NULL OR NEW.compromiso_firmado
                   OR NEW.motivo_no_concretada IS NOT NULL THEN
                    RAISE EXCEPTION 'Una solicitud nueva no puede traer datos de aprobación, entrega ni cancelación';
                END IF;
                IF NEW.compromiso_foto_path IS NOT NULL OR NEW.motivo_cancelacion IS NOT NULL THEN
                    RAISE EXCEPTION 'Una solicitud nueva no puede traer evidencia ni motivo de cancelación';
                END IF;
                RETURN NEW;
            END IF;

            IF OLD.numero_acta IS NOT NULL AND NEW.numero_acta IS DISTINCT FROM OLD.numero_acta THEN
                RAISE EXCEPTION 'El número de acta no puede cambiar';
            END IF;
            IF OLD.aprobada_at IS NOT NULL AND NEW.aprobada_at IS DISTINCT FROM OLD.aprobada_at THEN
                RAISE EXCEPTION 'La fecha de aprobación no puede cambiar';
            END IF;
            -- Sin acta todavía: solo la aprobación la asigna (validar_cambio_estado_solicitud la sobrescribe).
            IF OLD.numero_acta IS NULL AND (NEW.numero_acta IS NOT NULL OR NEW.aprobada_at IS NOT NULL)
               AND NOT (OLD.estado = 'en_espera' AND NEW.estado = 'aprobada') THEN
                RAISE EXCEPTION 'El número de acta y la fecha de aprobación solo se asignan al aprobar la solicitud';
            END IF;

            IF (NEW.compromiso_firmado, NEW.entregada_at)
               IS DISTINCT FROM (OLD.compromiso_firmado, OLD.entregada_at)
               AND NOT (OLD.estado = 'aprobada' AND NEW.estado = 'aceptada') THEN
                RAISE EXCEPTION 'Los datos de la entrega solo se registran al registrar la entrega';
            END IF;

            -- La foto del acta firmada se puede subir o reemplazar mientras está reservada y también después
            -- de registrar la entrega (lo habitual es firmar en la entrega y subir la foto luego).
            IF NEW.compromiso_foto_path IS DISTINCT FROM OLD.compromiso_foto_path
               AND NOT (OLD.estado = NEW.estado AND NEW.estado IN ('aprobada', 'aceptada')) THEN
                RAISE EXCEPTION 'La evidencia del compromiso solo se registra en una solicitud reservada o con la adopción concretada';
            END IF;

            IF NEW.motivo_cancelacion IS DISTINCT FROM OLD.motivo_cancelacion
               AND NOT (OLD.estado IN ('pendiente', 'en_espera', 'aprobada') AND NEW.estado = 'cancelada') THEN
                RAISE EXCEPTION 'El motivo de cancelación solo se registra al cancelar una solicitud activa';
            END IF;

            IF NEW.motivo_no_concretada IS DISTINCT FROM OLD.motivo_no_concretada
               AND NOT (OLD.estado = 'aprobada' AND NEW.estado = 'no_concretada') THEN
                RAISE EXCEPTION 'El motivo de la entrega no concretada solo se registra al marcar la entrega como no concretada';
            END IF;

            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;

        CREATE OR REPLACE FUNCTION procesar_aceptacion_solicitud()
        RETURNS TRIGGER AS $$
        BEGIN
            IF NEW.estado = OLD.estado THEN
                RETURN NEW;
            END IF;

            -- Aprobada: la mascota queda reservada para esta solicitud y sale del catálogo.
            IF NEW.estado = 'aprobada' THEN
                UPDATE mascotas SET estado = 'reservada' WHERE id = NEW.mascota_id;
            END IF;

            -- La entrega no se concretó o se canceló la solicitud aprobada: la mascota vuelve a estar disponible.
            IF OLD.estado = 'aprobada' AND NEW.estado IN ('no_concretada', 'cancelada') THEN
                UPDATE mascotas SET estado = 'disponible' WHERE id = NEW.mascota_id;
            END IF;

            IF NEW.estado = 'aceptada' THEN
                UPDATE mascotas SET estado = 'adoptada' WHERE id = NEW.mascota_id;

                -- Las demás solicitudes activas de la mascota se cierran.
                UPDATE solicitudes_adopcion
                SET estado = 'cerrada'
                WHERE mascota_id = NEW.mascota_id
                  AND id <> NEW.id
                  AND estado IN ('pendiente', 'en_espera');

                -- Las demás solicitudes activas del adoptante se cancelan.
                UPDATE solicitudes_adopcion
                SET estado = 'cancelada', motivo_cancelacion = 'Se concretó otra adopción'
                WHERE solicitante_id = NEW.solicitante_id
                  AND id <> NEW.id
                  AND estado IN ('pendiente', 'en_espera', 'aprobada');

                -- Crea el caso de seguimiento, copiando zona y horario que el ciudadano indicó en su solicitud.
                INSERT INTO seguimientos_adopcion (solicitud_id, zona_aproximada, horario_preferido)
                VALUES (NEW.id, NEW.zona_aproximada, NEW.horario_preferido);
            END IF;

            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
    SQL;

    /** Definiciones de 2026_10_23 (proteger_datos_solicitud) y 2026_10_20 (procesar_aceptacion_solicitud). */
    private const FUNCIONES_ANTERIORES = <<<'SQL'
        CREATE OR REPLACE FUNCTION proteger_datos_solicitud()
        RETURNS TRIGGER AS $$
        DECLARE
            hoy DATE := (CURRENT_TIMESTAMP AT TIME ZONE 'America/La_Paz')::date;
        BEGIN
            IF TG_OP = 'INSERT' THEN
                IF NEW.entrevista_fecha IS NOT NULL OR NEW.visita_previa_fecha IS NOT NULL
                   OR NEW.aprobada_at IS NOT NULL OR NEW.numero_acta IS NOT NULL
                   OR NEW.entregada_at IS NOT NULL OR NEW.compromiso_firmado
                   OR NEW.fecha_entrega_acordada IS NOT NULL OR NEW.motivo_no_concretada IS NOT NULL THEN
                    RAISE EXCEPTION 'Una solicitud nueva no puede traer datos de evaluación, aprobación, entrega ni cancelación';
                END IF;
                IF NEW.compromiso_foto_path IS NOT NULL OR NEW.motivo_cancelacion IS NOT NULL OR NEW.permitir_otra_adopcion THEN
                    RAISE EXCEPTION 'Una solicitud nueva no puede traer evidencia, motivo de cancelación ni permiso para varias adopciones';
                END IF;
                RETURN NEW;
            END IF;

            IF OLD.numero_acta IS NOT NULL AND NEW.numero_acta IS DISTINCT FROM OLD.numero_acta THEN
                RAISE EXCEPTION 'El número de acta no puede cambiar';
            END IF;
            IF OLD.aprobada_at IS NOT NULL AND NEW.aprobada_at IS DISTINCT FROM OLD.aprobada_at THEN
                RAISE EXCEPTION 'La fecha de aprobación no puede cambiar';
            END IF;
            -- Sin acta todavía: solo la aprobación la asigna (validar_cambio_estado_solicitud la sobrescribe).
            IF OLD.numero_acta IS NULL AND (NEW.numero_acta IS NOT NULL OR NEW.aprobada_at IS NOT NULL)
               AND NOT (OLD.estado = 'en_espera' AND NEW.estado = 'aprobada') THEN
                RAISE EXCEPTION 'El número de acta y la fecha de aprobación solo se asignan al aprobar la solicitud';
            END IF;

            IF (NEW.entrevista_fecha, NEW.entrevista_medio, NEW.entrevista_resultado, NEW.entrevista_notas,
                NEW.visita_previa_fecha, NEW.visita_previa_resultado, NEW.visita_previa_notas)
               IS DISTINCT FROM
               (OLD.entrevista_fecha, OLD.entrevista_medio, OLD.entrevista_resultado, OLD.entrevista_notas,
                OLD.visita_previa_fecha, OLD.visita_previa_resultado, OLD.visita_previa_notas) THEN
                IF OLD.estado <> 'en_espera' OR NEW.estado <> 'en_espera' THEN
                    RAISE EXCEPTION 'La entrevista y la visita previa solo se registran mientras la solicitud está en evaluación';
                END IF;
                IF NEW.entrevista_fecha > hoy OR NEW.visita_previa_fecha > hoy THEN
                    RAISE EXCEPTION 'La fecha de la entrevista o de la visita no puede ser futura';
                END IF;
            END IF;

            IF NEW.fecha_entrega_acordada IS DISTINCT FROM OLD.fecha_entrega_acordada
               AND NOT ((OLD.estado = 'en_espera' AND NEW.estado = 'aprobada') OR (OLD.estado = 'aprobada' AND NEW.estado = 'aprobada')) THEN
                RAISE EXCEPTION 'La fecha de entrega acordada solo se registra al aprobar la solicitud o mientras está aprobada';
            END IF;

            IF (NEW.compromiso_firmado, NEW.entregada_at, NEW.permitir_otra_adopcion)
               IS DISTINCT FROM (OLD.compromiso_firmado, OLD.entregada_at, OLD.permitir_otra_adopcion)
               AND NOT (OLD.estado = 'aprobada' AND NEW.estado = 'aceptada') THEN
                RAISE EXCEPTION 'Los datos de la entrega solo se registran al registrar la entrega';
            END IF;

            -- La foto del acta firmada se puede subir o reemplazar mientras está reservada y también después
            -- de registrar la entrega (lo habitual es firmar en la entrega y subir la foto luego).
            IF NEW.compromiso_foto_path IS DISTINCT FROM OLD.compromiso_foto_path
               AND NOT (OLD.estado = NEW.estado AND NEW.estado IN ('aprobada', 'aceptada')) THEN
                RAISE EXCEPTION 'La evidencia del compromiso solo se registra en una solicitud reservada o con la adopción concretada';
            END IF;

            IF NEW.motivo_cancelacion IS DISTINCT FROM OLD.motivo_cancelacion
               AND NOT (OLD.estado IN ('pendiente', 'en_espera', 'aprobada') AND NEW.estado = 'cancelada') THEN
                RAISE EXCEPTION 'El motivo de cancelación solo se registra al cancelar una solicitud activa';
            END IF;

            IF NEW.motivo_no_concretada IS DISTINCT FROM OLD.motivo_no_concretada
               AND NOT (OLD.estado = 'aprobada' AND NEW.estado = 'no_concretada') THEN
                RAISE EXCEPTION 'El motivo de la entrega no concretada solo se registra al marcar la entrega como no concretada';
            END IF;

            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;

        CREATE OR REPLACE FUNCTION procesar_aceptacion_solicitud()
        RETURNS TRIGGER AS $$
        BEGIN
            IF NEW.estado = OLD.estado THEN
                RETURN NEW;
            END IF;

            -- Aprobada: la mascota queda reservada para esta solicitud y sale del catálogo.
            IF NEW.estado = 'aprobada' THEN
                UPDATE mascotas SET estado = 'reservada' WHERE id = NEW.mascota_id;
            END IF;

            -- La entrega no se concretó o se canceló la solicitud aprobada: la mascota vuelve a estar disponible.
            IF OLD.estado = 'aprobada' AND NEW.estado IN ('no_concretada', 'cancelada') THEN
                UPDATE mascotas SET estado = 'disponible' WHERE id = NEW.mascota_id;
            END IF;

            IF NEW.estado = 'aceptada' THEN
                UPDATE mascotas SET estado = 'adoptada' WHERE id = NEW.mascota_id;

                -- Las demás solicitudes activas de la mascota se cierran.
                UPDATE solicitudes_adopcion
                SET estado = 'cerrada'
                WHERE mascota_id = NEW.mascota_id
                  AND id <> NEW.id
                  AND estado IN ('pendiente', 'en_espera');

                -- Las demás solicitudes activas del adoptante se cancelan, salvo que el administrador
                -- permita más de una adopción a esta persona. Una aprobada cancelada libera su mascota.
                IF NOT NEW.permitir_otra_adopcion THEN
                    UPDATE solicitudes_adopcion
                    SET estado = 'cancelada', motivo_cancelacion = 'Se concretó otra adopción'
                    WHERE solicitante_id = NEW.solicitante_id
                      AND id <> NEW.id
                      AND estado IN ('pendiente', 'en_espera', 'aprobada');
                END IF;

                -- Crea el caso de seguimiento, copiando zona y horario que el ciudadano indicó en su solicitud.
                INSERT INTO seguimientos_adopcion (solicitud_id, zona_aproximada, horario_preferido)
                VALUES (NEW.id, NEW.zona_aproximada, NEW.horario_preferido);
            END IF;

            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
    SQL;
};
