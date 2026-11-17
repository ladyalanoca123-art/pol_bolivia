<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use App\Services\ExportadorReporte;
use App\Support\PeriodoReporte;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\TestCase;

/**
 * Descargas PDF y Excel de Reportes (admin.reportes.pdf / admin.reportes.excel): permisos, validación,
 * archivos válidos y datos idénticos a los de la pantalla para el mismo período. Hoy = 14/10/2026.
 */
class ReporteExportacionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('pol_bolivia_test', DB::connection()->getDatabaseName());
        $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00', PeriodoReporte::ZONA));
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    // ---------- PDF ----------

    public function test_el_administrador_descarga_un_pdf_valido_del_periodo(): void
    {
        $this->escenario();

        $respuesta = $this->actingAs($this->admin)->get(route('admin.reportes.pdf', ['periodo' => 'este_mes']));

        $respuesta->assertOk();
        $this->assertSame('application/pdf', $respuesta->headers->get('Content-Type'));
        $this->assertSame('attachment; filename=reporte-pol-bolivia_2026-10-01_a_2026-10-14.pdf', $respuesta->headers->get('Content-Disposition'));
        $this->assertPdfValido($respuesta->getContent());
    }

    public function test_el_pdf_recibe_los_mismos_datos_que_la_pantalla_para_el_periodo(): void
    {
        $this->escenario();
        View::composer('admin.reportes.pdf', function ($vista) {
            $this->capturado = $vista->getData()['s'];
        });

        foreach ([['periodo' => 'este_mes'], ['periodo' => 'mes_anterior'], ['periodo' => 'personalizado', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-14'], []] as $parametros) {
            $this->assertPdfValido($this->actingAs($this->admin)->get(route('admin.reportes.pdf', $parametros))->assertOk()->getContent());

            $capturado = $this->capturado;
            $esperado = app(ExportadorReporte::class)->secciones($this->datosDePantalla($parametros));
            // Solo puede diferir la hora de generación (minuto de cada petición).
            unset($esperado['generado'], $capturado['generado']);
            $this->assertEquals($esperado, $capturado, json_encode($parametros));
        }
    }

    public function test_el_pdf_es_un_informe_corto_con_periodo_estado_actual_y_atencion(): void
    {
        $this->escenario();
        $s = app(ExportadorReporte::class)->secciones($this->datosDePantalla(['periodo' => 'este_mes']));
        $html = view('admin.reportes.pdf', ['s' => $s])->render();

        $this->assertStringContainsString('Reporte administrativo POL-Bolivia', $html);
        $this->assertStringContainsString('Período:</strong> Este mes (1 al 14 de octubre de 2026)', $html);
        $this->assertStringContainsString('Comparado con el período anterior: 1 al 14 de septiembre de 2026', $html);
        // Las dos etiquetas separan lo del período de lo actual.
        $this->assertStringContainsString(ExportadorReporte::ETIQUETA_PERIODO, $html);
        $this->assertStringContainsString(ExportadorReporte::ETIQUETA_ACTUAL, $html);
        $this->assertEquals(['Adopciones concretadas', 2, 1], $s['periodoKpis'][1]);
        $this->assertMatchesRegularExpression('/Visitas de seguimiento cumplidas<\/div>\s*<div class="valor">Sin casos/', $html);
        // Requiere atención: la solicitud recibida el 10/10 espera 4 días; la reservada no tiene acta.
        $atencion = collect($s['atencion'])->keyBy('clave');
        $this->assertSame(1, $atencion['solicitudes_por_evaluar']['total']);
        $this->assertSame('Recibida', $atencion['solicitudes_por_evaluar']['filas'][0][2]);
        $this->assertSame(4, $atencion['solicitudes_por_evaluar']['filas'][0][3]);
        $this->assertSame(1, $atencion['reservadas_sin_acta']['total']);
        $this->assertSame(0, $atencion['visitas_vencidas']['total']);
        $this->assertStringContainsString('Reservadas sin foto del acta firmada', $html);
        $this->assertStringContainsString('Nada pendiente.', $html);
        // Sin notas técnicas para el administrador.
        $this->assertStringNotContainsString('aprobada_at', $html);
        $this->assertStringNotContainsString('resuelta_at', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('http', preg_replace('/<!DOCTYPE[^>]*>/', '', $html), 'sin recursos externos');
    }

    public function test_el_periodo_hoy_se_compara_con_ayer(): void
    {
        $hoy = PeriodoReporte::crear('hoy');
        $this->assertSame('Hoy (14 de octubre de 2026)', $hoy->etiqueta());
        $this->assertSame('13 de octubre de 2026', $hoy->anterior()->rango());
        $this->assertSame(1, $hoy->dias());

        $this->solicitud('pendiente', '2026-10-14 08:00:00-04');
        $this->solicitud('pendiente', '2026-10-13 08:00:00-04');
        $pantalla = $this->datosDePantalla(['periodo' => 'hoy']);
        $this->assertSame(['actual' => 1, 'anterior' => 1], array_intersect_key($pantalla['indicadoresPeriodo']['solicitudes_recibidas'], ['actual' => 0, 'anterior' => 0]));

        $pdf = $this->actingAs($this->admin)->get(route('admin.reportes.pdf', ['periodo' => 'hoy']));
        $this->assertSame('attachment; filename=reporte-pol-bolivia_2026-10-14.pdf', $pdf->headers->get('Content-Disposition'));
        $this->actingAs($this->admin)->get(route('admin.reportes.index'))->assertOk()
            ->assertSee(route('admin.reportes.index', ['periodo' => 'hoy']), false)->assertSee('Hoy');
    }

    // ---------- Excel ----------

    public function test_el_administrador_descarga_un_excel_valido_con_sus_hojas_y_encabezados(): void
    {
        $this->escenario();

        $respuesta = $this->actingAs($this->admin)->get(route('admin.reportes.excel', ['periodo' => 'este_mes']));

        $respuesta->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('filename=reporte-pol-bolivia_2026-10-01_a_2026-10-14.xlsx', $respuesta->headers->get('Content-Disposition'));
        $libro = $this->leerExcel($respuesta->streamedContent());

        $this->assertSame(ExportadorReporte::HOJAS, $libro->getSheetNames());
        $this->assertSame('Reporte administrativo POL-Bolivia · Resumen', $libro->getSheetByName('Resumen')->getCell('A1')->getValue());
        $solicitudes = $libro->getSheetByName('Solicitudes');
        $this->assertSame(['N.º', 'N.º de acta', 'Solicitante', 'Mascota', 'Estado', 'Revisión', 'Fecha de solicitud', 'Fecha de resolución', 'Fecha de entrega', 'Foto del acta'], $solicitudes->rangeToArray('A5:J5')[0]);
        // Filtros y encabezado fijo en cada hoja de consulta.
        foreach (['Mascotas', 'Solicitudes'] as $nombre) {
            $this->assertStringStartsWith('A5:', $libro->getSheetByName($nombre)->getAutoFilter()->getRange(), $nombre);
            $this->assertSame('A6', $libro->getSheetByName($nombre)->getFreezePane(), $nombre);
        }
        $this->assertSame('dd/mm/yyyy', $solicitudes->getStyle('G6')->getNumberFormat()->getFormatCode());
    }

    public function test_el_excel_coincide_con_los_datos_de_la_pantalla_y_lista_los_registros(): void
    {
        $this->escenario();
        $pantalla = $this->datosDePantalla(['periodo' => 'este_mes']);

        $libro = $this->leerExcel($this->actingAs($this->admin)->get(route('admin.reportes.excel', ['periodo' => 'este_mes']))->streamedContent());

        $resumen = collect($libro->getSheetByName('Resumen')->toArray(null, true, false))->keyBy(0);
        $this->assertEquals($pantalla['indicadoresPeriodo']['solicitudes_recibidas']['actual'], $resumen['Solicitudes recibidas'][1]);
        $this->assertEquals($pantalla['indicadoresPeriodo']['adopciones_completadas']['actual'], $resumen['Adopciones concretadas'][1]);
        $this->assertEquals($pantalla['mascotasPorEstado']['disponible'], $resumen['Mascotas disponibles'][1]);
        $this->assertEquals(1, $resumen['Reservadas sin foto del acta firmada'][1]);

        // Solicitudes creadas en el período (5 del 1 al 14 de octubre) y todas las mascotas (inventario).
        $solicitudes = array_filter($libro->getSheetByName('Solicitudes')->rangeToArray('A6:J20', null, true, false), fn ($f) => $f[0] !== null);
        $this->assertCount(5, $solicitudes);
        $this->assertEqualsCanonicalizing(['Adopción concretada', 'Rechazada', 'Recibida', 'Reservada', 'Entrega no concretada'], array_column($solicitudes, 4));
        $this->assertSame(['No'], array_values(array_column(array_filter($solicitudes, fn ($f) => $f[4] === 'Reservada'), 9)));
        $mascotas = array_filter($libro->getSheetByName('Mascotas')->rangeToArray('A6:H30', null, true, false), fn ($f) => $f[0] !== null);
        $this->assertCount($pantalla['totalMascotas'], $mascotas);
        $bruno = collect($mascotas)->firstWhere(0, 'Bruno');
        $this->assertSame(['Bruno', 'Perro', 'Hembra', 'Grande', '2 años', 'Disponible'], array_slice($bruno, 0, 6));
        $this->assertSame(142, (int) $bruno[7], 'días desde el 25/05');
    }

    public function test_el_excel_no_incluye_datos_de_contacto(): void
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano', 'email' => 'privado@ejemplo.test', 'telefono' => '71234567']);
        $solicitud = SolicitudAdopcion::create([...CuestionarioAdopcionDatos::registro(), 'solicitante_id' => $ciudadano->id, 'mascota_id' => $this->mascota()->id, 'estado' => 'pendiente']);

        $libro = $this->leerExcel($this->actingAs($this->admin)->get(route('admin.reportes.excel'))->streamedContent());
        $todo = json_encode(array_map(fn ($h) => $h->toArray(), $libro->getAllSheets()));

        $this->assertStringContainsString($ciudadano->nombres, $todo);
        $this->assertStringNotContainsString('privado@ejemplo.test', $todo);
        $this->assertStringNotContainsString('71234567', $todo);
        $this->assertNotNull($solicitud->id);
    }

    public function test_registros_aceptados_sin_fecha_se_informan_en_pantalla_sin_fecha_inventada(): void
    {
        $this->escenario();
        $this->solicitud('aceptada', '2025-03-02 12:00:00-04');
        $sinFecha = SolicitudAdopcion::query()->where('estado', 'aceptada')->latest('id')->firstOrFail();
        DB::table('solicitudes_adopcion')->where('id', $sinFecha->id)->update(['resuelta_at' => null]);

        $pantalla = $this->datosDePantalla([]);
        $this->assertSame(4, $pantalla['totalAdopciones']);
        $this->assertSame(1, $pantalla['adopcionesSinFechaResolucion']);
        $this->actingAs($this->admin)->get(route('admin.reportes.index'))
            ->assertOk()->assertSee('1 adopción antigua no tiene fecha registrada')->assertDontSee('verificable');

        $libro = $this->leerExcel($this->actingAs($this->admin)->get(route('admin.reportes.excel'))->streamedContent());
        $fila = collect($libro->getSheetByName('Solicitudes')->toArray(null, true, false))->firstWhere(0, $sinFecha->id);
        $this->assertNull($fila[7], 'sin fecha de resolución inventada');
    }

    public function test_los_dos_formatos_respetan_el_periodo_solicitado(): void
    {
        $this->escenario();
        $resumen = fn (array $p) => collect($this->leerExcel($this->actingAs($this->admin)->get(route('admin.reportes.excel', $p))->streamedContent())->getSheetByName('Resumen')->toArray(null, true, false))->firstWhere(0, 'Adopciones concretadas');

        $this->assertEquals(['Adopciones concretadas', 2, 1], array_slice($resumen(['periodo' => 'este_mes']), 0, 3));
        $this->assertEquals(['Adopciones concretadas', 1, 0], array_slice($resumen(['periodo' => 'mes_anterior']), 0, 3));
        $this->assertEquals(['Adopciones concretadas', 3, 0], array_slice($resumen(['periodo' => 'personalizado', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-14']), 0, 3));

        $pdf = $this->actingAs($this->admin)->get(route('admin.reportes.pdf', ['periodo' => 'personalizado', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-30']));
        $this->assertSame('attachment; filename=reporte-pol-bolivia_2026-09-01_a_2026-09-30.pdf', $pdf->headers->get('Content-Disposition'));
    }

    // ---------- Validación y permisos ----------

    public function test_un_periodo_invalido_se_rechaza_sin_generar_archivos(): void
    {
        foreach (['admin.reportes.pdf', 'admin.reportes.excel'] as $ruta) {
            foreach ([['periodo' => 'semana'], ['periodo' => 'personalizado', 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-01'], ['periodo' => 'personalizado', 'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-20']] as $parametros) {
                $respuesta = $this->actingAs($this->admin)->from(route('admin.reportes.index'))->get(route($ruta, $parametros));
                $respuesta->assertRedirect(route('admin.reportes.index'))->assertSessionHasErrors();
                $this->assertStringNotContainsString('attachment', (string) $respuesta->headers->get('Content-Disposition'));
            }
        }
    }

    public function test_solo_el_administrador_activo_puede_descargar(): void
    {
        foreach (['admin.reportes.pdf', 'admin.reportes.excel'] as $ruta) {
            foreach ([['rol' => 'ciudadano'], ['rol' => 'voluntario'], ['rol' => 'administrador', 'estado' => false]] as $atributos) {
                $this->actingAs(User::factory()->create($atributos))->get(route($ruta, ['periodo' => 'este_mes']))->assertForbidden();
            }
        }
        auth()->logout();
        foreach (['admin.reportes.pdf', 'admin.reportes.excel'] as $ruta) {
            $this->get(route($ruta, ['periodo' => 'este_mes']))->assertRedirect(route('login'));
        }
    }

    // ---------- Casos sin datos y seguridad del contenido ----------

    public function test_sin_registros_ambos_formatos_se_generan_con_sus_estados_vacios(): void
    {
        foreach ([['periodo' => 'este_mes'], []] as $parametros) {
            $this->assertPdfValido($this->actingAs($this->admin)->get(route('admin.reportes.pdf', $parametros))->assertOk()->getContent());
            $libro = $this->leerExcel($this->actingAs($this->admin)->get(route('admin.reportes.excel', $parametros))->assertOk()->streamedContent());
            $this->assertSame(ExportadorReporte::HOJAS, $libro->getSheetNames());
            $this->assertSame('No hay mascotas registradas.', $libro->getSheetByName('Mascotas')->getCell('A6')->getValue());
            $this->assertSame('No hay incidencias en este período.', $libro->getSheetByName('Incidencias')->getCell('A6')->getValue());
        }

        $sinPeriodo = $this->leerExcel($this->actingAs($this->admin)->get(route('admin.reportes.excel'))->streamedContent());
        $this->assertContains('Elige un período en la pantalla de reportes para comparar con el período anterior.', array_merge(...$sinPeriodo->getSheetByName('Resumen')->toArray()));
        $this->assertStringContainsString('reporte-pol-bolivia_acumulado_2026-10-14.pdf', $this->actingAs($this->admin)->get(route('admin.reportes.pdf'))->headers->get('Content-Disposition'));

        $conPeriodo = $this->leerExcel($this->actingAs($this->admin)->get(route('admin.reportes.excel', ['periodo' => 'este_mes']))->streamedContent());
        $this->assertSame('Sin casos', collect($conPeriodo->getSheetByName('Resumen')->toArray())->firstWhere(0, 'Visitas de seguimiento cumplidas')[1]);
    }

    public function test_los_textos_se_exportan_como_texto_y_no_como_formulas(): void
    {
        $this->mascota(['nombre' => '=HYPERLINK("http://ejemplo.test","clic")'], '2026-05-01 10:00:00-04');

        $hoja = $this->leerExcel($this->actingAs($this->admin)->get(route('admin.reportes.excel'))->streamedContent())->getSheetByName('Mascotas');
        $celda = collect(range(1, 30))->map(fn ($f) => $hoja->getCell("A{$f}"))->first(fn ($c) => str_starts_with((string) $c->getValue(), '=HYPERLINK'));

        $this->assertNotNull($celda);
        $this->assertSame(DataType::TYPE_STRING, $celda->getDataType());
    }

    public function test_la_pantalla_de_reportes_ofrece_las_descargas_del_periodo_seleccionado(): void
    {
        $this->actingAs($this->admin)->get(route('admin.reportes.index', ['periodo' => 'personalizado', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-30']))
            ->assertOk()
            ->assertSee(route('admin.reportes.pdf', ['periodo' => 'personalizado', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-30']))
            ->assertSee(route('admin.reportes.excel', ['periodo' => 'personalizado', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-30']))
            ->assertSee('Descargar PDF')->assertSee('Descargar Excel')
            ->assertSee('bg-red-700', false)->assertSee('bg-emerald-700', false);

        $this->actingAs($this->admin)->get(route('admin.reportes.index'))->assertOk()
            ->assertSee('href="'.route('admin.reportes.pdf').'"', false);
    }

    // ---------- Datos de prueba ----------

    /** Adopciones: 2 en octubre (1–14), 1 en septiembre (1–14); mascotas disponibles y solicitudes abiertas. */
    private function escenario(): void
    {
        $this->solicitud('aceptada', '2026-09-20 10:00:00-04', '2026-10-02 10:00:00-04');
        $this->solicitud('aceptada', '2026-10-03 10:00:00-04', '2026-10-05 10:00:00-04');
        $this->solicitud('aceptada', '2026-09-01 10:00:00-04', '2026-09-03 10:00:00-04');
        $this->solicitud('rechazada', '2026-10-04 10:00:00-04', '2026-10-06 10:00:00-04');
        $this->solicitud('pendiente', '2026-10-10 10:00:00-04');
        $this->solicitud('aprobada', '2026-10-11 10:00:00-04');
        $this->solicitud('no_concretada', '2026-10-12 10:00:00-04', '2026-10-13 10:00:00-04');
        $this->mascota(['nombre' => 'Bruno', 'tamano' => 'grande'], '2026-05-25 10:00:00-04');
        $this->mascota(['nombre' => 'Nina', 'especie' => 'gato'], '2026-06-18 10:00:00-04');
    }

    private function solicitud(string $estado, string $creada, ?string $resuelta = null): void
    {
        $solicitud = SolicitudAdopcion::create([
            ...CuestionarioAdopcionDatos::registro(),
            'solicitante_id' => User::factory()->create(['rol' => 'ciudadano'])->id,
            'mascota_id' => $this->mascota()->id,
            'estado' => 'pendiente',
        ]);

        if ($estado !== 'pendiente') {
            $solicitud->update(['estado' => 'en_espera']);
            if ($estado === 'rechazada') {
                $solicitud->update(['estado' => 'rechazada']);
            } else {
                $solicitud->update(['estado' => 'aprobada']);
                if ($estado === 'aceptada') {
                    DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update([
                        'estado' => 'aceptada',
                        'compromiso_firmado' => true,
                        'entregada_at' => DB::selectOne('select CURRENT_TIMESTAMP as ahora')->ahora,
                    ]);
                } elseif ($estado === 'no_concretada') {
                    DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update([
                        'estado' => 'no_concretada',
                        'motivo_no_concretada' => 'La entrega no pudo coordinarse.',
                    ]);
                }
            }
        }

        // created_at y resuelta_at son fechas de fixture para consultar períodos históricos; el estado siempre
        // se obtiene recorriendo las transiciones reales. Una solicitud aprobada conserva resuelta_at nula.
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(array_filter(['created_at' => $creada, 'resuelta_at' => $resuelta]));
    }

    private function mascota(array $atributos = [], ?string $creada = null): Mascota
    {
        $mascota = DB::transaction(function () use ($atributos): Mascota {
            $mascota = Mascota::create([...['nombre' => fake()->unique()->firstName(), 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id], ...$atributos]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'mascotas/e.jpg', 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
        if ($creada) {
            DB::table('mascotas')->where('id', $mascota->id)->update(['created_at' => $creada]);
        }

        return $mascota;
    }

    /** Datos que recibe la vista de Reportes para los mismos parámetros. */
    private function datosDePantalla(array $parametros): array
    {
        return $this->actingAs($this->admin)->get(route('admin.reportes.index', $parametros))->assertOk()->original->getData();
    }

    /** Secciones con las que se renderizó la última plantilla del PDF (View::composer). */
    private array $capturado = [];

    private function assertPdfValido(string $contenido): void
    {
        $this->assertStringStartsWith('%PDF-', $contenido);
        $this->assertStringContainsString('%%EOF', substr($contenido, -1024));
        $this->assertGreaterThanOrEqual(1, preg_match_all('/\/Type\s*\/Page[^s]/', $contenido), 'al menos una página');
        $this->assertGreaterThan(2000, strlen($contenido));
    }

    private function leerExcel(string $contenido): Spreadsheet
    {
        $this->assertStringStartsWith("PK\x03\x04", $contenido, 'un .xlsx es un ZIP');
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($ruta, $contenido);
        try {
            $this->assertSame('Xlsx', IOFactory::identify($ruta));

            return IOFactory::createReader('Xlsx')->load($ruta);
        } finally {
            @unlink($ruta);
        }
    }
}
