<?php

namespace App\Services;

use App\Models\Mascota;
use App\Models\SolicitudAdopcion;
use App\Support\PeriodoReporte;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportaciones del reporte administrativo.
 *
 *  - PDF: informe corto para decidir. Responde «¿cómo está la fundación y qué requiere atención?».
 *  - Excel: el mismo resumen y hojas de consulta con los registros, para buscar y filtrar.
 *
 * Ambos separan la «Información del período seleccionado» del «Estado actual al generar el reporte».
 * Recibe los datos de ReporteController::datos() (calculados por ReporteAdministrador); no consulta la base.
 */
class ExportadorReporte
{
    public const HOJAS = ['Resumen', 'Mascotas', 'Solicitudes', 'Seguimientos', 'Incidencias', 'Voluntariado'];

    public const ETIQUETA_PERIODO = 'Información del período seleccionado';

    public const ETIQUETA_ACTUAL = 'Estado actual al generar el reporte';

    /** Indicadores de gestión que se muestran: clave de ReporteAdministrador::gestion() => [etiqueta, unidad]. */
    public const GESTION = [
        'tiempo_respuesta' => ['Tiempo promedio de respuesta a una solicitud', 'dias'],
        'dias_hasta_adopcion' => ['Días promedio hasta la adopción', 'dias'],
        'visitas_cumplidas' => ['Visitas de seguimiento cumplidas', 'porcentaje'],
    ];

    /** Bloques de «Requiere atención»: clave de ReporteAdministrador::atencion() => [título, ayuda, columnas]. */
    public const ATENCION = [
        'solicitudes_por_evaluar' => ['Solicitudes por evaluar', 'Recibidas o en evaluación, de la más antigua a la más reciente.', ['Solicitante', 'Mascota', 'Etapa', 'Días esperando']],
        'reservadas_sin_acta' => ['Reservadas sin foto del acta firmada', 'Falta subir el acta firmada antes de registrar la entrega.', ['Solicitante', 'Mascota', 'N.º de acta', 'Días reservada']],
        'seguimientos_sin_voluntario' => ['Seguimientos sin voluntario', 'Adopciones concretadas que aún no tienen voluntario asignado.', ['Mascota', 'Adoptante', 'Días sin voluntario']],
        'visitas_vencidas' => ['Visitas vencidas', 'Visitas programadas cuya fecha ya pasó y no se registraron.', ['Mascota', 'Voluntario', 'Fecha programada', 'Días de atraso']],
        'incidencias_pendientes' => ['Incidencias pendientes', 'Reportadas en los seguimientos y aún no atendidas.', ['Mascota', 'Tipo', 'Estado', 'Días abierta']],
        'esperan_hogar' => ['Mascotas que esperan hogar hace más tiempo', 'Mascotas disponibles, desde su registro.', ['Mascota', 'Especie', 'Días esperando']],
    ];

    private const TIPOS_INCIDENCIA = ['salud' => 'Salud', 'maltrato' => 'Maltrato', 'perdida_contacto' => 'Pérdida de contacto', 'otro' => 'Otro'];

    private const ESTADOS_INCIDENCIA = ['pendiente' => 'Pendiente', 'en_proceso' => 'En proceso', 'atendida' => 'Atendida'];

    private const ESTADOS_SEGUIMIENTO = ['sin_asignar' => 'Sin voluntario', 'activo' => 'Activo', 'cerrado' => 'Cerrado'];

    private const ESPECIES = ['perro' => 'Perro', 'gato' => 'Gato'];

    private const SEXOS = ['macho' => 'Macho', 'hembra' => 'Hembra'];

    private const TAMANOS = ['pequeno' => 'Pequeño', 'mediano' => 'Mediano', 'grande' => 'Grande'];

