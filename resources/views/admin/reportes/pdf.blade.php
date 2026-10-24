<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $s['titulo'] }}</title>
    {{-- Documento para Dompdf (ExportadorReporte::pdf): tablas y CSS simple, sin recursos externos ni scripts. --}}
    <style>
        @page { margin: 22mm 16mm 18mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1e293b; }
        h1 { font-size: 17pt; color: #172554; margin: 0 0 2pt 0; }
        h2 { font-size: 11.5pt; color: #172554; margin: 14pt 0 6pt 0; padding-bottom: 3pt; border-bottom: 1pt solid #cbd5e1; }
        .marca { font-size: 8pt; letter-spacing: 1.5pt; color: #b45309; text-transform: uppercase; margin: 0 0 4pt 0; }
        .meta { color: #475569; margin: 1pt 0; }
        .nota { color: #64748b; font-size: 8pt; margin: 4pt 0 0 0; }
        .vacio { color: #64748b; font-style: italic; margin: 4pt 0; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #172554; color: #ffffff; font-weight: bold; text-align: left; padding: 4pt 6pt; font-size: 8.5pt; }
        td { padding: 2.8pt 6pt; border-bottom: 0.5pt solid #e2e8f0; }
        tr:nth-child(even) td { background: #f8fafc; }
        .num { text-align: right; white-space: nowrap; }
        .resumen td { border: 0.5pt solid #cbd5e1; padding: 7pt; width: 25%; vertical-align: top; background: #ffffff; }
        .resumen .valor { font-size: 16pt; font-weight: bold; color: #172554; }
        .resumen .etiqueta { color: #475569; font-size: 8.5pt; }
        .dos-columnas td.columna { width: 50%; vertical-align: top; padding: 0 6pt 0 0; border: 0; background: #ffffff; }
        .bloque { page-break-inside: avoid; }
        .pie { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5pt; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @php
        $numero = fn ($v, int $d = 1) => number_format((float) $v, $d, ',', '.');
        $gestionValor = fn (?float $v, string $unidad) => $v === null ? 'Sin casos' : ($unidad === 'porcentaje' ? $numero($v, 1).' %' : $numero($v, 1).' '.((float) $v === 1.0 ? 'día' : 'días'));
    @endphp

    <div class="pie">{{ $s['titulo'] }} · Generado el {{ $s['generado'] }} · Documento de uso interno del equipo administrador</div>

    <p class="marca">Reportes y estadísticas</p>
    <h1>{{ $s['titulo'] }}</h1>
    <p class="meta"><strong>Período:</strong> {{ $s['periodo'] }}</p>
    @if ($s['comparacion'])<p class="meta">{{ $s['comparacion'] }}</p>@endif
    <p class="meta"><strong>Generado:</strong> {{ $s['generado'] }}</p>

    <h2>Resumen</h2>
    <table class="resumen">
        <tr>
            @foreach ($s['resumen'] as [$etiqueta, $valor, $anterior])
                <td>
                    <div class="etiqueta">{{ $etiqueta }}</div>
                    <div class="valor">{{ $valor }}</div>
                    @if ($s['comparacion'])<div class="etiqueta">Período anterior: {{ $anterior }}</div>@endif
                </td>
            @endforeach
            @if ($s['gestion'] !== null)
                @foreach (array_slice($s['gestion'], 0, 2) as $g)
                    <td>
                        <div class="etiqueta">{{ $g['etiqueta'] }}</div>
                        <div class="valor">{{ $gestionValor($g['actual'], $g['unidad']) }}</div>
                        <div class="etiqueta">Período anterior: {{ $gestionValor($g['anterior'], $g['unidad']) }}</div>
                    </td>
                @endforeach
            @endif
        </tr>
    </table>

    <h2>Indicadores del período</h2>
    @if ($s['indicadores'] === null)
        <p class="vacio">{{ $s['sinPeriodo'] }}</p>
    @else
        <table>
            <thead><tr><th>Indicador</th><th class="num">Período</th><th class="num">Período anterior</th><th>Tendencia</th><th class="num">Variación</th></tr></thead>
            <tbody>
                @foreach ($s['indicadores'] as $r)
                    <tr><td>{{ $r['etiqueta'] }}</td><td class="num">{{ $r['actual'] }}</td><td class="num">{{ $r['anterior'] }}</td><td>{{ $r['tendencia'] }}</td><td class="num">{{ $r['porcentaje'] === null ? '—' : $numero($r['porcentaje']).' %' }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <p class="nota">La variación solo se calcula si el período anterior no es cero.</p>
    @endif

    <h2>Indicadores de gestión</h2>
    @if ($s['gestion'] === null)
        <p class="vacio">{{ $s['sinPeriodo'] }}</p>
    @else
        <table>
            <thead><tr><th>Indicador</th><th class="num">Período</th><th class="num">Período anterior</th></tr></thead>
            <tbody>
                @foreach ($s['gestion'] as $g)
                    <tr><td>{{ $g['etiqueta'] }}</td><td class="num">{{ $gestionValor($g['actual'], $g['unidad']) }}</td><td class="num">{{ $gestionValor($g['anterior'], $g['unidad']) }}</td></tr>
                @endforeach
                <tr><td>Seguimientos sin voluntario (estado actual)</td><td class="num">{{ $s['seguimientosSinVoluntario'] }}</td><td class="num">—</td></tr>
            </tbody>
        </table>
        <p class="nota">{{ $s['notaTiempoRespuesta'] }}</p>

        <div class="bloque">
        <h2>Embudo de adopción</h2>
        <p class="nota">Estado actual de las solicitudes recibidas en el período.</p>
        @if ($s['embudo']['recibidas'] === 0)
            <p class="vacio">No se recibieron solicitudes en este período.</p>
        @else
            <table>
                <thead><tr><th>Etapa</th><th class="num">Solicitudes</th><th class="num">% de las recibidas</th></tr></thead>
                <tbody>
                    @foreach ($s['embudo']['etapas'] as [$etapa, $cantidad, $pct])
                        <tr><td>{{ $etapa }}</td><td class="num">{{ $cantidad }}</td><td class="num">{{ $numero($pct) }} %</td></tr>
                    @endforeach
                </tbody>
            </table>
            <p class="nota">El {{ $numero($s['embudo']['porcentajeAdopcion'], 0) }} % de las solicitudes recibidas en el período terminó en adopción.</p>
        @endif
        </div>
    @endif

    <h2>Solicitudes y adopciones por mes</h2>
    <table>
        <thead><tr><th>Mes</th><th class="num">Solicitudes recibidas</th><th class="num">Adopciones</th></tr></thead>
        <tbody>
            @foreach ($s['evolucion'] as [$mes, $solicitudes, $adopciones])
                <tr><td>{{ ucfirst($mes) }}</td><td class="num">{{ $solicitudes }}</td><td class="num">{{ $adopciones }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <p class="nota">Últimos 6 meses, independiente del período. Solicitudes por fecha de envío; adopciones concretadas por fecha de resolución. En el estado actual, {{ $s['adopcionesSinFechaResolucion'] }} {{ $s['adopcionesSinFechaResolucion'] === 1 ? 'adopción aceptada carece' : 'adopciones aceptadas carecen' }} de fecha de resolución verificable y no aparece{{ $s['adopcionesSinFechaResolucion'] === 1 ? '' : 'n' }} en la serie; no se asigna una fecha.</p>

    <div class="bloque">
    <h2>Mascotas disponibles (estado actual)</h2>
    @if ($s['disponibles']['total'] === 0)
        <p class="vacio">No hay mascotas disponibles en este momento.</p>
    @else
        <table class="dos-columnas"><tr>
            @foreach (['Por especie' => $s['disponibles']['especie'], 'Por tamaño' => $s['disponibles']['tamano']] as $grupo => $valores)
                <td class="columna">
                    <table>
                        <thead><tr><th>{{ $grupo }}</th><th class="num">Mascotas</th></tr></thead>
                        <tbody>@foreach ($valores as $etiqueta => $cantidad)<tr><td>{{ $etiqueta }}</td><td class="num">{{ $cantidad }}</td></tr>@endforeach</tbody>
                    </table>
                </td>
            @endforeach
        </tr></table>
        <p class="nota">{{ $s['disponibles']['total'] }} {{ $s['disponibles']['total'] === 1 ? 'mascota espera' : 'mascotas esperan' }} hogar.</p>
    @endif
    </div>

    <div class="bloque">
    <h2>Esperan hogar hace más tiempo</h2>
    @if ($s['espera'] === [])
        <p class="vacio">No hay mascotas disponibles en este momento.</p>
    @else
        <table>
            <thead><tr><th>Nombre</th><th>Especie</th><th>Tamaño</th><th>Edad</th><th>Registrada el</th><th class="num">Días de espera</th></tr></thead>
            <tbody>
                @foreach ($s['espera'] as $e)
                    <tr><td>{{ $e['nombre'] }}</td><td>{{ $e['especie'] }}</td><td>{{ $e['tamano'] }}</td><td>{{ $e['edad'] }}</td><td>{{ $e['registrada']->format('d/m/Y') }}</td><td class="num">{{ $e['dias'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
    </div>

    <h2>Estado actual</h2>
    <p class="nota">Cantidades a la fecha de generación, sin filtro de período.</p>
    <table>
        <thead><tr><th>Grupo</th><th>Concepto</th><th class="num">Cantidad</th></tr></thead>
        <tbody>
            @foreach ($s['estado'] as $grupo => $conceptos)
                @foreach ($conceptos as $concepto => $cantidad)
                    <tr><td>{{ $loop->first ? $grupo : '' }}</td><td>{{ $concepto }}</td><td class="num">{{ $cantidad }}</td></tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</body>
</html>
