<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nuevo flujo de las solicitudes de adopción:
 *
 *   pendiente (Recibida) → en_espera (En evaluación) → aprobada (Aprobada) → aceptada (Adopción concretada)
 *
 *   pendiente → en_espera | rechazada | cancelada
 *   en_espera → aprobada  | rechazada | cancelada
 *   aprobada  → aceptada  | no_concretada | cancelada
 *
 * - aprobada exige una entrevista favorable, que la visita previa (si se registró) sea favorable y que la
 *   mascota esté disponible. Reserva la mascota (sale del catálogo) y asigna el número de acta.
 * - aceptada (Registrar entrega) exige el compromiso firmado y la fecha de entrega. Adopta la mascota,
 *   cierra las demás solicitudes activas de la mascota, cancela las demás solicitudes activas del
 *   adoptante (salvo permitir_otra_adopcion) y crea el seguimiento.
 * - no_concretada (con motivo) o cancelada desde aprobada devuelven la mascota a disponible.
 * - Un ciudadano tiene como máximo 2 solicitudes activas (pendiente, en_espera o aprobada), de mascotas distintas.
 *
 * Las solicitudes aceptadas anteriores se conservan tal cual: son adopciones concretadas.
 * No modifica database/sql/pol_bolivia.sql: reemplaza las funciones con CREATE OR REPLACE.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Estados nuevos.
        DB::statement('ALTER TABLE solicitudes_adopcion DROP CONSTRAINT solicitudes_estado_valido');
        DB::statement("ALTER TABLE solicitudes_adopcion ADD CONSTRAINT solicitudes_estado_valido CHECK (estado IN (
            'pendiente', 'en_espera', 'aprobada', 'aceptada', 'rechazada', 'cancelada', 'cerrada', 'no_concretada'))");
        DB::statement('ALTER TABLE mascotas DROP CONSTRAINT mascotas_estado_valido');
        DB::statement("ALTER TABLE mascotas ADD CONSTRAINT mascotas_estado_valido CHECK (estado IN ('disponible', 'reservada', 'adoptada'))");

        // 2. Columnas nuevas (todas opcionales: las solicitudes existentes no cambian).
        DB::unprepared(<<<'SQL'
            -- IF NOT EXISTS: tolera una secuencia que haya quedado de una instalación anterior sin dueño.
            -- 2026_10_22_000000 la liga a la columna para que se elimine junto con la tabla.
            CREATE SEQUENCE IF NOT EXISTS seq_numero_acta_adopcion START 1;

            ALTER TABLE solicitudes_adopcion
                ADD COLUMN entrevista_fecha DATE NULL,
                ADD COLUMN entrevista_medio VARCHAR(20) NULL,
                ADD COLUMN entrevista_resultado VARCHAR(20) NULL,
                ADD COLUMN entrevista_notas TEXT NULL,
                ADD COLUMN visita_previa_fecha DATE NULL,
                ADD COLUMN visita_previa_resultado VARCHAR(20) NULL,
                ADD COLUMN visita_previa_notas TEXT NULL,
                ADD COLUMN aprobada_at TIMESTAMPTZ NULL,
                ADD COLUMN fecha_entrega_acordada DATE NULL,
                ADD COLUMN entregada_at TIMESTAMPTZ NULL,
                ADD COLUMN compromiso_firmado BOOLEAN NOT NULL DEFAULT FALSE,
                ADD COLUMN permitir_otra_adopcion BOOLEAN NOT NULL DEFAULT FALSE,
                ADD COLUMN numero_acta VARCHAR(30) NULL,
                ADD COLUMN compromiso_foto_path TEXT NULL,
                ADD COLUMN motivo_no_concretada TEXT NULL,
                ADD COLUMN motivo_cancelacion TEXT NULL;

            ALTER TABLE solicitudes_adopcion
                ADD CONSTRAINT solicitudes_numero_acta_unico UNIQUE (numero_acta),
                ADD CONSTRAINT solicitudes_numero_acta_formato CHECK (numero_acta IS NULL OR numero_acta ~ '^POL-ADOP-[0-9]{4}-[0-9]{4,}$'),
                ADD CONSTRAINT solicitudes_entrevista_medio_valido CHECK (entrevista_medio IS NULL OR entrevista_medio IN ('llamada', 'whatsapp', 'presencial')),
                ADD CONSTRAINT solicitudes_entrevista_resultado_valido CHECK (entrevista_resultado IS NULL OR entrevista_resultado IN ('favorable', 'no_favorable')),
                ADD CONSTRAINT solicitudes_visita_previa_resultado_valido CHECK (visita_previa_resultado IS NULL OR visita_previa_resultado IN ('favorable', 'no_favorable')),
                -- Una entrevista o una visita se registran completas (fecha, medio y resultado) o no se registran.
                ADD CONSTRAINT solicitudes_entrevista_completa CHECK (
                    (entrevista_fecha IS NULL AND entrevista_medio IS NULL AND entrevista_resultado IS NULL)
                    OR (entrevista_fecha IS NOT NULL AND entrevista_medio IS NOT NULL AND entrevista_resultado IS NOT NULL)),
                ADD CONSTRAINT solicitudes_visita_previa_completa CHECK ((visita_previa_fecha IS NULL) = (visita_previa_resultado IS NULL)),
                ADD CONSTRAINT solicitudes_motivo_no_concretada CHECK (estado <> 'no_concretada' OR btrim(coalesce(motivo_no_concretada, '')) <> '');

            -- Una sola solicitud aprobada por mascota (respaldo del bloqueo FOR UPDATE de la aprobación).
            CREATE UNIQUE INDEX idx_una_solicitud_aprobada_por_mascota ON solicitudes_adopcion (mascota_id) WHERE estado = 'aprobada';

            -- Una sola solicitud activa por persona y mascota: ahora también cuenta la aprobada.
            DROP INDEX idx_una_solicitud_pendiente;
            CREATE UNIQUE INDEX idx_una_solicitud_pendiente ON solicitudes_adopcion (solicitante_id, mascota_id)
                WHERE estado IN ('pendiente', 'en_espera', 'aprobada');
        SQL);

        // 3. Funciones.
        DB::unprepared(self::FUNCIONES_NUEVAS);

        // 4. Triggers nuevos (los demás ya existen y usan las funciones reemplazadas).
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trigger_proteger_datos_solicitud
            BEFORE INSERT OR UPDATE ON solicitudes_adopcion
            FOR EACH ROW EXECUTE FUNCTION proteger_datos_solicitud();

            CREATE TRIGGER trigger_validar_limite_solicitudes_activas
            BEFORE INSERT ON solicitudes_adopcion
            FOR EACH ROW EXECUTE FUNCTION validar_limite_solicitudes_activas();
        SQL);
    }

    /**
     * Vuelve al flujo anterior solo si no hay estados ni datos exclusivos del flujo nuevo en uso;
     * de lo contrario se detiene antes de ejecutar cambios estructurales.
     */
    public function down(): void
    {
        $enUso = DB::selectOne("SELECT
            (SELECT count(*) FROM solicitudes_adopcion WHERE estado IN ('aprobada', 'no_concretada')) AS solicitudes,
            (SELECT count(*) FROM mascotas WHERE estado = 'reservada') AS mascotas,
            (SELECT count(*) FROM solicitudes_adopcion WHERE
                entrevista_fecha IS NOT NULL OR entrevista_medio IS NOT NULL OR entrevista_resultado IS NOT NULL OR entrevista_notas IS NOT NULL OR
                visita_previa_fecha IS NOT NULL OR visita_previa_resultado IS NOT NULL OR visita_previa_notas IS NOT NULL OR
                aprobada_at IS NOT NULL OR fecha_entrega_acordada IS NOT NULL OR entregada_at IS NOT NULL OR
                compromiso_firmado IS TRUE OR permitir_otra_adopcion IS TRUE OR numero_acta IS NOT NULL OR
                compromiso_foto_path IS NOT NULL OR motivo_no_concretada IS NOT NULL OR motivo_cancelacion IS NOT NULL
            ) AS datos_nuevos");

        if ($enUso->solicitudes > 0 || $enUso->mascotas > 0) {
            throw new RuntimeException("No se puede revertir: hay {$enUso->solicitudes} solicitudes aprobadas o no concretadas y {$enUso->mascotas} mascotas reservadas.");
        }

        if ($enUso->datos_nuevos > 0) {
            throw new RuntimeException("No se puede revertir: {$enUso->datos_nuevos} solicitudes conservan datos del nuevo flujo. Se mantienen intactos los campos y la secuencia para evitar perder entrevistas, aprobaciones, entregas, evidencias o motivos.");
        }

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trigger_validar_limite_solicitudes_activas ON solicitudes_adopcion;
            DROP TRIGGER IF EXISTS trigger_proteger_datos_solicitud ON solicitudes_adopcion;
            DROP FUNCTION IF EXISTS validar_limite_solicitudes_activas();
            DROP FUNCTION IF EXISTS proteger_datos_solicitud();
        SQL);

        DB::unprepared(self::FUNCIONES_ANTERIORES);

        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS idx_una_solicitud_aprobada_por_mascota;
            DROP INDEX IF EXISTS idx_una_solicitud_pendiente;
            CREATE UNIQUE INDEX idx_una_solicitud_pendiente ON solicitudes_adopcion (solicitante_id, mascota_id)
                WHERE estado IN ('pendiente', 'en_espera');

            ALTER TABLE solicitudes_adopcion
                DROP COLUMN IF EXISTS entrevista_fecha,
                DROP COLUMN IF EXISTS entrevista_medio,
                DROP COLUMN IF EXISTS entrevista_resultado,
                DROP COLUMN IF EXISTS entrevista_notas,
                DROP COLUMN IF EXISTS visita_previa_fecha,
                DROP COLUMN IF EXISTS visita_previa_resultado,
                DROP COLUMN IF EXISTS visita_previa_notas,
                DROP COLUMN IF EXISTS aprobada_at,
                DROP COLUMN IF EXISTS fecha_entrega_acordada,
                DROP COLUMN IF EXISTS entregada_at,
                DROP COLUMN IF EXISTS compromiso_firmado,
                DROP COLUMN IF EXISTS permitir_otra_adopcion,
                DROP COLUMN IF EXISTS numero_acta,
                DROP COLUMN IF EXISTS compromiso_foto_path,
                DROP COLUMN IF EXISTS motivo_no_concretada,
                DROP COLUMN IF EXISTS motivo_cancelacion;

            DROP SEQUENCE IF EXISTS seq_numero_acta_adopcion;
        SQL);

        DB::statement('ALTER TABLE mascotas DROP CONSTRAINT mascotas_estado_valido');
        DB::statement("ALTER TABLE mascotas ADD CONSTRAINT mascotas_estado_valido CHECK (estado IN ('disponible', 'adoptada'))");
        DB::statement('ALTER TABLE solicitudes_adopcion DROP CONSTRAINT solicitudes_estado_valido');
        DB::statement("ALTER TABLE solicitudes_adopcion ADD CONSTRAINT solicitudes_estado_valido CHECK (estado IN (
            'pendiente', 'en_espera', 'aceptada', 'rechazada', 'cancelada', 'cerrada'))");
    }

    private const FUNCIONES_NUEVAS = <<<'SQL'
        -- Transiciones permitidas y requisitos de cada etapa. Bloquea la mascota (FOR UPDATE) al aprobar y
        -- al registrar la entrega: dos aprobaciones simultáneas de la misma mascota no pueden prosperar.
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

            -- El cierre se permite solo durante el UPDATE anidado de procesar_aceptacion_solicitud
            -- (profundidad mayor que 1); una actualización directa entra con profundidad 1.
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

                -- Número de acta único (POL-ADOP-AAAA-NNNN) y fecha de aprobación: se asignan una sola vez.
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

        -- Efectos de cada transición sobre la mascota, las demás solicitudes y el seguimiento.
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

        -- Fecha de resolución: aprobada todavía no es una resolución; no_concretada sí.
        CREATE OR REPLACE FUNCTION registrar_resolucion_solicitud()
        RETURNS TRIGGER AS $$
        BEGIN
            IF TG_OP = 'INSERT' THEN
                NEW.resuelta_at := NULL;
                RETURN NEW;
            END IF;

            IF NEW.estado IS DISTINCT FROM OLD.estado THEN
                IF NEW.estado IN ('aceptada', 'rechazada', 'cancelada', 'cerrada', 'no_concretada') THEN
                    NEW.resuelta_at := CURRENT_TIMESTAMP;
                ELSIF NEW.estado IN ('pendiente', 'en_espera', 'aprobada') THEN
                    NEW.resuelta_at := NULL;
                END IF;
            END IF;

            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;

        -- reservada y adoptada solo las asignan los triggers de la solicitud (profundidad >= 2).
        CREATE OR REPLACE FUNCTION proteger_estado_mascota()
        RETURNS TRIGGER AS $$
        BEGIN
            IF TG_OP = 'INSERT' THEN
                IF NEW.estado <> 'disponible' THEN
                    RAISE EXCEPTION 'Una mascota nueva debe registrarse como disponible';
                END IF;
                RETURN NEW;
            END IF;

            IF NEW.estado = OLD.estado THEN
                RETURN NEW;
            END IF;

            IF OLD.estado = 'adoptada' THEN
                RAISE EXCEPTION 'Una mascota adoptada no puede cambiar nuevamente de estado';
            END IF;

            IF NEW.estado = 'reservada' THEN
                IF pg_trigger_depth() < 2 OR NOT EXISTS (
                    SELECT 1 FROM solicitudes_adopcion WHERE mascota_id = NEW.id AND estado = 'aprobada'
                ) THEN
                    RAISE EXCEPTION 'El estado reservada solo puede establecerse al aprobar una solicitud';
                END IF;
            END IF;

            IF OLD.estado = 'reservada' AND NEW.estado = 'disponible' THEN
                IF pg_trigger_depth() < 2 OR EXISTS (
                    SELECT 1 FROM solicitudes_adopcion WHERE mascota_id = NEW.id AND estado = 'aprobada'
                ) THEN
                    RAISE EXCEPTION 'Una mascota reservada solo vuelve a estar disponible si la entrega no se concreta o se cancela la solicitud aprobada';
                END IF;
            END IF;

            IF NEW.estado = 'adoptada' THEN
                IF OLD.estado <> 'reservada' OR pg_trigger_depth() < 2 OR NOT EXISTS (
                    SELECT 1 FROM solicitudes_adopcion WHERE mascota_id = NEW.id AND estado = 'aceptada'
                ) THEN
                    RAISE EXCEPTION 'El estado adoptada solo puede establecerse al registrar la entrega de una solicitud aprobada';
                END IF;
            END IF;

            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;

        -- Una mascota disponible o reservada (puede volver a disponible) conserva al menos una foto principal.
        CREATE OR REPLACE FUNCTION validar_multimedia_mascota_disponible()
        RETURNS TRIGGER AS $$
        DECLARE
            mascota_a_validar BIGINT;
            estado_mascota VARCHAR(20);
            cantidad_fotos INTEGER;
            cantidad_principales INTEGER;
        BEGIN
            IF TG_TABLE_NAME = 'mascotas' THEN
                mascota_a_validar := NEW.id;
            ELSE
                mascota_a_validar := COALESCE(NEW.mascota_id, OLD.mascota_id);
            END IF;

            SELECT estado INTO estado_mascota FROM mascotas WHERE id = mascota_a_validar;

            IF estado_mascota IN ('disponible', 'reservada') THEN
                SELECT COUNT(*) INTO cantidad_fotos
                FROM fotos_mascota
                WHERE mascota_id = mascota_a_validar AND tipo = 'foto';

                SELECT COUNT(*) INTO cantidad_principales
                FROM fotos_mascota
                WHERE mascota_id = mascota_a_validar AND tipo = 'foto' AND es_principal = TRUE;

                IF cantidad_fotos = 0 OR cantidad_principales = 0 THEN
                    RAISE EXCEPTION 'Una mascota disponible debe conservar al menos una foto principal';
                END IF;
            END IF;

            RETURN COALESCE(NEW, OLD);
        END;
        $$ LANGUAGE plpgsql;

        -- Datos de la evaluación y de la entrega: solo se registran en su etapa y no se reescriben después.
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

        -- Como máximo 2 solicitudes activas (pendiente, en evaluación o aprobada) por ciudadano. Bloquea la fila
        -- del ciudadano para que dos envíos simultáneos no superen el límite.
        CREATE OR REPLACE FUNCTION validar_limite_solicitudes_activas()
        RETURNS TRIGGER AS $$
        DECLARE
            activas INTEGER;
        BEGIN
            PERFORM 1 FROM usuarios WHERE id = NEW.solicitante_id FOR NO KEY UPDATE;

            SELECT count(*) INTO activas
            FROM solicitudes_adopcion
            WHERE solicitante_id = NEW.solicitante_id
              AND estado IN ('pendiente', 'en_espera', 'aprobada');

            IF activas >= 2 THEN
                RAISE EXCEPTION 'Ya tienes 2 solicitudes de adopción activas. Para enviar otra, espera la respuesta o cancela una de ellas.';
            END IF;

            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
    SQL;

    /** Definiciones vigentes antes de esta migración (pol_bolivia.sql y 2026_10_14_000000_add_resuelta_at). */
    private const FUNCIONES_ANTERIORES = <<<'SQL'
        CREATE OR REPLACE FUNCTION validar_cambio_estado_solicitud()
        RETURNS TRIGGER AS $$
        DECLARE
            estado_mascota VARCHAR(20);
        BEGIN
            IF OLD.estado IN ('aceptada', 'rechazada', 'cancelada', 'cerrada')
               AND NEW.estado <> OLD.estado THEN
                RAISE EXCEPTION
                    'Una solicitud con estado final no puede cambiar de estado';
            END IF;
            IF NEW.estado = 'cerrada'
               AND OLD.estado <> 'cerrada'
               AND pg_trigger_depth() = 1 THEN
                RAISE EXCEPTION
                    'El estado cerrada solo puede asignarse automáticamente al aceptar otra solicitud';
            END IF;
            IF NEW.estado = 'aceptada'
               AND OLD.estado NOT IN ('pendiente', 'en_espera') THEN
                RAISE EXCEPTION
                    'Solo se puede aceptar una solicitud pendiente o en espera';
            END IF;
            IF NEW.estado = 'aceptada' AND OLD.estado <> 'aceptada' THEN
                SELECT estado INTO estado_mascota
                FROM mascotas
                WHERE id = NEW.mascota_id
                FOR UPDATE;
                IF estado_mascota IS DISTINCT FROM 'disponible' THEN
                    RAISE EXCEPTION
                        'Solo se puede aceptar una solicitud de una mascota disponible';
                END IF;
            END IF;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;

        CREATE OR REPLACE FUNCTION procesar_aceptacion_solicitud()
        RETURNS TRIGGER AS $$
        BEGIN
            IF NEW.estado = 'aceptada'
               AND OLD.estado <> 'aceptada' THEN
                UPDATE mascotas
                SET estado = 'adoptada'
                WHERE id = NEW.mascota_id;
                UPDATE solicitudes_adopcion
                SET estado = 'cerrada'
                WHERE mascota_id = NEW.mascota_id
                  AND id <> NEW.id
                  AND estado IN ('pendiente', 'en_espera');
                INSERT INTO seguimientos_adopcion (solicitud_id, zona_aproximada, horario_preferido)
                VALUES (NEW.id, NEW.zona_aproximada, NEW.horario_preferido);
            END IF;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;

        CREATE OR REPLACE FUNCTION registrar_resolucion_solicitud()
        RETURNS TRIGGER AS $$
        BEGIN
            IF TG_OP = 'INSERT' THEN
                NEW.resuelta_at := NULL;
                RETURN NEW;
            END IF;
            IF NEW.estado IS DISTINCT FROM OLD.estado THEN
                IF NEW.estado IN ('aceptada', 'rechazada', 'cancelada', 'cerrada') THEN
                    NEW.resuelta_at := CURRENT_TIMESTAMP;
                ELSIF NEW.estado IN ('pendiente', 'en_espera') THEN
                    NEW.resuelta_at := NULL;
                END IF;
            END IF;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;

        CREATE OR REPLACE FUNCTION proteger_estado_mascota()
        RETURNS TRIGGER AS $$
        BEGIN
            IF TG_OP = 'INSERT' AND NEW.estado = 'adoptada' THEN
                RAISE EXCEPTION
                    'Una mascota no puede crearse directamente con estado adoptada';
            END IF;
            IF TG_OP = 'UPDATE' THEN
                IF OLD.estado = 'adoptada' AND NEW.estado <> OLD.estado THEN
                    RAISE EXCEPTION
                        'Una mascota adoptada no puede cambiar nuevamente de estado';
                END IF;
                IF NEW.estado = 'adoptada' AND OLD.estado <> 'adoptada' THEN
                    IF pg_trigger_depth() < 2
                       OR NOT EXISTS (
                            SELECT 1
                            FROM solicitudes_adopcion
                            WHERE mascota_id = NEW.id
                              AND estado = 'aceptada'
                       ) THEN
                        RAISE EXCEPTION
                            'El estado adoptada solo puede establecerse al aceptar una solicitud';
                    END IF;
                END IF;
            END IF;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;

        CREATE OR REPLACE FUNCTION validar_multimedia_mascota_disponible()
        RETURNS TRIGGER AS $$
        DECLARE
            mascota_a_validar BIGINT;
            estado_mascota VARCHAR(20);
            cantidad_fotos INTEGER;
            cantidad_principales INTEGER;
        BEGIN
            IF TG_TABLE_NAME = 'mascotas' THEN
                mascota_a_validar := NEW.id;
            ELSE
                mascota_a_validar := COALESCE(NEW.mascota_id, OLD.mascota_id);
            END IF;
            SELECT estado INTO estado_mascota FROM mascotas WHERE id = mascota_a_validar;
            IF estado_mascota = 'disponible' THEN
                SELECT COUNT(*) INTO cantidad_fotos
                FROM fotos_mascota
                WHERE mascota_id = mascota_a_validar AND tipo = 'foto';
                SELECT COUNT(*) INTO cantidad_principales
                FROM fotos_mascota
                WHERE mascota_id = mascota_a_validar
                  AND tipo = 'foto'
                  AND es_principal = TRUE;
                IF cantidad_fotos = 0 OR cantidad_principales = 0 THEN
                    RAISE EXCEPTION
                        'Una mascota disponible debe conservar al menos una foto principal';
                END IF;
            END IF;
            RETURN COALESCE(NEW, OLD);
        END;
        $$ LANGUAGE plpgsql;
    SQL;
};
