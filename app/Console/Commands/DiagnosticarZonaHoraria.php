<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diagnóstico (SOLO LECTURA) del desfase de zona horaria en fechas históricas.
 *
 * Contexto: Laravel escribe las fechas como texto "Y-m-d H:i:s" en su zona (UTC) y la sesión de
 * PostgreSQL (America/La_Paz) las interpreta como hora local: el instante guardado queda +4 h.
 * Las fechas que escribe PostgreSQL (CURRENT_TIMESTAMP en valores por defecto y triggers) son correctas.
 *
 * Evidencia: la tabla auditorias (instante UTC correcto, escrito por Laravel).
 * Indicio (NO es prueba): la fracción de segundo. Laravel escribe sin fracciones y PostgreSQL con
 * microsegundos. Nunca basta por sí sola para declarar una fecha correcta o desfasada.
 *
 * Categorías por fila y columna:
 *   confirmado    evidencia de +4 h (±2 s) en un valor sin fracción (escrito por Laravel)
 *   correcto      evidencia de 0 h (±2 s); la evidencia prevalece sobre el indicio
 *   conflicto     evidencia de +4 h y de 0 h a la vez, o +4 h en un valor con microsegundos
 *   sospechoso    sin ningún evento de auditoría y sin fracción (indicio de desfase, sin prueba)
 *   indeterminado sin evidencia concluyente: hay auditoría que no coincide, o no hay auditoría
 *                 y el valor tiene microsegundos
 *
 * Solo "confirmado" podría corregirse automáticamente; sospechosos e indeterminados quedan excluidos.
 * No modifica nada: no tiene modo de aplicación y ejecuta todo en una transacción READ ONLY.
 */
class DiagnosticarZonaHoraria extends Command
{
    protected $signature = 'zona-horaria:diagnosticar
        {--detalle : Muestra cada fila candidata con su valor actual y el propuesto}';

    protected $description = 'Simulación de solo lectura: muestra qué fechas históricas tienen el desfase de +4 h y cuáles no pueden determinarse';

    private const DESFASE = 14400; // segundos (+4 h)

    private const TOLERANCIA = 2;  // segundos

    private const CATEGORIAS = ['confirmado', 'correcto', 'conflicto', 'sospechoso', 'indeterminado'];

    /** Columnas escritas por Laravel (o que pueden serlo). tipo de evidencia: creado | actualizacion | valor */
    private const COLUMNAS = [
        'usuarios' => ['modelo' => 'User', 'columnas' => ['created_at' => 'creado', 'updated_at' => 'actualizacion', 'email_verified_at' => 'valor']],
        'solicitudes_adopcion' => ['modelo' => 'SolicitudAdopcion', 'columnas' => ['created_at' => 'creado', 'updated_at' => 'actualizacion']],
        'postulaciones_voluntariado' => ['modelo' => 'PostulacionVoluntariado', 'columnas' => ['created_at' => 'creado', 'updated_at' => 'actualizacion', 'revisado_at' => 'valor']],
        'mascotas' => ['modelo' => 'Mascota', 'columnas' => ['created_at' => 'creado', 'updated_at' => 'actualizacion']],
        'fotos_mascota' => ['modelo' => 'FotoMascota', 'columnas' => ['created_at' => 'creado']],
        'voluntarios' => ['modelo' => 'Voluntario', 'columnas' => ['created_at' => 'creado', 'updated_at' => 'actualizacion']],
        'seguimientos_adopcion' => ['modelo' => 'SeguimientoAdopcion', 'columnas' => ['created_at' => 'creado', 'updated_at' => 'actualizacion', 'fecha_inicio' => 'valor', 'fecha_cierre' => 'valor']],
        'visitas_seguimiento' => ['modelo' => 'VisitaSeguimiento', 'columnas' => ['created_at' => 'creado', 'updated_at' => 'actualizacion']],
        'incidencias' => ['modelo' => 'Incidencia', 'columnas' => ['created_at' => 'creado', 'updated_at' => 'actualizacion', 'atendida_at' => 'valor']],
    ];