    /** Secciones del reporte, comunes al PDF y al Excel. */
    public function secciones(array $datos): array
    {
        /** @var PeriodoReporte|null $periodo */
        $periodo = $datos['periodoReporte'];
        $i = $datos['indicadoresPeriodo'];
        $s = $datos['solicitudesPorEstado'];
        $inc = $datos['incidenciasPorEstado'];

        return [
            'titulo' => 'Reporte administrativo POL-Bolivia',
            'generado' => $datos['generadoEn']->copy()->setTimezone(PeriodoReporte::ZONA)->format('d/m/Y H:i').' (hora de Bolivia)',
            'periodo' => $periodo ? $periodo->etiqueta() : 'Sin período: totales acumulados',
            'comparacion' => $periodo ? 'Comparado con el período anterior: '.$datos['periodoAnterior']->rango() : null,
            'conPeriodo' => $periodo !== null,
            'periodoKpis' => $periodo
                ? [
                    ['Solicitudes recibidas', $i['solicitudes_recibidas']['actual'], $i['solicitudes_recibidas']['anterior']],
                    ['Adopciones concretadas', $i['adopciones_completadas']['actual'], $i['adopciones_completadas']['anterior']],
                ]
                : [
                    ['Solicitudes recibidas (total histórico)', $datos['totalSolicitudes'], null],
                    ['Adopciones concretadas (total histórico)', $datos['totalAdopciones'], null],
                ],
            'actualKpis' => [
                ['Solicitudes por evaluar', $s['pendiente'] + $s['en_espera']],
                ['Mascotas disponibles', $datos['mascotasPorEstado']['disponible']],
                ['Seguimientos activos', $datos['seguimientosPorEstado']['activo']],
                ['Incidencias pendientes', $inc['pendiente'] + $inc['en_proceso']],
                ['Voluntarios activos', $datos['voluntariosPorEstado']['activo']],
            ],
            'gestion' => $datos['gestion'] === null ? null : collect(self::GESTION)->map(fn (array $g, string $clave) => [
                'etiqueta' => $g[0],
                'unidad' => $g[1],
                'actual' => $datos['gestion'][$clave]['actual'],
                'anterior' => $datos['gestion'][$clave]['anterior'],
            ])->values()->all(),
            'situacion' => [
                'Mascotas' => ['Disponibles' => $datos['mascotasPorEstado']['disponible'], 'Reservadas' => $datos['mascotasPorEstado']['reservada'], 'Adoptadas' => $datos['mascotasPorEstado']['adoptada']],
                'Solicitudes' => ['Recibidas' => $s['pendiente'], 'En evaluación' => $s['en_espera'], 'Reservadas' => $s['aprobada'], 'Concretadas' => $s['aceptada'], 'Rechazadas' => $s['rechazada'], 'Canceladas' => $s['cancelada'], 'Entregas no concretadas' => $s['no_concretada'], 'Cerradas al adoptarse la mascota' => $s['cerrada']],
                'Seguimientos' => ['Sin voluntario' => $datos['seguimientosPorEstado']['sin_asignar'], 'Activos' => $datos['seguimientosPorEstado']['activo'], 'Cerrados' => $datos['seguimientosPorEstado']['cerrado']],
                'Incidencias' => ['Pendientes' => $inc['pendiente'], 'En proceso' => $inc['en_proceso'], 'Atendidas' => $inc['atendida']],
                'Voluntariado' => ['Voluntarios activos' => $datos['voluntariosPorEstado']['activo'], 'Voluntarios inactivos' => $datos['voluntariosPorEstado']['inactivo'], 'Postulaciones pendientes' => $datos['postulacionesPorEstado']['pendiente']],
            ],
            'atencion' => collect(self::ATENCION)->map(fn (array $a, string $clave) => [
                'clave' => $clave,
                'titulo' => $a[0],
                'ayuda' => $a[1],
                'columnas' => $a[2],
                'total' => $datos['atencion'][$clave]['total'],
                'filas' => array_map(fn (array $f) => $this->filaAtencion($clave, $f), $datos['atencion'][$clave]['filas']),
            ])->values()->all(),
        ];
    }

    /** Nombre de archivo seguro: solo letras, números, guiones y punto. */
    public function nombreArchivo(array $datos, string $extension): string
    {
        $periodo = $datos['periodoReporte'];
        $tramo = $periodo
            ? $periodo->inicio->format('Y-m-d').($periodo->dias() > 1 ? '_a_'.$periodo->ultimoDia()->format('Y-m-d') : '')
            : 'acumulado_'.$datos['generadoEn']->copy()->setTimezone(PeriodoReporte::ZONA)->format('Y-m-d');

        return 'reporte-pol-bolivia_'.$tramo.'.'.preg_replace('/[^a-z]/', '', $extension);
    }

