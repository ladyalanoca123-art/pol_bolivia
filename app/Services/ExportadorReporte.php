<?php

namespace App\Services;

use App\Support\PeriodoReporte;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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
 * Exportaciones PDF y Excel del reporte administrativo.
 *
 * Recibe los mismos datos que la pantalla de Reportes (ReporteController::datos(), calculados por
 * ReporteAdministrador) y los organiza una sola vez en secciones; el PDF y el Excel presentan esas
 * secciones, por lo que ambos coinciden entre sí y con la pantalla. No consulta la base de datos.
 */
class ExportadorReporte
{
    public const INDICADORES = [
        'solicitudes_recibidas' => 'Solicitudes recibidas',
        'adopciones_completadas' => 'Adopciones completadas',
        'solicitudes_rechazadas' => 'Solicitudes rechazadas',
        'solicitudes_canceladas' => 'Solicitudes canceladas',
        'solicitudes_no_concretadas' => 'Entregas no concretadas',
        'solicitudes_cerradas' => 'Solicitudes cerradas al adoptarse la mascota',
        'mascotas_registradas' => 'Mascotas registradas',
        'postulaciones_recibidas' => 'Postulaciones recibidas',
        'postulaciones_aprobadas' => 'Postulaciones aprobadas',
        'postulaciones_rechazadas' => 'Postulaciones rechazadas',
        'seguimientos_iniciados' => 'Seguimientos iniciados',
        'seguimientos_cerrados' => 'Seguimientos cerrados',
        'visitas_realizadas' => 'Visitas realizadas',
        'incidencias_reportadas' => 'Incidencias reportadas',
        'incidencias_atendidas' => 'Incidencias atendidas',
    ];

    /** indicador de gestión => [etiqueta, unidad] */
    public const GESTION = [
        'tasa_aprobacion' => ['Adopciones concretadas / (concretadas + rechazadas)', 'porcentaje'],
        'tiempo_respuesta' => ['Tiempo promedio hasta decisión administrativa', 'dias'],
        'dias_hasta_adopcion' => ['Días promedio hasta la adopción', 'dias'],
        'atencion_incidencias' => ['Atención de incidencias', 'dias'],
        'revision_postulaciones' => ['Revisión de postulaciones', 'dias'],
        'visitas_cumplidas' => ['Visitas de seguimiento cumplidas', 'porcentaje'],
    ];

    public const TENDENCIAS = ['aumento' => 'Aumento', 'disminucion' => 'Disminución', 'igual' => 'Sin cambios', 'nuevo' => 'Nuevo'];

    public const HOJAS = ['Resumen', 'Indicadores', 'Gestión', 'Evolución mensual', 'Mascotas disponibles', 'Estado actual'];

    private const SIN_PERIODO = 'Elige un período en la pantalla de reportes para obtener estos indicadores.';