    /** Columnas que no se tocan nunca, con el motivo. */
    private const EXCLUIDAS = [
        'visitas_seguimiento.fecha_realizada' => 'Fecha introducida por la persona como hora local: ya es correcta.',
        'visitas_seguimiento.fecha_programada' => 'Tipo date (sin hora ni zona): no tiene desfase.',
        'password_reset_tokens.created_at' => 'Tokens de recuperación: no se modifican; caducan solos.',
        'auditorias.created_at' => 'timestamp sin zona escrito en UTC: es la referencia. Ver estrategia de auditoría.',
        'failed_jobs.failed_at' => 'Fuera de alcance (valor por defecto de PostgreSQL).',
    ];

    public function handle(): int
    {
        $base = DB::connection()->getDatabaseName();
        $this->info("Diagnóstico de zona horaria (SOLO LECTURA) | base: {$base} | Laravel: ".config('app.timezone').' | sesión PostgreSQL: '.DB::selectOne('show timezone')->TimeZone);

        DB::beginTransaction();
        DB::statement('SET TRANSACTION READ ONLY');

        try {
            $resumen = [];
            $detalle = [];

            foreach (self::COLUMNAS as $tabla => $definicion) {
                foreach ($definicion['columnas'] as $columna => $tipoEvidencia) {
                    $conteo = array_fill_keys(self::CATEGORIAS, 0);

                    foreach ($this->filas($tabla, $columna) as $fila) {
                        $clase = $this->clasificar($definicion['modelo'], $columna, $tipoEvidencia, $fila);
                        $conteo[$clase]++;

                        $detalle[] = [
                            "{$tabla}.{$columna}", $fila->id, $clase,
                            $fila->con_fraccion ? 'con µs' : 'sin fracción',
                            $fila->actual_utc.' UTC', $fila->actual_lapaz.' La Paz',
                            match ($clase) {
                                'confirmado' => $fila->propuesto_lapaz.' La Paz',
                                'correcto' => '(sin cambio)',
                                default => '(excluido de la corrección)',
                            },
                        ];
                    }

                    $resumen[] = ["{$tabla}.{$columna}", array_sum($conteo), ...array_values($conteo), $this->triggersQueSeDisparan($tabla, $columna)];
                }
            }

            $this->newLine();
            $this->table(
                ['Columna', 'Con valor', 'Confirmadas (+4 h)', 'Correctas (con evidencia)', 'Conflicto', 'Sospechosas', 'Indeterminadas', 'Triggers que dispararía un UPDATE'],
                $resumen
            );

            if ($this->option('detalle') && $detalle !== []) {
                $this->newLine();
                $this->table(['Columna', 'ID', 'Categoría', 'Indicio', 'Valor actual', 'Valor actual', 'Valor propuesto (−4 h)'], $detalle);
            }

            $this->newLine();
            $this->line('Columnas excluidas:');
            foreach (self::EXCLUIDAS as $columna => $motivo) {
                $this->line("  - {$columna}: {$motivo}");
            }
            $this->newLine();
            $this->line('La fracción de segundo es solo un indicio; la clasificación depende de la evidencia de auditoría.');
            $this->warn('Solo las "confirmadas" podrían corregirse. "Sospechosas" e "indeterminadas" no pueden determinarse con certeza y quedan excluidas de cualquier corrección automática.');
            $this->line('Este comando no modifica ningún registro.');
        } finally {
            DB::rollBack();
        }

        return self::SUCCESS;
    }