    public function pdf(array $datos): Response
    {
        $logo = 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/pol-bolivia/logos/POL_02_horizontal_web.png')));

        return Pdf::loadView('admin.reportes.pdf', ['s' => $this->secciones($datos), 'logo' => $logo])
            ->setPaper('a4', 'portrait')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false])
            ->download($this->nombreArchivo($datos, 'pdf'));
    }

    public function excel(array $datos): StreamedResponse
    {
        $libro = $this->libro($datos);

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
            $libro->disconnectWorksheets();
        }, $this->nombreArchivo($datos, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Libro de Excel: Resumen y una hoja de consulta por tema (HOJAS). */
    public function libro(array $datos): Spreadsheet
    {
        $s = $this->secciones($datos);
        $r = $datos['registros'] ?? ['mascotas' => [], 'solicitudes' => [], 'seguimientos' => [], 'incidencias' => [], 'voluntarios' => []];
        $libro = new Spreadsheet;
        $libro->getProperties()->setCreator('POL-Bolivia')->setTitle($s['titulo'])->setSubject($s['periodo']);
        $libro->removeSheetByIndex(0);

        $this->hojaResumen($libro, $s);

        $alcancePeriodo = self::ETIQUETA_PERIODO.': '.$s['periodo'].' (registros creados en el período).';
        $alcanceActual = self::ETIQUETA_ACTUAL.' (todos los registros).';

        $this->hojaRegistros($libro, 'Mascotas', $s, $alcanceActual,
            ['Nombre', 'Especie', 'Sexo', 'Tamaño', 'Edad', 'Estado', 'Fecha de registro', 'Días esperando hogar'],
            array_map(fn ($m) => [
                $m['nombre'], self::ESPECIES[$m['especie']] ?? $m['especie'], self::SEXOS[$m['sexo']] ?? $m['sexo'], self::TAMANOS[$m['tamano']] ?? $m['tamano'],
                $this->edad((int) $m['edad'], (string) $m['edad_en']), Mascota::ESTADOS_LEGIBLES[$m['estado']] ?? $m['estado'], $m['registrada'], $m['dias_esperando'] === null ? null : (int) $m['dias_esperando'],
            ], $r['mascotas']), [6], 'No hay mascotas registradas.');

        $this->hojaRegistros($libro, 'Solicitudes', $s, $alcancePeriodo,
            ['N.º', 'N.º de acta', 'Solicitante', 'Mascota', 'Estado', 'Revisión', 'Fecha de solicitud', 'Fecha de resolución', 'Fecha de entrega', 'Foto del acta'],
            array_map(fn ($f) => [
                (int) $f['id'], $f['numero_acta'] ?? '—', $f['solicitante'], $f['mascota'], SolicitudAdopcion::ESTADOS_LEGIBLES[$f['estado']] ?? $f['estado'],
                SolicitudAdopcion::NIVELES_ALERTA[$f['nivel_alerta']] ?? '—', $f['fecha_solicitud'], $f['fecha_resolucion'], $f['fecha_entrega'],
                in_array($f['estado'], ['aprobada', 'aceptada'], true) ? ($f['tiene_foto_acta'] ? 'Sí' : 'No') : '—',
            ], $r['solicitudes']), [6, 7, 8], 'No hay solicitudes en este período.');

        $this->hojaRegistros($libro, 'Seguimientos', $s, $alcancePeriodo,
            ['Mascota', 'Adoptante', 'Voluntario', 'Estado', 'Fecha de inicio', 'Visitas terminadas (de 2)', 'Próxima visita', 'Visita vencida', 'Incidencias pendientes'],
            array_map(fn ($f) => [
                $f['mascota'], $f['adoptante'], $f['voluntario'] ?? 'Sin asignar', self::ESTADOS_SEGUIMIENTO[$f['estado']] ?? $f['estado'], $f['fecha_inicio'],
                (int) $f['visitas_terminadas'], $f['proxima_visita'], $f['proxima_visita'] === null ? '—' : ($f['proxima_visita'] < $this->hoy() ? 'Sí' : 'No'), (int) $f['incidencias_pendientes'],
            ], $r['seguimientos']), [4, 6], 'No hay seguimientos en este período.');

        $this->hojaRegistros($libro, 'Incidencias', $s, $alcancePeriodo,
            ['Mascota', 'Tipo', 'Estado', 'Fecha de reporte', 'Reportada por', 'Atendida por', 'Fecha de atención'],
            array_map(fn ($f) => [
                $f['mascota'], self::TIPOS_INCIDENCIA[$f['tipo']] ?? $f['tipo'], self::ESTADOS_INCIDENCIA[$f['estado']] ?? $f['estado'], $f['fecha_reporte'],
                $f['reportado_por'], $f['atendido_por'] ?? '—', $f['fecha_atencion'],
            ], $r['incidencias']), [3, 6], 'No hay incidencias en este período.');

        $this->hojaRegistros($libro, 'Voluntariado', $s, $alcanceActual,
            ['Nombre', 'Estado', 'Fecha de aprobación', 'Postulación N.º', 'Seguimientos activos'],
            array_map(fn ($f) => [
                $f['nombre'], $f['estado'] === 'activo' ? 'Activo' : 'Inactivo', $f['fecha_aprobacion'], $f['postulacion_id'] === null ? '—' : (int) $f['postulacion_id'], (int) $f['seguimientos_activos'],
            ], $r['voluntarios']), [2], 'No hay voluntarios registrados.');

        foreach ($libro->getAllSheets() as $h) {
            $h->setSelectedCell('A1');
        }
        $libro->setActiveSheetIndex(0);

        return $libro;
    }

    private function hojaResumen(Spreadsheet $libro, array $s): void
    {
        $hoja = $libro->createSheet()->setTitle('Resumen');
        $this->encabezadoHoja($hoja, $s, 'Resumen', $s['periodo'].($s['comparacion'] ? ' · '.$s['comparacion'] : ''));

        $fila = 6;
        $this->titulo($hoja, "A{$fila}", self::ETIQUETA_PERIODO);
        $fila++;
        if ($s['conPeriodo']) {
            $this->encabezados($hoja, $fila, ['Indicador', 'Período', 'Período anterior']);
            foreach ($s['periodoKpis'] as [$etiqueta, $actual, $anterior]) {
                $fila++;
                $this->texto($hoja, "A{$fila}", $etiqueta);
                $hoja->setCellValue("B{$fila}", $actual);
                $hoja->setCellValue("C{$fila}", $anterior);
            }
            foreach ($s['gestion'] as $g) {
                $fila++;
                $this->texto($hoja, "A{$fila}", $g['etiqueta']);
                foreach (['B' => $g['actual'], 'C' => $g['anterior']] as $columna => $valor) {
                    if ($valor === null) {
                        $this->texto($hoja, "{$columna}{$fila}", 'Sin casos');
                    } else {
                        $hoja->setCellValue("{$columna}{$fila}", $g['unidad'] === 'porcentaje' ? $valor / 100 : $valor);
                        $hoja->getStyle("{$columna}{$fila}")->getNumberFormat()->setFormatCode($g['unidad'] === 'porcentaje' ? '0.0%' : '0.0" días"');
                    }
                }
            }
        } else {
            $this->encabezados($hoja, $fila, ['Indicador', 'Cantidad']);
            foreach ($s['periodoKpis'] as [$etiqueta, $valor]) {
                $fila++;
                $this->texto($hoja, "A{$fila}", $etiqueta);
                $hoja->setCellValue("B{$fila}", $valor);
            }
            $fila++;
            $this->texto($hoja, "A{$fila}", 'Elige un período en la pantalla de reportes para comparar con el período anterior.');
        }

        $fila += 2;
        $this->titulo($hoja, "A{$fila}", self::ETIQUETA_ACTUAL);
        $fila = $this->tabla($hoja, $fila + 1, ['Indicador', 'Cantidad'], $s['actualKpis']);

        $fila += 2;
        $this->titulo($hoja, "A{$fila}", 'Requiere atención (estado actual)');
        $fila = $this->tabla($hoja, $fila + 1, ['Asunto', 'Cantidad'], array_map(fn ($a) => [$a['titulo'], $a['total']], $s['atencion']));

        $fila += 2;
        $this->titulo($hoja, "A{$fila}", 'Situación actual por estado');
        $filas = [];
        foreach ($s['situacion'] as $grupo => $conceptos) {
            foreach ($conceptos as $concepto => $cantidad) {
                $filas[] = [$grupo, $concepto, $cantidad];
            }
        }
        $this->tabla($hoja, $fila + 1, ['Grupo', 'Concepto', 'Cantidad'], $filas);

        foreach (['A' => 52, 'B' => 18, 'C' => 18] as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }
    }

    /**
     * Hoja de consulta: encabezado, tabla con filtros y encabezado fijo. $fechas: índices (base 0) de las
     * columnas con fecha AAAA-MM-DD, que se guardan como fecha de Excel (dd/mm/aaaa).
     */
    private function hojaRegistros(Spreadsheet $libro, string $nombre, array $s, string $alcance, array $encabezados, array $filas, array $fechas, string $vacio): void
    {
        $hoja = $libro->createSheet()->setTitle($nombre);
        $this->encabezadoHoja($hoja, $s, $nombre, $alcance);
        $this->encabezados($hoja, 5, $encabezados);
        $ultimaColumna = Coordinate::stringFromColumnIndex(count($encabezados));

        if ($filas === []) {
            $this->texto($hoja, 'A6', $vacio);
        } else {
            $fila = 5;
            foreach ($filas as $datos) {
                $fila++;
                foreach (array_values($datos) as $c => $valor) {
                    $celda = Coordinate::stringFromColumnIndex($c + 1).$fila;
                    if (in_array($c, $fechas, true) && is_string($valor) && $valor !== '') {
                        $hoja->setCellValue($celda, FechaExcel::PHPToExcel(substr($valor, 0, 10)));
                        $hoja->getStyle($celda)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                    } elseif (is_string($valor)) {
                        $this->texto($hoja, $celda, $valor);
                    } else {
                        $hoja->setCellValue($celda, $valor);
                    }
                }
            }
            $hoja->setAutoFilter("A5:{$ultimaColumna}{$fila}");
        }
        $hoja->freezePane('A6');

        foreach (range(1, count($encabezados)) as $c) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }
    }

    private function encabezadoHoja(Worksheet $hoja, array $s, string $nombre, string $alcance): void
    {
        $this->texto($hoja, 'A1', $s['titulo'].' · '.$nombre);
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('172554');
        $this->texto($hoja, 'A2', 'Generado: '.$s['generado']);
        $this->texto($hoja, 'A3', $alcance);
        $hoja->getStyle('A2:A3')->getFont()->getColor()->setRGB('475569');
    }

    /** Fila de «Requiere atención» lista para mostrar (textos legibles, sin datos personales de contacto). */
    private function filaAtencion(string $clave, array $f): array
    {
        $dias = (int) $f['dias'];

        return match ($clave) {
            'solicitudes_por_evaluar' => [$f['solicitante'], $f['mascota'], SolicitudAdopcion::ESTADOS_LEGIBLES[$f['estado']] ?? $f['estado'], $dias],
            'reservadas_sin_acta' => [$f['solicitante'], $f['mascota'], $f['numero_acta'] ?? '—', $dias],
            'seguimientos_sin_voluntario' => [$f['mascota'], $f['adoptante'], $dias],
            'visitas_vencidas' => [$f['mascota'], $f['voluntario'] ?: 'Sin asignar', Carbon::parse($f['fecha_programada'])->format('d/m/Y'), $dias],
            'incidencias_pendientes' => [$f['mascota'], self::TIPOS_INCIDENCIA[$f['tipo']] ?? $f['tipo'], self::ESTADOS_INCIDENCIA[$f['estado']] ?? $f['estado'], $dias],
            'esperan_hogar' => [$f['mascota'], self::ESPECIES[$f['especie']] ?? $f['especie'], $dias],
        };
    }

    private function edad(int $edad, string $unidad): string
    {
        return $edad.' '.($unidad === 'meses' ? ($edad === 1 ? 'mes' : 'meses') : ($edad === 1 ? 'año' : 'años'));
    }

    private function hoy(): string
    {
        return Carbon::now(PeriodoReporte::ZONA)->toDateString();
    }

    /** Escribe una tabla (encabezados y filas) y devuelve la última fila escrita. */
    private function tabla(Worksheet $hoja, int $fila, array $encabezados, array $filas): int
    {
        $this->encabezados($hoja, $fila, $encabezados);
        foreach ($filas as $datos) {
            $fila++;
            foreach (array_values($datos) as $c => $valor) {
                $celda = Coordinate::stringFromColumnIndex($c + 1).$fila;
                is_string($valor) ? $this->texto($hoja, $celda, $valor) : $hoja->setCellValue($celda, $valor);
            }
        }

        return $fila;
    }

    private function encabezados(Worksheet $hoja, int $fila, array $encabezados): void
    {
        foreach ($encabezados as $c => $titulo) {
            $this->texto($hoja, Coordinate::stringFromColumnIndex($c + 1).$fila, $titulo);
        }
        $rango = 'A'.$fila.':'.Coordinate::stringFromColumnIndex(count($encabezados)).$fila;
        $hoja->getStyle($rango)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('172554');
    }

    private function titulo(Worksheet $hoja, string $celda, string $valor): void
    {
        $this->texto($hoja, $celda, $valor);
        $hoja->getStyle($celda)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('B45309');
    }

    /** Texto siempre como texto: un valor que empiece por «=» nunca se interpreta como fórmula. */
    private function texto(Worksheet $hoja, string $celda, string $valor): void
    {
        $hoja->setCellValueExplicit($celda, $valor, DataType::TYPE_STRING);
    }
}
