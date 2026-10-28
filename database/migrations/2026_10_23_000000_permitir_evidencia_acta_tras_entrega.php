<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permite subir o reemplazar la foto del acta firmada (compromiso_foto_path) también después de registrar la
 * entrega. Antes solo se podía mientras la solicitud estaba reservada (aprobada), y lo habitual es firmar el acta
 * en la entrega y subir la foto después. Solo cambia esa condición de proteger_datos_solicitud(); no toca datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(self::FUNCION_NUEVA);
    }

    /** Vuelve a la regla anterior: solo mientras la solicitud está reservada. */
    public function down(): void
    {
        DB::unprepared(self::FUNCION_ANTERIOR);
    }

    private const FUNCION_NUEVA = <<<'SQL'
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
    SQL;

    /** Definición de 2026_10_20_000000_nuevo_flujo_solicitudes_adopcion. */
    private const FUNCION_ANTERIOR = <<<'SQL'
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

            IF NEW.compromiso_foto_path IS DISTINCT FROM OLD.compromiso_foto_path
               AND NOT (OLD.estado = 'aprobada' AND NEW.estado = 'aprobada') THEN
                RAISE EXCEPTION 'La evidencia del compromiso solo se registra mientras la entrega está pendiente';
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
    SQL;
};
