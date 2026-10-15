<?php

namespace App\Support\ZonaHoraria;

use App\Models\Auditoria;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Verifica, aplica y revierte la reparación de zona horaria (−4 h) de un PlanReparacion.
 *
 * Todo ocurre dentro de la transacción que abre el comando: si cualquier comprobación falla se
 * lanza una excepción y PostgreSQL deshace todo, incluida la desactivación temporal del trigger
 * de updated_at (DDL transaccional), por lo que ese trigger nunca queda desactivado.
 * Solo se desactiva trigger_<tabla>_updated_at de las tablas del plan; los triggers de
 * validación siguen activos.
 */
final class ReparacionZonaHoraria
{
    /** Bases en las que puede aplicarse, con su marca (COMMENT ON DATABASE). pol_bolivia no tiene marca: exige --confirmar-base. */
    public const BASES = [
        'pol_bolivia_test' => 'POL-BOLIVIA-PRUEBAS',
        'pol_bolivia_ensayo_zona' => 'POL-BOLIVIA-ENSAYO-ZONA',
        'pol_bolivia' => null,
    ];

    /** Los 8 triggers de updated_at del sistema: deben estar activos antes y después. */
    public const TRIGGERS_UPDATED_AT = [
        'usuarios' => 'trigger_usuarios_updated_at',
        'solicitudes_adopcion' => 'trigger_solicitudes_updated_at',
        'postulaciones_voluntariado' => 'trigger_postulaciones_updated_at',
        'mascotas' => 'trigger_mascotas_updated_at',
        'seguimientos_adopcion' => 'trigger_seguimientos_updated_at',
        'visitas_seguimiento' => 'trigger_visitas_updated_at',
        'incidencias' => 'trigger_incidencias_updated_at',
        'voluntarios' => 'trigger_voluntarios_updated_at',
    ];

    /**
     * Motivo para rechazar la base, o null si puede usarse.
     * La simulación (solo lectura) se admite en las bases conocidas; aplicar exige además la marca
     * (o, en pol_bolivia, --confirmar-base=pol_bolivia).
     */
    public static function motivoRechazoBase(string $conectada, ?string $marca, ?string $opcionBase, ?string $confirmarBase, bool $aplicar): ?string
    {
        return match (true) {
            $opcionBase === null || $opcionBase === '' => 'Indica la base con --base=<nombre>.',
            $opcionBase !== $conectada => "--base={$opcionBase} no coincide con la base conectada ({$conectada}).",
            ! array_key_exists($conectada, self::BASES) => "La base {$conectada} no está autorizada para la reparación.",
            ! $aplicar => null,
            self::BASES[$conectada] !== null && $marca !== self::BASES[$conectada] => "La base {$conectada} no tiene la marca ".self::BASES[$conectada].'.',
            self::BASES[$conectada] === null && $confirmarBase !== $conectada => "Para aplicar en {$conectada} se exige --confirmar-base={$conectada}.",
            default => null,
        };
    }