    /** Secciones del reporte, comunes al PDF y al Excel. */
    public function secciones(array $datos): array
    {
        /** @var PeriodoReporte|null $periodo */
        $periodo = $datos['periodoReporte'];
        $i = $datos['indicadoresPeriodo'];
        $tamanos = ['pequeno' => 'Pequeño', 'mediano' => 'Mediano', 'grande' => 'Grande'];

        return [
            'titulo' => 'Reporte de POL-Bolivia',
            'generado' => $datos['generadoEn']->copy()->setTimezone(PeriodoReporte::ZONA)->format('d/m/Y H:i').' (hora de Bolivia)',
            'periodo' => $periodo ? $periodo->etiqueta() : 'Sin período: totales acumulados y estado actual',
            'comparacion' => $periodo ? 'Comparado con el período anterior: '.$datos['periodoAnterior']->rango() : null,
            'sinPeriodo' => self::SIN_PERIODO,
            'adopcionesSinFechaResolucion' => $datos['adopcionesSinFechaResolucion'],
            'notaTiempoRespuesta' => 'Tiempo entre creación y decisión administrativa. Se excluyen aceptadas históricas sin aprobada_at porque resuelta_at puede corresponder a la entrega.',
            'resumen' => $periodo
                ? [
                    ['Adopciones realizadas', $i['adopciones_completadas']['actual'], $i['adopciones_completadas']['anterior']],
                    ['Solicitudes recibidas', $i['solicitudes_recibidas']['actual'], $i['solicitudes_recibidas']['anterior']],
                ]
                : [
                    ['Adopciones realizadas (total acumulado)', $datos['totalAdopciones'], null],
                    ['Solicitudes de adopción (total acumulado)', $datos['totalSolicitudes'], null],
                    ['Mascotas disponibles (estado actual)', $datos['mascotasPorEstado']['disponible'], null],
                    ['Seguimientos activos (estado actual)', $datos['seguimientosPorEstado']['activo'], null],
                ],
            'indicadores' => $i === null ? null : collect(self::INDICADORES)->map(fn (string $etiqueta, string $clave) => [
                'etiqueta' => $etiqueta,
                'actual' => $i[$clave]['actual'],
                'anterior' => $i[$clave]['anterior'],
                'tendencia' => self::TENDENCIAS[$i[$clave]['variacion']['tipo']],
                'porcentaje' => $i[$clave]['variacion']['porcentaje'],
            ])->values()->all(),
            'gestion' => $datos['gestion'] === null ? null : collect(self::GESTION)->map(fn (array $g, string $clave) => [
                'etiqueta' => $g[0],
                'unidad' => $g[1],
                'actual' => $datos['gestion'][$clave]['actual'],
                'anterior' => $datos['gestion'][$clave]['anterior'],
            ])->values()->all(),
            'seguimientosSinVoluntario' => $datos['seguimientosPorEstado']['sin_asignar'],
            'embudo' => $datos['embudo'] === null ? null : [
                'recibidas' => $datos['embudo']['recibidas'],
                'porcentajeAdopcion' => $datos['embudo']['porcentaje_adopcion'],
                'etapas' => collect(['recibidas' => 'Solicitudes recibidas', 'evaluadas' => 'Evaluadas por el equipo', 'aceptadas' => 'Adopciones concretadas', 'seguimiento_cerrado' => 'Seguimiento cerrado'])
                    ->map(fn (string $etiqueta, string $clave) => [$etiqueta, $datos['embudo'][$clave], $datos['embudo']['recibidas'] > 0 ? round($datos['embudo'][$clave] / $datos['embudo']['recibidas'] * 100, 1) : null])
                    ->values()->all(),
            ],
            'evolucion' => array_map(fn (array $m) => [
                Str::ucfirst(Carbon::parse($m['mes'].'-01')->translatedFormat('F Y')).($m['en_curso'] ? ' (en curso)' : ''),
                $m['solicitudes'],
                $m['adopciones'],
            ], $datos['evolucionMensual']),
            'disponibles' => [
                'total' => $datos['disponibles']['total'],
                'especie' => ['Perros' => $datos['disponibles']['especie']['perro'], 'Gatos' => $datos['disponibles']['especie']['gato']],
                'tamano' => collect($tamanos)->mapWithKeys(fn ($etiqueta, $clave) => [$etiqueta => $datos['disponibles']['tamano'][$clave] ?? 0])->all(),
            ],
            'espera' => $datos['esperanMasTiempo']->map(fn (array $e) => [
                'nombre' => $e['mascota']->nombre,
                'especie' => $e['mascota']->especie === 'gato' ? 'Gato' : 'Perro',
                'tamano' => $tamanos[$e['mascota']->tamano] ?? $e['mascota']->tamano,
                'edad' => $e['mascota']->edad.' '.($e['mascota']->edad_en === 'meses' ? ($e['mascota']->edad === 1 ? 'mes' : 'meses') : ($e['mascota']->edad === 1 ? 'año' : 'años')),
                'registrada' => $e['mascota']->created_at->copy()->setTimezone(PeriodoReporte::ZONA),
                'dias' => $e['dias'],
            ])->all(),
            'estado' => [
                'Mascotas' => ['Total mascotas' => $datos['totalMascotas'], 'Mascotas disponibles' => $datos['mascotasPorEstado']['disponible'], 'Mascotas reservadas' => $datos['mascotasPorEstado']['reservada'], 'Mascotas adoptadas' => $datos['mascotasPorEstado']['adoptada'], 'Perros' => $datos['mascotasPorEspecie']['perro'], 'Gatos' => $datos['mascotasPorEspecie']['gato']],
                'Solicitudes de adopción' => ['Pendientes' => $datos['solicitudesPorEstado']['pendiente'], 'En evaluación' => $datos['solicitudesPorEstado']['en_espera'], 'Reservadas (entrega pendiente)' => $datos['solicitudesPorEstado']['aprobada'], 'Adopciones concretadas' => $datos['solicitudesPorEstado']['aceptada'], 'Rechazadas' => $datos['solicitudesPorEstado']['rechazada'], 'Canceladas' => $datos['solicitudesPorEstado']['cancelada'], 'Cerradas al adoptarse la mascota' => $datos['solicitudesPorEstado']['cerrada'], 'Entregas no concretadas' => $datos['solicitudesPorEstado']['no_concretada']],
                'Seguimientos' => ['Sin asignar' => $datos['seguimientosPorEstado']['sin_asignar'], 'Activos' => $datos['seguimientosPorEstado']['activo'], 'Cerrados' => $datos['seguimientosPorEstado']['cerrado']],
                'Incidencias' => ['Pendientes' => $datos['incidenciasPorEstado']['pendiente'], 'En proceso' => $datos['incidenciasPorEstado']['en_proceso'], 'Atendidas' => $datos['incidenciasPorEstado']['atendida']],
                'Voluntariado' => ['Voluntarios activos' => $datos['voluntariosPorEstado']['activo'], 'Voluntarios inactivos' => $datos['voluntariosPorEstado']['inactivo'], 'Postulaciones pendientes' => $datos['postulacionesPorEstado']['pendiente'], 'Postulaciones aprobadas' => $datos['postulacionesPorEstado']['aprobada'], 'Postulaciones rechazadas' => $datos['postulacionesPorEstado']['rechazada'], 'Postulaciones canceladas' => $datos['postulacionesPorEstado']['cancelada']],
            ],
        ];
    }