    private function filas(string $tabla, string $columna): array
    {
        return DB::select("
            select id,
                   (extract(microseconds from {$columna})::bigint % 1000000) <> 0 as con_fraccion,
                   to_char({$columna} at time zone 'UTC', 'YYYY-MM-DD HH24:MI:SS') as actual_utc,
                   to_char({$columna} at time zone 'America/La_Paz', 'YYYY-MM-DD HH24:MI:SS') as actual_lapaz,
                   to_char(({$columna} - interval '4 hours') at time zone 'America/La_Paz', 'YYYY-MM-DD HH24:MI:SS') as propuesto_lapaz,
                   extract(epoch from {$columna}) as epoca
            from {$tabla}
            where {$columna} is not null
            order by id");
    }

    /** Combina la evidencia de auditoría (UTC) con el indicio de la fracción de segundo. */
    private function clasificar(string $modelo, string $columna, string $tipo, object $fila): string
    {
        $diferencias = match ($tipo) {
            // Momento de creación: evento "creado" del mismo registro.
            'creado' => DB::select("select extract(epoch from ? ::timestamptz) - extract(epoch from (created_at at time zone 'UTC')) as dif
                from auditorias where modelo = ? and modelo_id = ? and accion = 'creado'", [$fila->actual_utc.'+00', $modelo, $fila->id]),
            // Última escritura: cualquier evento del registro.
            'actualizacion' => DB::select("select extract(epoch from ? ::timestamptz) - extract(epoch from (created_at at time zone 'UTC')) as dif
                from auditorias where modelo = ? and modelo_id = ?", [$fila->actual_utc.'+00', $modelo, $fila->id]),
            // Valor escrito con now(): la auditoría guarda el texto exacto que envió Laravel (en UTC).
            'valor' => DB::select("select extract(epoch from ? ::timestamptz) - extract(epoch from ((valores_nuevos ->> ?)::timestamp at time zone 'UTC')) as dif
                from auditorias where modelo = ? and modelo_id = ? and jsonb_exists(valores_nuevos, ?)", [$fila->actual_utc.'+00', $columna, $modelo, $fila->id, $columna]),
        };

        $valores = array_map(fn ($d) => (float) $d->dif, $diferencias);
        $desfase = (bool) array_filter($valores, fn ($dif) => abs($dif - self::DESFASE) <= self::TOLERANCIA);
        $exacto = (bool) array_filter($valores, fn ($dif) => abs($dif) <= self::TOLERANCIA);

        return match (true) {
            $desfase && $exacto => 'conflicto',
            // +4 h en un valor con microsegundos no puede producirlo el mecanismo conocido.
            $desfase => $fila->con_fraccion ? 'conflicto' : 'confirmado',
            // La evidencia manda: 0 h es correcto aunque no tenga fracción (p. ej. escrito por
            // Laravel después de activar la zona horaria coherente).
            $exacto => 'correcto',
            $valores !== [] => 'indeterminado',      // hay auditoría, pero no coincide
            $fila->con_fraccion => 'indeterminado',  // sin auditoría; indicio de PostgreSQL, sin prueba
            default => 'sospechoso',                 // sin auditoría; indicio de Laravel, sin prueba
        };
    }

    /** Triggers BEFORE/AFTER UPDATE que se ejecutarían si se actualizara esa columna. */
    private function triggersQueSeDisparan(string $tabla, string $columna): string
    {
        $nombres = [];
        foreach (DB::select("select t.tgname, pg_get_triggerdef(t.oid) as def from pg_trigger t join pg_class c on c.oid = t.tgrelid
            where c.relname = ? and not t.tgisinternal", [$tabla]) as $trigger) {
            if (! preg_match('/(BEFORE|AFTER) (.+?) ON /', $trigger->def, $m) || ! str_contains($m[2], 'UPDATE')) {
                continue;
            }
            $columnas = preg_match('/UPDATE OF ([\w, ]+)/', $m[2], $of) ? array_map('trim', explode(',', $of[1])) : null;
            if ($columnas === null || in_array($columna, $columnas, true)) {
                $nombres[] = $trigger->tgname;
            }
        }

        return $nombres === [] ? '—' : implode(', ', $nombres);
    }
}