    public static function identidad(): object
    {
        return DB::selectOne("select current_database() as base, shobj_description(d.oid, 'pg_database') as marca
            from pg_database d where d.datname = current_database()");
    }

    /** Fija la sesión de la transacción en UTC (texto determinista) y límites de espera. */
    public static function prepararTransaccion(bool $soloLectura): void
    {
        if ($soloLectura) {
            DB::statement('SET TRANSACTION READ ONLY');
        }
        DB::statement("SET LOCAL TimeZone = 'UTC'");
        DB::statement("SET LOCAL lock_timeout = '5s'");
        DB::statement("SET LOCAL statement_timeout = '120s'");
    }

    /**
     * Campos de integridad y huella completa de una fila (fechas como texto UTC).
     *
     * Con $columnasHuella (lista ordenada de las columnas que tenía la tabla al reparar, tomada de un
     * AnexoColumnas verificado), la huella se calcula como md5(ROW(esas columnas)::text): el mismo texto
     * que md5(t::text) producía entonces, aunque después se hayan añadido columnas al final de la tabla.
     */
    public static function estadoFila(string $tabla, int $id, ?array $columnasHuella = null): ?array
    {
        $campos = PlanReparacion::CAMPOS_FILA[$tabla];
        $select = implode(', ', array_map(
            fn ($c) => in_array($c, PlanReparacion::CAMPOS_FECHA, true)
                ? "to_char({$c} at time zone 'UTC', 'YYYY-MM-DD HH24:MI:SS.US') as {$c}"
                : "{$c}::text as {$c}",
            $campos
        ));
        $huella = $columnasHuella === null
            ? 'md5(t::text)'
            : 'md5(row('.implode(', ', array_map(fn (string $c) => 't.'.self::identificador($c), $columnasHuella)).')::text)';
        $fila = DB::selectOne("select {$select}, {$huella} as md5_fila from {$tabla} t where id = ?", [$id]);

        if ($fila === null) {
            return null;
        }

        $datos = (array) $fila;
        $md5 = $datos['md5_fila'];
        unset($datos['md5_fila']);

        return ['campos' => $datos, 'md5' => $md5];
    }

    /** @return array<int, string> estado (tgenabled) de los 8 triggers de updated_at */
    public static function estadoTriggers(): array
    {
        $estado = [];
        foreach (self::TRIGGERS_UPDATED_AT as $tabla => $trigger) {
            $fila = DB::selectOne('select t.tgenabled e from pg_trigger t join pg_class c on c.oid = t.tgrelid where c.relname = ? and t.tgname = ?', [$tabla, $trigger]);
            $estado[$trigger] = $fila?->e ?? 'ausente';
        }

        return $estado;
    }

    /**
     * Problemas que impedirían que los triggers se ejecuten con normalidad: algún trigger de
     * updated_at o de validación (del esquema public) no activo, o una sesión en modo réplica
     * (session_replication_role distinto de origin, en el que los triggers no se disparan).
     *
     * @return array<int, string>
     */
    public static function problemasTriggers(): array
    {
        $problemas = [];

        $inactivos = array_keys(array_filter(self::estadoTriggers(), fn ($e) => $e !== 'O'));
        if ($inactivos !== []) {
            $problemas[] = 'Triggers de updated_at no activos: '.implode(', ', $inactivos).'.';
        }

        $otros = array_column(DB::select("select c.relname || '.' || t.tgname as nombre from pg_trigger t
            join pg_class c on c.oid = t.tgrelid join pg_namespace s on s.oid = c.relnamespace
            where s.nspname = 'public' and not t.tgisinternal and t.tgenabled <> 'O'
              and t.tgname not in ('".implode("', '", self::TRIGGERS_UPDATED_AT)."')
            order by 1"), 'nombre');
        if ($otros !== []) {
            $problemas[] = 'Triggers de validación no activos: '.implode(', ', $otros).'.';
        }

        $rol = DB::selectOne("select current_setting('session_replication_role') as r")->r;
        if ($rol !== 'origin') {
            $problemas[] = "session_replication_role es {$rol}: los triggers no se ejecutarían.";
        }

        return $problemas;
    }

    public static function exigirTriggersActivos(string $momento): void
    {
        $problemas = self::problemasTriggers();
        if ($problemas !== []) {
            throw new RuntimeException("Triggers no utilizables {$momento}: ".implode(' ', $problemas));
        }
    }

    /**
     * Comprueba que los cambios de un registro sean exactamente los del plan fijo (mismas filas,
     * columnas y valores) y tengan el estado de fila necesario para verificar la reversión.
     * La firma del registro no basta: no es criptográfica y podría recalcularse.
     *
     * @return array<int, string>
     */
    public static function erroresCambiosDelPlan(array $cambios, PlanReparacion $plan): array
    {
        $esperados = array_map(fn ($e) => [$e['tabla'], $e['columna'], $e['id'], $e['actual'], $e['nuevo']], $plan->entradas());
        $recibidos = array_map(fn ($c) => is_array($c) ? [$c['tabla'] ?? null, $c['columna'] ?? null, $c['id'] ?? null, $c['original'] ?? null, $c['nuevo'] ?? null] : null, $cambios);

        $errores = [];
        if ($recibidos !== $esperados) {
            $errores[] = 'los cambios del registro no coinciden con las entradas del plan.';
        }
        foreach ($cambios as $i => $c) {
            if (! is_array($c['fila_tras_cambio']['campos'] ?? null) || ! is_string($c['fila_tras_cambio']['md5'] ?? null)) {
                $errores[] = 'el cambio '.($i + 1).' no tiene el estado de la fila tras la reparación.';
            }
        }

        return $errores;
    }

    /**
     * Comprueba cada entrada contra la base: valor exacto (sin fracción), evidencia de auditoría,
     * campos de la fila uno a uno y huella completa.
     *
     * @return array<string, array<int, string>> errores por entrada (vacío si todo coincide)
     */
    public function verificar(PlanReparacion $plan): array
    {
        $errores = [];
        foreach ($plan->errores() as $error) {
            $errores['plan'][] = $error;
        }
        if ($errores !== []) {
            return $errores;
        }

        foreach ($plan->entradas() as $e) {
            $clave = PlanReparacion::clave($e);
            $fila = self::estadoFila($e['tabla'], $e['id']);

            if ($fila === null) {
                $errores[$clave][] = 'la fila no existe';

                continue;
            }

            $valor = $fila['campos'][$e['columna']] ?? null;
            if ($valor !== $e['actual'].'.000000') {
                $errores[$clave][] = "valor actual {$valor} distinto del esperado {$e['actual']}";
            }

            foreach ($e['fila']['campos'] as $campo => $esperado) {
                if (($fila['campos'][$campo] ?? null) !== $esperado) {
                    $errores[$clave][] = "campo {$campo} cambió (".var_export($fila['campos'][$campo] ?? null, true).' en lugar de '.var_export($esperado, true).')';
                }
            }
            if ($fila['md5'] !== $e['fila']['md5']) {
                $errores[$clave][] = 'la fila cambió desde el diagnóstico (huella distinta)';
            }

            foreach ($this->erroresEvidencia($e) as $error) {
                $errores[$clave][] = $error;
            }
        }

        return $errores;
    }

    private function erroresEvidencia(array $e): array
    {
        $auditoria = DB::selectOne('select modelo, modelo_id, accion, created_at::text c, valores_nuevos ->> ? v from auditorias where id = ?', [$e['columna'], $e['evidencia']['auditoria_id']]);

        if ($auditoria === null) {
            return ["no existe la auditoría #{$e['evidencia']['auditoria_id']}"];
        }

        $errores = [];
        if ($auditoria->modelo !== PlanReparacion::MODELOS[$e['tabla']] || (int) $auditoria->modelo_id !== (int) $e['id']) {
            $errores[] = "la auditoría #{$e['evidencia']['auditoria_id']} no corresponde a este registro";
        }

        $valor = $e['evidencia']['tipo'] === 'instante' ? substr($auditoria->c, 0, 19) : substr((string) $auditoria->v, 0, 19);
        if ($valor !== $e['evidencia']['valor']) {
            $errores[] = "la auditoría #{$e['evidencia']['auditoria_id']} indica {$valor}, no {$e['evidencia']['valor']}";
        }
        if ($e['evidencia']['tipo'] === 'instante' && $e['columna'] === 'created_at' && $auditoria->accion !== 'creado') {
            $errores[] = "la auditoría #{$e['evidencia']['auditoria_id']} no es de creación";
        }

        return $errores;
    }

    /**
     * Aplica el plan (llamar dentro de una transacción). Devuelve los cambios realizados.
     *
     * @return array<int, array{tabla:string, columna:string, id:int, original:string, nuevo:string, fila_tras_cambio:array}>
     */
    public function aplicar(PlanReparacion $plan, string $origen): array
    {
        self::exigirTriggersActivos('antes de empezar');
        $this->bloquear($plan->entradas());

        $errores = $this->verificar($plan);
        if ($errores !== []) {
            throw new DiscrepanciaException($errores);
        }

        $exclusionesAntes = $this->valoresExcluidos($plan);
        $cambios = $this->escribir($plan->entradas(), 'actual', 'nuevo');

        if ($this->valoresExcluidos($plan) !== $exclusionesAntes) {
            throw new RuntimeException('Una fila excluida cambió durante la reparación.');
        }
        $this->registrarEnAuditoria($cambios, 'original', 'nuevo', $origen);

        return $cambios;
    }

    /**
     * Revierte los cambios de un registro de reparación (llamar dentro de una transacción).
     * Exige que los cambios sean los del plan, que cada valor actual sea exactamente el que dejó la
     * reparación y que la fila no haya cambiado.
     */
    public function revertir(PlanReparacion $plan, array $cambios, string $origen, ?array $columnasOriginales = null): array
    {
        self::exigirTriggersActivos('antes de empezar');
        // Antes de usar tablas o columnas del registro en SQL: deben ser exactamente las del plan.
        if (($errores = self::erroresCambiosDelPlan($cambios, $plan)) !== []) {
            throw new DiscrepanciaException(['registro' => $errores]);
        }
        $this->bloquear($cambios);

        $errores = $this->verificarReversion($plan, $cambios, $columnasOriginales);
        if ($errores !== []) {
            throw new DiscrepanciaException($errores);
        }

        $revertidos = $this->escribir(array_map(fn ($c) => [...$c, 'actual' => $c['nuevo'], 'nuevo' => $c['original']], $cambios), 'actual', 'nuevo');
        $this->registrarEnAuditoria($revertidos, 'original', 'nuevo', $origen);

        return $revertidos;
    }

    /**
     * @param  array<string, array<int, string>>|null  $columnasOriginales  columnas por tabla de un AnexoColumnas verificado
     * @return array<string, array<int, string>> errores por entrada (vacío si se puede revertir)
     */
    public function verificarReversion(PlanReparacion $plan, array $cambios, ?array $columnasOriginales = null): array
    {
        $errores = [];
        foreach (self::erroresCambiosDelPlan($cambios, $plan) as $error) {
            $errores['registro'][] = $error;
        }
        if ($errores !== []) {
            return $errores;
        }

        foreach ($cambios as $c) {
            $clave = PlanReparacion::clave($c);
            $fila = self::estadoFila($c['tabla'], $c['id'], $columnasOriginales[$c['tabla']] ?? null);
            if ($fila === null) {
                $errores[$clave][] = 'la fila no existe';

                continue;
            }
            if (($fila['campos'][$c['columna']] ?? null) !== $c['nuevo'].'.000000') {
                $errores[$clave][] = 'el valor actual no es el que dejó la reparación';
            }
            foreach ($c['fila_tras_cambio']['campos'] as $campo => $esperado) {
                if (($fila['campos'][$campo] ?? null) !== $esperado) {
                    $errores[$clave][] = "campo {$campo} cambió después de la reparación";
                }
            }
            if ($fila['md5'] !== $c['fila_tras_cambio']['md5']) {
                $errores[$clave][] = 'la fila cambió después de la reparación (huella distinta)';
            }
        }

        return $errores;
    }

    /** Nombre de columna validado y entre comillas (proviene de un anexo verificado, nunca del usuario). */
    public static function identificador(string $columna): string
    {
        if (! preg_match('/^[a-z_][a-z0-9_]*$/', $columna)) {
            throw new RuntimeException("Nombre de columna no válido: {$columna}.");
        }

        return '"'.$columna.'"';
    }

    /** Bloquea las filas afectadas hasta el final de la transacción. */
    private function bloquear(array $entradas): void
    {
        foreach (collect($entradas)->groupBy('tabla') as $tabla => $filas) {
            $ids = $filas->pluck('id')->unique()->values()->all();
            DB::select("select id from {$tabla} where id in (".implode(',', array_fill(0, count($ids), '?')).') for update', $ids);
        }
    }

    /**
     * Escribe columna = valor nuevo solo donde columna = valor actual (1 fila exacta por entrada),
     * con el trigger de updated_at de esas tablas desactivado únicamente durante la transacción.
     */
    private function escribir(array $entradas, string $desde, string $hacia): array
    {
        $tablas = collect($entradas)->pluck('tabla')->unique()->values()->all();

        // Las validaciones diferidas (p. ej. trigger_validar_minimo_fotos) se ejecutan al terminar
        // cada sentencia en lugar de al confirmar: siguen validando, y no quedan eventos pendientes
        // que impidan el ALTER TABLE … ENABLE TRIGGER (PostgreSQL lo rechaza con eventos pendientes).
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        foreach ($tablas as $tabla) {
            DB::statement("ALTER TABLE {$tabla} DISABLE TRIGGER ".self::TRIGGERS_UPDATED_AT[$tabla]);
        }

        $cambios = [];
        foreach ($entradas as $e) {
            $filas = DB::update(
                "update {$e['tabla']} set {$e['columna']} = ?::timestamptz where id = ? and {$e['columna']} = ?::timestamptz",
                [$e[$hacia].'+00', $e['id'], $e[$desde].'+00']
            );
            if ($filas !== 1) {
                throw new RuntimeException(PlanReparacion::clave($e)." afectó a {$filas} filas (se esperaba 1).");
            }
            $cambios[] = ['tabla' => $e['tabla'], 'columna' => $e['columna'], 'id' => $e['id'], 'original' => $e[$desde], 'nuevo' => $e[$hacia]];
        }

        foreach ($tablas as $tabla) {
            DB::statement("ALTER TABLE {$tabla} ENABLE TRIGGER ".self::TRIGGERS_UPDATED_AT[$tabla]);
        }
        self::exigirTriggersActivos('al terminar');

        // Comprobación posterior (ya con todas las columnas escritas): cada una tiene su valor
        // nuevo; se guarda el estado final de la fila para poder verificar una futura reversión.
        foreach ($cambios as &$c) {
            $fila = self::estadoFila($c['tabla'], $c['id']);
            if (($fila['campos'][$c['columna']] ?? null) !== $c['nuevo'].'.000000') {
                throw new RuntimeException(PlanReparacion::clave($c).' no quedó con el valor esperado.');
            }
            $c['fila_tras_cambio'] = $fila;
        }
        unset($c);

        return $cambios;
    }

    /** Valores actuales de las columnas excluidas (para comprobar que no se tocaron). */
    private function valoresExcluidos(PlanReparacion $plan): array
    {
        $valores = [];
        foreach ($plan->exclusiones() as $x) {
            $fila = DB::selectOne("select to_char({$x['columna']} at time zone 'UTC', 'YYYY-MM-DD HH24:MI:SS.US') v from {$x['tabla']} where id = ?", [$x['id']]);
            $valores[PlanReparacion::clave($x)] = $fila?->v;
        }

        return $valores;
    }

    /** Un registro de auditoría por fila con los valores anterior y nuevo de cada columna. */
    private function registrarEnAuditoria(array $cambios, string $antes, string $despues, string $origen): void
    {
        foreach (collect($cambios)->groupBy(fn ($c) => "{$c['tabla']}#{$c['id']}") as $grupo) {
            $primero = $grupo->first();
            Auditoria::create([
                'usuario_id' => null,
                'accion' => 'actualizado',
                'modelo' => PlanReparacion::MODELOS[$primero['tabla']],
                'modelo_id' => $primero['id'],
                'valores_anteriores' => $grupo->mapWithKeys(fn ($c) => [$c['columna'] => $c[$antes]])->all(),
                'valores_nuevos' => $grupo->mapWithKeys(fn ($c) => [$c['columna'] => $c[$despues]])->all(),
                'ip' => null,
                'user_agent' => $origen,
            ]);
        }
    }
}