    /** Nombre de archivo seguro: solo letras, números, guiones y punto. */
    public function nombreArchivo(array $datos, string $extension): string
    {
        $periodo = $datos['periodoReporte'];
        $tramo = $periodo
            ? $periodo->inicio->format('Y-m-d').'_a_'.$periodo->ultimoDia()->format('Y-m-d')
            : 'acumulado_'.$datos['generadoEn']->copy()->setTimezone(PeriodoReporte::ZONA)->format('Y-m-d');

        return 'reporte-pol-bolivia_'.$tramo.'.'.preg_replace('/[^a-z]/', '', $extension);
    }

    public function pdf(array $datos): Response
    {
        return Pdf::loadView('admin.reportes.pdf', ['s' => $this->secciones($datos)])
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

    /** Libro de Excel con una hoja por sección (HOJAS). */
    public function libro(array $datos): Spreadsheet
    {
        $s = $this->secciones($datos);
        $libro = new Spreadsheet;
        $libro->getProperties()->setCreator('POL-Bolivia')->setTitle($s['titulo'])->setSubject($s['periodo']);
        $libro->removeSheetByIndex(0);

        // Resumen
        $hoja = $this->hoja($libro, 'Resumen', $s);
        $fila = $this->tabla($hoja, 6, ['Indicador', 'Período', 'Período anterior', 'Unidad'], array_map(fn ($r) => [$r[0], $r[1], $r[2] ?? ($s['comparacion'] ? 0 : '—'), 'Cantidad'], $s['resumen']));
        if ($s['gestion'] !== null) {
            $fila++;
            foreach ([0, 1] as $g) {
                $fila = $this->filaGestion($hoja, $fila, $s['gestion'][$g], true);
            }
        }

        // Indicadores del período
        $hoja = $this->hoja($libro, 'Indicadores', $s);
        if ($s['indicadores'] === null) {
            $this->texto($hoja, 'A6', $s['sinPeriodo']);
        } else {
            $fin = $this->tabla($hoja, 6, ['Indicador', 'Período', 'Período anterior', 'Tendencia', 'Variación'], array_map(fn ($r) => [$r['etiqueta'], $r['actual'], $r['anterior'], $r['tendencia'], $r['porcentaje'] === null ? null : $r['porcentaje'] / 100], $s['indicadores']));
            $hoja->getStyle("E7:E{$fin}")->getNumberFormat()->setFormatCode('0.0%');
            $this->texto($hoja, 'A'.($fin + 2), 'Variación: solo se calcula si el período anterior no es cero.');
        }

        // Gestión y embudo
        $hoja = $this->hoja($libro, 'Gestión', $s);
        if ($s['gestion'] === null) {
            $this->texto($hoja, 'A6', $s['sinPeriodo']);
        } else {
            $this->encabezados($hoja, 6, ['Indicador', 'Período', 'Período anterior', 'Unidad']);
            $fila = 7;
            foreach ($s['gestion'] as $g) {
                $fila = $this->filaGestion($hoja, $fila, $g, true);
            }
            $this->texto($hoja, "A{$fila}", $s['notaTiempoRespuesta']);
            $fila += 2;
            $hoja->setCellValueExplicit("A{$fila}", 'Seguimientos sin voluntario (estado actual)', DataType::TYPE_STRING);
            $hoja->setCellValue("B{$fila}", $s['seguimientosSinVoluntario']);
            $fila += 2;
            $this->texto($hoja, "A{$fila}", 'Embudo de adopción: estado actual de las solicitudes recibidas en el período');
            $hoja->getStyle("A{$fila}")->getFont()->setBold(true);
            if ($s['embudo']['recibidas'] === 0) {
                $this->texto($hoja, 'A'.($fila + 1), 'No se recibieron solicitudes en este período.');
            } else {
                $fin = $this->tabla($hoja, $fila + 1, ['Etapa', 'Solicitudes', '% de las recibidas'], array_map(fn ($e) => [$e[0], $e[1], $e[2] / 100], $s['embudo']['etapas']));
                $hoja->getStyle('C'.($fila + 2).":C{$fin}")->getNumberFormat()->setFormatCode('0.0%');
            }
        }

        // Evolución mensual
        $hoja = $this->hoja($libro, 'Evolución mensual', $s);
        $this->tabla($hoja, 6, ['Mes', 'Solicitudes recibidas', 'Adopciones'], $s['evolucion']);
        $this->texto($hoja, 'A14', 'Últimos 6 meses. Solicitudes por fecha de envío; adopciones concretadas por fecha de resolución.');
        $cantidadSinFecha = $s['adopcionesSinFechaResolucion'];
        $descripcionSinFecha = $cantidadSinFecha === 1 ? '1 adopción aceptada carece' : $cantidadSinFecha.' adopciones aceptadas carecen';
        $this->texto($hoja, 'A15', 'En el estado actual, '.$descripcionSinFecha.' de fecha de resolución verificable y no aparece en la serie; no se asigna una fecha.');

        // Mascotas disponibles
        $hoja = $this->hoja($libro, 'Mascotas disponibles', $s);
        $this->texto($hoja, 'A5', 'Estado actual: '.$s['disponibles']['total'].' '.($s['disponibles']['total'] === 1 ? 'mascota disponible' : 'mascotas disponibles').'.');
        $fila = $this->tabla($hoja, 6, ['Especie', 'Mascotas'], array_map(null, array_keys($s['disponibles']['especie']), array_values($s['disponibles']['especie'])));
        $fila = $this->tabla($hoja, $fila + 2, ['Tamaño', 'Mascotas'], array_map(null, array_keys($s['disponibles']['tamano']), array_values($s['disponibles']['tamano'])));
        $fila += 2;
        $this->texto($hoja, "A{$fila}", 'Esperan hogar hace más tiempo');
        $hoja->getStyle("A{$fila}")->getFont()->setBold(true);
        if ($s['espera'] === []) {
            $this->texto($hoja, 'A'.($fila + 1), 'No hay mascotas disponibles en este momento.');
        } else {
            $fin = $this->tabla($hoja, $fila + 1, ['Nombre', 'Especie', 'Tamaño', 'Edad', 'Registrada el', 'Días de espera'], array_map(fn ($e) => [$e['nombre'], $e['especie'], $e['tamano'], $e['edad'], FechaExcel::PHPToExcel($e['registrada']->format('Y-m-d')), $e['dias']], $s['espera']));
            $hoja->getStyle('E'.($fila + 2).":E{$fin}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        }

        // Estado actual
        $hoja = $this->hoja($libro, 'Estado actual', $s);
        $filas = [];
        foreach ($s['estado'] as $grupo => $conceptos) {
            foreach ($conceptos as $concepto => $cantidad) {
                $filas[] = [$grupo, $concepto, $cantidad];
            }
        }
        $this->tabla($hoja, 6, ['Grupo', 'Concepto', 'Cantidad'], $filas);

        foreach ($libro->getAllSheets() as $h) {
            foreach (range('A', 'F') as $columna) {
                $h->getColumnDimension($columna)->setAutoSize(true);
            }
            $h->setSelectedCell('A1');
        }
        $libro->setActiveSheetIndex(0);

        return $libro;
    }

    private function hoja(Spreadsheet $libro, string $nombre, array $s): Worksheet
    {
        $hoja = $libro->createSheet()->setTitle($nombre);
        $this->texto($hoja, 'A1', $s['titulo'].' · '.$nombre);
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('172554');
        $this->texto($hoja, 'A2', 'Generado: '.$s['generado']);
        $this->texto($hoja, 'A3', 'Período: '.$s['periodo']);
        if ($s['comparacion']) {
            $this->texto($hoja, 'A4', $s['comparacion']);
        }
        $hoja->getStyle('A2:A4')->getFont()->getColor()->setRGB('475569');

        return $hoja;
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

    private function filaGestion(Worksheet $hoja, int $fila, array $g, bool $conUnidad = false): int
    {
        $this->texto($hoja, "A{$fila}", $g['etiqueta']);
        foreach (['B' => $g['actual'], 'C' => $g['anterior']] as $columna => $valor) {
            if ($valor === null) {
                $this->texto($hoja, "{$columna}{$fila}", 'Sin casos');
            } else {
                $hoja->setCellValue("{$columna}{$fila}", $g['unidad'] === 'porcentaje' ? $valor / 100 : $valor);
                $hoja->getStyle("{$columna}{$fila}")->getNumberFormat()->setFormatCode($g['unidad'] === 'porcentaje' ? '0.0%' : '0.0');
            }
        }
        if ($conUnidad) {
            $this->texto($hoja, "D{$fila}", $g['unidad'] === 'porcentaje' ? 'Porcentaje' : 'Días');
        }

        return $fila + 1;
    }

    /** Texto siempre como texto: un valor que empiece por «=» nunca se interpreta como fórmula. */
    private function texto(Worksheet $hoja, string $celda, string $valor): void
    {
        $hoja->setCellValueExplicit($celda, $valor, DataType::TYPE_STRING);
    }
}
