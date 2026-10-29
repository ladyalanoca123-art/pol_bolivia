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

    public function test_el_contenido_del_pdf_muestra_el_periodo_los_indicadores_y_los_estados_vacios(): void
    {
        $this->escenario();
        $s = app(ExportadorReporte::class)->secciones($this->datosDePantalla(['periodo' => 'este_mes']));
        $html = view('admin.reportes.pdf', ['s' => $s])->render();

        $this->assertStringContainsString('Período:</strong> Este mes (1 al 14 de octubre de 2026)', $html);
        $this->assertStringContainsString('Comparado con el período anterior: 1 al 14 de septiembre de 2026', $html);
        $this->assertMatchesRegularExpression('/<td>Adopciones completadas<\/td><td class="num">2<\/td><td class="num">1<\/td><td>Aumento<\/td><td class="num">100,0 %<\/td>/', $html);
        $this->assertStringContainsString('<td>Visitas de seguimiento cumplidas</td><td class="num">Sin casos</td>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('http', preg_replace('/<!DOCTYPE[^>]*>/', '', $html), 'sin recursos externos');
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
        $indicadores = $libro->getSheetByName('Indicadores');
        $this->assertSame('Reporte de POL-Bolivia · Indicadores', $indicadores->getCell('A1')->getValue());
        $this->assertSame('Período: Este mes (1 al 14 de octubre de 2026)', $indicadores->getCell('A3')->getValue());
        $this->assertSame(['Indicador', 'Período', 'Período anterior', 'Tendencia', 'Variación'], $indicadores->rangeToArray('A6:E6')[0]);
        $this->assertSame(['Mes', 'Solicitudes recibidas', 'Adopciones'], $libro->getSheetByName('Evolución mensual')->rangeToArray('A6:C6')[0]);
        $this->assertSame(['Grupo', 'Concepto', 'Cantidad'], $libro->getSheetByName('Estado actual')->rangeToArray('A6:C6')[0]);
        $this->assertSame('0.0%', $indicadores->getStyle('E7')->getNumberFormat()->getFormatCode());
    }

    public function test_el_excel_coincide_con_los_datos_de_la_pantalla(): void
    {
        $this->escenario();
        $pantalla = $this->datosDePantalla(['periodo' => 'este_mes']);

        $libro = $this->leerExcel($this->actingAs($this->admin)->get(route('admin.reportes.excel', ['periodo' => 'este_mes']))->streamedContent());

        $filas = $libro->getSheetByName('Indicadores')->rangeToArray('A7:C21', null, true, false);
        foreach (array_values(ExportadorReporte::INDICADORES) as $n => $etiqueta) {
            $clave = array_keys(ExportadorReporte::INDICADORES)[$n];
            $this->assertEquals([$etiqueta, $pantalla['indicadoresPeriodo'][$clave]['actual'], $pantalla['indicadoresPeriodo'][$clave]['anterior']], $filas[$n], $etiqueta);
        }
        $gestion = $libro->getSheetByName('Gestión');
        $this->assertSame('Adopciones concretadas / (concretadas + rechazadas)', $gestion->getCell('A7')->getValue());
        $this->assertEqualsWithDelta($pantalla['gestion']['tasa_aprobacion']['actual'] / 100, $gestion->getCell('B7')->getValue(), 0.0001);
        $estado = collect($libro->getSheetByName('Estado actual')->rangeToArray('A7:C34', null, true, false))->filter(fn ($f) => $f[0] !== null);
        $this->assertEquals($pantalla['totalMascotas'], $estado->firstWhere(1, 'Total mascotas')[2]);
        $this->assertEquals($pantalla['solicitudesPorEstado']['aceptada'], $estado->first(fn ($f) => $f[0] === 'Solicitudes de adopción' && $f[1] === 'Adopciones concretadas')[2]);
        $this->assertEquals($pantalla['solicitudesPorEstado']['aprobada'], $estado->first(fn ($f) => $f[0] === 'Solicitudes de adopción' && $f[1] === 'Reservadas (entrega pendiente)')[2]);
        $this->assertEquals($pantalla['solicitudesPorEstado']['no_concretada'], $estado->first(fn ($f) => $f[0] === 'Solicitudes de adopción' && $f[1] === 'Entregas no concretadas')[2]);
        $this->assertSame(1, $pantalla['solicitudesPorEstado']['aprobada']);
        $this->assertSame(1, $pantalla['solicitudesPorEstado']['no_concretada']);
        $this->assertSame(1, $pantalla['mascotasPorEstado']['reservada']);
        $this->assertEquals($pantalla['mascotasPorEstado']['reservada'], $estado->first(fn ($f) => $f[0] === 'Mascotas' && $f[1] === 'Mascotas reservadas')[2]);
        $this->assertEquals($pantalla['disponibles']['total'], array_sum(array_column($libro->getSheetByName('Mascotas disponibles')->rangeToArray('B7:B8', null, true, false), 0)));
    }

    public function test_registros_aceptados_sin_fecha_se_informan_en_pantalla_pdf_y_excel_sin_fecha_inventada(): void
    {
        $this->escenario();
        $this->solicitud('aceptada', '2025-03-02 12:00:00-04');
        $sinFecha = SolicitudAdopcion::query()->where('estado', 'aceptada')->latest('id')->firstOrFail();
        DB::table('solicitudes_adopcion')->where('id', $sinFecha->id)->update(['resuelta_at' => null]);

        $pantalla = $this->datosDePantalla([]);
        $this->assertSame(4, $pantalla['totalAdopciones']);
        $this->assertSame(1, $pantalla['adopcionesSinFechaResolucion']);
        $this->actingAs($this->admin)->get(route('admin.reportes.index'))
            ->assertOk()->assertSee('1 adopción aceptada sin fecha de resolución verificable');

        $secciones = app(ExportadorReporte::class)->secciones($pantalla);
        $pdfHtml = view('admin.reportes.pdf', ['s' => $secciones])->render();
        $this->assertStringContainsString('1 adopción aceptada carece de fecha de resolución verificable', $pdfHtml);

        $libro = $this->leerExcel($this->actingAs($this->admin)->get(route('admin.reportes.excel'))->streamedContent());
        $this->assertStringContainsString('1 adopción aceptada carece de fecha de resolución verificable', $libro->getSheetByName('Evolución mensual')->getCell('A15')->getValue());
    }

    public function test_los_dos_formatos_respetan_el_periodo_solicitado(): void
    {
        $this->escenario();
        $resumen = fn (array $p) => $this->leerExcel($this->actingAs($this->admin)->get(route('admin.reportes.excel', $p))->streamedContent())->getSheetByName('Resumen')->rangeToArray('A7:C7', null, true, false)[0];

        $this->assertEquals(['Adopciones realizadas', 2, 1], $resumen(['periodo' => 'este_mes']));
        $this->assertEquals(['Adopciones realizadas', 1, 0], $resumen(['periodo' => 'mes_anterior']));
        $this->assertEquals(['Adopciones realizadas', 3, 0], $resumen(['periodo' => 'personalizado', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-14']));

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
            $this->assertStringContainsString('No hay mascotas disponibles en este momento.', implode(' ', array_map(fn ($f) => (string) $f[0], $libro->getSheetByName('Mascotas disponibles')->toArray())));
        }

        $sinPeriodo = $this->leerExcel($this->actingAs($this->admin)->get(route('admin.reportes.excel'))->streamedContent());
        $this->assertSame('Elige un período en la pantalla de reportes para obtener estos indicadores.', $sinPeriodo->getSheetByName('Indicadores')->getCell('A6')->getValue());
        $this->assertStringContainsString('reporte-pol-bolivia_acumulado_2026-10-14.pdf', $this->actingAs($this->admin)->get(route('admin.reportes.pdf'))->headers->get('Content-Disposition'));

        $conPeriodo = $this->leerExcel($this->actingAs($this->admin)->get(route('admin.reportes.excel', ['periodo' => 'este_mes']))->streamedContent());
        $this->assertSame('Sin casos', $conPeriodo->getSheetByName('Gestión')->getCell('B7')->getValue());
        $this->assertContains('No se recibieron solicitudes en este período.', array_merge(...$conPeriodo->getSheetByName('Gestión')->toArray()));
    }

    public function test_los_textos_se_exportan_como_texto_y_no_como_formulas(): void
    {
        $this->mascota(['nombre' => '=HYPERLINK("http://ejemplo.test","clic")'], '2026-05-01 10:00:00-04');

        $hoja = $this->leerExcel($this->actingAs($this->admin)->get(route('admin.reportes.excel'))->streamedContent())->getSheetByName('Mascotas disponibles');
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
