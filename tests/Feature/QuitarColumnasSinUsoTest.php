<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migración 2026_10_24: quita las columnas sin uso de solicitudes_adopcion y las funciones dejan de mencionarlas.
 */
class QuitarColumnasSinUsoTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNAS = [
        'entrevista_fecha', 'entrevista_medio', 'entrevista_resultado', 'entrevista_notas',
        'visita_previa_fecha', 'visita_previa_resultado', 'visita_previa_notas',
        'fecha_entrega_acordada', 'permitir_otra_adopcion',
    ];

    public function test_las_columnas_y_sus_restricciones_ya_no_existen_y_las_funciones_no_las_mencionan(): void
    {
        foreach (self::COLUMNAS as $columna) {
            $this->assertFalse(Schema::hasColumn('solicitudes_adopcion', $columna), $columna);
        }

        $restricciones = DB::table('pg_constraint')->where('conrelid', DB::raw("'solicitudes_adopcion'::regclass"))
            ->where(fn ($q) => $q->where('conname', 'like', '%entrevista%')->orWhere('conname', 'like', '%visita_previa%'))->count();
        $this->assertSame(0, $restricciones);

        foreach (DB::select("select proname, prosrc from pg_proc p join pg_namespace n on n.oid = p.pronamespace where n.nspname = 'public'") as $funcion) {
            $this->assertDoesNotMatchRegularExpression('/entrevista_|visita_previa_|fecha_entrega_acordada|permitir_otra_adopcion/', $funcion->prosrc, $funcion->proname);
        }
    }

    public function test_la_migracion_se_puede_revertir_y_volver_a_aplicar(): void
    {
        $migracion = require database_path('migrations/2026_10_24_000000_quitar_columnas_sin_uso_de_solicitudes_adopcion.php');

        $migracion->down();
        foreach (self::COLUMNAS as $columna) {
            $this->assertTrue(Schema::hasColumn('solicitudes_adopcion', $columna), "down() debe recrear {$columna}");
        }
        $this->assertStringContainsString('permitir_otra_adopcion', DB::selectOne("select prosrc from pg_proc where proname = 'procesar_aceptacion_solicitud'")->prosrc);

        $migracion->up();
        foreach (self::COLUMNAS as $columna) {
            $this->assertFalse(Schema::hasColumn('solicitudes_adopcion', $columna), "up() debe quitar {$columna}");
        }
    }
}
