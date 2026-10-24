<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION validar_cambio_estado_solicitud()
RETURNS TRIGGER AS $$
DECLARE
    estado_mascota VARCHAR(20);
    hoy DATE := (CURRENT_TIMESTAMP AT TIME ZONE 'America/La_Paz')::date;
BEGIN
    IF NEW.estado = OLD.estado THEN
        RETURN NEW;
    END IF;

    IF OLD.estado IN ('aceptada', 'rechazada', 'cancelada', 'cerrada', 'no_concretada') THEN
        RAISE EXCEPTION 'Una solicitud con estado final no puede cambiar de estado';
    END IF;

    IF NEW.estado = 'cerrada' THEN
        IF pg_trigger_depth() = 1 OR OLD.estado NOT IN ('pendiente', 'en_espera') THEN
            RAISE EXCEPTION 'El estado cerrada solo puede asignarse automáticamente al concretarse otra adopción de la mascota';
        END IF;
        RETURN NEW;
    END IF;

    IF NOT (
        (OLD.estado = 'pendiente' AND NEW.estado IN ('en_espera', 'rechazada', 'cancelada'))
        OR (OLD.estado = 'en_espera' AND NEW.estado IN ('aprobada', 'rechazada', 'cancelada'))
        OR (OLD.estado = 'aprobada' AND NEW.estado IN ('aceptada', 'no_concretada', 'cancelada'))
    ) THEN
        RAISE EXCEPTION 'Una solicitud en estado % no puede pasar a %', OLD.estado, NEW.estado;
    END IF;

    IF NEW.estado = 'aprobada' THEN
        SELECT estado INTO estado_mascota FROM mascotas WHERE id = NEW.mascota_id FOR UPDATE;
        IF estado_mascota IS DISTINCT FROM 'disponible' THEN
            RAISE EXCEPTION 'Solo se puede aprobar una solicitud de una mascota disponible';
        END IF;

        NEW.aprobada_at := CURRENT_TIMESTAMP;
        NEW.numero_acta := 'POL-ADOP-' || to_char(CURRENT_TIMESTAMP AT TIME ZONE 'America/La_Paz', 'YYYY')
            || '-' || lpad(nextval('seq_numero_acta_adopcion')::text, 4, '0');
    END IF;

    IF NEW.estado = 'aceptada' THEN
        IF NEW.compromiso_firmado IS DISTINCT FROM TRUE THEN
            RAISE EXCEPTION 'Para registrar la entrega, el adoptante debe firmar el compromiso de adopción';
        END IF;
        IF NEW.entregada_at IS NULL THEN
            RAISE EXCEPTION 'Para registrar la entrega se necesita la fecha de entrega';
        END IF;
        IF (NEW.entregada_at AT TIME ZONE 'America/La_Paz')::date > hoy THEN
            RAISE EXCEPTION 'La fecha de entrega no puede ser futura';
        END IF;
        IF (NEW.entregada_at AT TIME ZONE 'America/La_Paz')::date < (OLD.aprobada_at AT TIME ZONE 'America/La_Paz')::date THEN
            RAISE EXCEPTION 'La fecha de entrega no puede ser anterior a la aprobación';
        END IF;

        SELECT estado INTO estado_mascota FROM mascotas WHERE id = NEW.mascota_id FOR UPDATE;
        IF estado_mascota IS DISTINCT FROM 'reservada' THEN
            RAISE EXCEPTION 'Solo se puede registrar la entrega de una mascota reservada';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
    }

    public function down(): void
    {
        // Revierte solo la regla simplificada: restaura el requisito anterior de entrevista favorable
        // y visita previa no desfavorable, sin tocar columnas, datos, índices ni otros triggers.
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION validar_cambio_estado_solicitud()
RETURNS TRIGGER AS $$
DECLARE
    estado_mascota VARCHAR(20);
    hoy DATE := (CURRENT_TIMESTAMP AT TIME ZONE 'America/La_Paz')::date;
BEGIN
    IF NEW.estado = OLD.estado THEN RETURN NEW; END IF;
    IF OLD.estado IN ('aceptada', 'rechazada', 'cancelada', 'cerrada', 'no_concretada') THEN
        RAISE EXCEPTION 'Una solicitud con estado final no puede cambiar de estado';
    END IF;
    IF NEW.estado = 'cerrada' THEN
        IF pg_trigger_depth() = 1 OR OLD.estado NOT IN ('pendiente', 'en_espera') THEN
            RAISE EXCEPTION 'El estado cerrada solo puede asignarse automáticamente al concretarse otra adopción de la mascota';
        END IF;
        RETURN NEW;
    END IF;
    IF NOT (
        (OLD.estado = 'pendiente' AND NEW.estado IN ('en_espera', 'rechazada', 'cancelada'))
        OR (OLD.estado = 'en_espera' AND NEW.estado IN ('aprobada', 'rechazada', 'cancelada'))
        OR (OLD.estado = 'aprobada' AND NEW.estado IN ('aceptada', 'no_concretada', 'cancelada'))
    ) THEN
        RAISE EXCEPTION 'Una solicitud en estado % no puede pasar a %', OLD.estado, NEW.estado;
    END IF;
    IF NEW.estado = 'aprobada' THEN
        IF NEW.entrevista_resultado IS DISTINCT FROM 'favorable' THEN
            RAISE EXCEPTION 'Para aprobar la solicitud se necesita una entrevista con resultado favorable';
        END IF;
        IF NEW.visita_previa_resultado = 'no_favorable' THEN
            RAISE EXCEPTION 'No se puede aprobar la solicitud: la visita previa al hogar no fue favorable';
        END IF;
        SELECT estado INTO estado_mascota FROM mascotas WHERE id = NEW.mascota_id FOR UPDATE;
        IF estado_mascota IS DISTINCT FROM 'disponible' THEN
            RAISE EXCEPTION 'Solo se puede aprobar una solicitud de una mascota disponible';
        END IF;
        NEW.aprobada_at := CURRENT_TIMESTAMP;
        NEW.numero_acta := 'POL-ADOP-' || to_char(CURRENT_TIMESTAMP AT TIME ZONE 'America/La_Paz', 'YYYY')
            || '-' || lpad(nextval('seq_numero_acta_adopcion')::text, 4, '0');
    END IF;
    IF NEW.estado = 'aceptada' THEN
        IF NEW.compromiso_firmado IS DISTINCT FROM TRUE THEN
            RAISE EXCEPTION 'Para registrar la entrega, el adoptante debe firmar el compromiso de adopción';
        END IF;
        IF NEW.entregada_at IS NULL THEN RAISE EXCEPTION 'Para registrar la entrega se necesita la fecha de entrega'; END IF;
        IF (NEW.entregada_at AT TIME ZONE 'America/La_Paz')::date > hoy THEN RAISE EXCEPTION 'La fecha de entrega no puede ser futura'; END IF;
        IF (NEW.entregada_at AT TIME ZONE 'America/La_Paz')::date < (OLD.aprobada_at AT TIME ZONE 'America/La_Paz')::date THEN
            RAISE EXCEPTION 'La fecha de entrega no puede ser anterior a la aprobación';
        END IF;
        SELECT estado INTO estado_mascota FROM mascotas WHERE id = NEW.mascota_id FOR UPDATE;
        IF estado_mascota IS DISTINCT FROM 'reservada' THEN
            RAISE EXCEPTION 'Solo se puede registrar la entrega de una mascota reservada';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
    }
};
