<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Estados finales que registran la fecha de resolución (fin del trámite). Una adopción realizada se
     * identifica solo con estado = 'aceptada': resuelta_at en una solicitud cerrada es la fecha de su
     * cierre automático, nunca una adopción.
     */
    public const ESTADOS_RESUELTOS = ['aceptada', 'rechazada', 'cancelada', 'cerrada'];

    /** Estados con una transición auditable (cerrada la asigna PostgreSQL, sin auditoría). */
    public const ESTADOS_AUDITABLES = ['aceptada', 'rechazada', 'cancelada'];

    /**
     * Fecha real de resolución de las solicitudes de adopción.
     *
     * - Columna resuelta_at (TIMESTAMPTZ) e índice (estado, resuelta_at) para filtrar, p. ej., las
     *   aceptadas por fecha de resolución.
     * - Trigger que la registra en cada transición a un estado resuelto y la limpia si la solicitud
     *   vuelve a un estado abierto. Al ser de PostgreSQL cubre todos los caminos: las acciones del
     *   administrador, las actualizaciones anidadas de otros triggers y cualquier UPDATE directo.
     * - Recuperación de las fechas de las solicitudes ya resueltas (auditoría o updated_at).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE solicitudes_adopcion ADD COLUMN resuelta_at TIMESTAMPTZ NULL');
        DB::statement('CREATE INDEX idx_solicitudes_estado_resuelta_at ON solicitudes_adopcion (estado, resuelta_at)');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION registrar_resolucion_solicitud()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Las solicitudes se crean pendientes: nunca nacen resueltas.
                IF TG_OP = 'INSERT' THEN
                    NEW.resuelta_at := NULL;
                    RETURN NEW;
                END IF;

                -- Solo una transición real de estado modifica la fecha; reescribir el mismo estado no la toca.
                IF NEW.estado IS DISTINCT FROM OLD.estado THEN
                    -- cerrada: la asigna procesar_aceptacion_solicitud en la misma transacción que la
                    -- aceptación de otra solicitud de la mascota, por lo que comparte su CURRENT_TIMESTAMP.
                    IF NEW.estado IN ('aceptada', 'rechazada', 'cancelada', 'cerrada') THEN
                        NEW.resuelta_at := CURRENT_TIMESTAMP;
                    ELSIF NEW.estado IN ('pendiente', 'en_espera') THEN
                        NEW.resuelta_at := NULL;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trigger_registrar_resolucion_solicitud
            BEFORE INSERT OR UPDATE OF estado ON solicitudes_adopcion
            FOR EACH ROW EXECUTE FUNCTION registrar_resolucion_solicitud();
        SQL);

        $this->recuperarFechasDeResolucion();
    }

    /** Elimina solo lo creado aquí; no reconstruye valores anteriores de las solicitudes. */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trigger_registrar_resolucion_solicitud ON solicitudes_adopcion');
        DB::statement('DROP FUNCTION IF EXISTS registrar_resolucion_solicitud()');
        DB::statement('DROP INDEX IF EXISTS idx_solicitudes_estado_resuelta_at');
        DB::statement('ALTER TABLE solicitudes_adopcion DROP COLUMN IF EXISTS resuelta_at');
    }

    /**
     * Completa resuelta_at de las solicitudes resueltas que aún no la tienen. No toca las que ya la tienen.
     *
     * 1. Auditoría: la auditoría de SolicitudAdopcion de esa solicitud cuyo valores_nuevos.estado es el
     *    estado final actual, con un estado anterior distinto (una transición real) y sin ningún cambio
     *    posterior a otro estado. Si hay varias, la primera tras el último cambio a otro estado: la que
     *    dejó la solicitud en su estado final. auditorias.created_at se guarda en UTC sin zona.
     *    Solo para los estados auditables (ESTADOS_AUDITABLES).
     * 2. cerrada: la fecha de resolución de la solicitud aceptada de la misma mascota. PostgreSQL solo
     *    asigna cerrada al aceptar otra solicitud de la mascota (validar_cambio_estado_solicitud lo exige
     *    desde el esquema inicial) y solo puede haber una aceptada por mascota
     *    (idx_una_solicitud_aceptada_por_mascota): ese cierre ocurrió en la transacción de esa aceptación.
     *    Se usa solo si esa fecha no es anterior a created_at de la solicitud cerrada.
     * 3. updated_at, si no hay fecha anterior y no es anterior a created_at.
     * 4. Si tampoco hay una fecha fiable, queda NULL.
     *
     * El trigger de updated_at se desactiva solo durante esta actualización para conservar las fechas
     * históricas de modificación. Todo ocurre en una transacción propia (un SAVEPOINT dentro de la de la
     * migración): si algo falla, también se revierte la desactivación del trigger, aunque la función se
     * llame fuera de una migración.
     *
     * @return array{auditoria: int, cierre_por_aceptacion: int, updated_at: int, sin_fecha: int}
     */
    public function recuperarFechasDeResolucion(): array
    {
        return DB::transaction(fn (): array => $this->recuperarFechas());
    }

    /** @return array{auditoria: int, cierre_por_aceptacion: int, updated_at: int, sin_fecha: int} */
    private function recuperarFechas(): array
    {
        $estados = self::ESTADOS_RESUELTOS;
        $marcadores = implode(', ', array_fill(0, count($estados), '?'));
        $auditables = self::ESTADOS_AUDITABLES;
        $marcadoresAuditables = implode(', ', array_fill(0, count($auditables), '?'));

        $this->exigirTriggerUpdatedAt('O');
        DB::statement('ALTER TABLE solicitudes_adopcion DISABLE TRIGGER trigger_solicitudes_updated_at');

        $porAuditoria = DB::update(<<<SQL
            UPDATE solicitudes_adopcion s
            SET resuelta_at = t.fecha
            FROM (
                SELECT s2.id, (
                    SELECT a.created_at AT TIME ZONE 'UTC'
                    FROM auditorias a
                    WHERE a.modelo = 'SolicitudAdopcion'
                      AND a.modelo_id = s2.id
                      AND a.valores_nuevos ->> 'estado' = s2.estado
                      AND (a.valores_anteriores ->> 'estado') IS DISTINCT FROM s2.estado
                      AND NOT EXISTS (
                          SELECT 1 FROM auditorias b
                          WHERE b.modelo = 'SolicitudAdopcion'
                            AND b.modelo_id = s2.id
                            AND b.valores_nuevos ->> 'estado' <> s2.estado
                            AND (b.created_at, b.id) > (a.created_at, a.id)
                      )
                    ORDER BY a.created_at, a.id
                    LIMIT 1
                ) AS fecha
                FROM solicitudes_adopcion s2
                WHERE s2.estado IN ({$marcadoresAuditables}) AND s2.resuelta_at IS NULL
            ) t
            WHERE s.id = t.id AND t.fecha IS NOT NULL
        SQL, $auditables);

        $porCierre = DB::update(<<<'SQL'
            UPDATE solicitudes_adopcion c
            SET resuelta_at = a.resuelta_at
            FROM solicitudes_adopcion a
            WHERE c.estado = 'cerrada'
              AND c.resuelta_at IS NULL
              AND a.mascota_id = c.mascota_id
              AND a.id <> c.id
              AND a.estado = 'aceptada'
              AND a.resuelta_at IS NOT NULL
              AND a.resuelta_at >= c.created_at
        SQL);

        $porUpdatedAt = DB::update(<<<SQL
            UPDATE solicitudes_adopcion
            SET resuelta_at = updated_at
            WHERE estado IN ({$marcadores})
              AND resuelta_at IS NULL
              AND updated_at IS NOT NULL
              AND updated_at >= created_at
        SQL, $estados);

        DB::statement('ALTER TABLE solicitudes_adopcion ENABLE TRIGGER trigger_solicitudes_updated_at');
        $this->exigirTriggerUpdatedAt('O');

        $sinFecha = (int) DB::selectOne(
            "SELECT count(*) AS n FROM solicitudes_adopcion WHERE estado IN ({$marcadores}) AND resuelta_at IS NULL",
            $estados
        )->n;

        return ['auditoria' => $porAuditoria, 'cierre_por_aceptacion' => $porCierre, 'updated_at' => $porUpdatedAt, 'sin_fecha' => $sinFecha];
    }

    private function exigirTriggerUpdatedAt(string $esperado): void
    {
        $estado = DB::selectOne("SELECT tgenabled AS e FROM pg_trigger WHERE tgname = 'trigger_solicitudes_updated_at' AND tgrelid = 'solicitudes_adopcion'::regclass")?->e;

        if ($estado !== $esperado) {
            throw new RuntimeException("trigger_solicitudes_updated_at en estado '{$estado}', se esperaba '{$esperado}'.");
        }
    }
};
