<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $s['titulo'] }}</title>
    {{-- Informe corto para Dompdf (ExportadorReporte::pdf): tablas y CSS simple, sin recursos externos ni scripts. --}}
    <style>
        @page { margin: 16mm 15mm 16mm 15mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; }
        .membrete { width: 100%; border-collapse: collapse; border-bottom: 1.5pt solid #172554; margin-bottom: 8pt; }
        .membrete td { vertical-align: bottom; padding: 0 0 6pt 0; }
        .membrete .logo { width: 120px; }
        h1 { font-size: 15pt; color: #172554; margin: 0; text-align: right; }
        .meta { color: #475569; margin: 1pt 0; text-align: right; font-size: 8.5pt; }
        h2 { font-size: 11pt; color: #172554; margin: 12pt 0 3pt 0; }
        .etiqueta-alcance { display: inline-block; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.6pt; padding: 2pt 6pt; border-radius: 3pt; margin-bottom: 5pt; }
        .alcance-periodo { background: #dbeafe; color: #1e3a8a; }
        .alcance-actual { background: #fef3c7; color: #92400e; }
        .nota { color: #64748b; font-size: 7.5pt; margin: 3pt 0 0 0; }
        .vacio { color: #15803d; font-size: 8.5pt; margin: 2pt 0 0 0; }
        table { width: 100%; border-collapse: collapse; }
        .kpis td { border: 0.6pt solid #cbd5e1; padding: 6pt 7pt; vertical-align: top; background: #ffffff; }
        .kpis .valor { font-size: 17pt; font-weight: bold; color: #172554; }
        .kpis .texto { color: #475569; font-size: 8pt; }
        .kpis .antes { color: #64748b; font-size: 7.5pt; margin-top: 2pt; }
        th { background: #172554; color: #ffffff; font-weight: bold; text-align: left; padding: 3pt 5pt; font-size: 8pt; }
        td { padding: 2.5pt 5pt; border-bottom: 0.5pt solid #e2e8f0; }
        .num { text-align: right; white-space: nowrap; }
        .situacion td.columna { width: 50%; vertical-align: top; padding: 0 5pt 6pt 0; border: 0; }
        .situacion td.columna table td { font-size: 8.5pt; }
        .bloque { page-break-inside: avoid; margin-top: 8pt; }
        .bloque h3 { font-size: 9.5pt; color: #172554; margin: 0 0 1pt 0; }
        .bloque h3 .total { color: #b45309; }
        .bloque .ayuda { color: #64748b; font-size: 7.5pt; margin: 0 0 3pt 0; }
        .alerta h3 .total { color: #b91c1c; }
        .pie { position: fixed; bottom: -9mm; left: 0; right: 0; font-size: 7pt; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @php
        $numero = fn ($v, int $d = 1) => number_format((float) $v, $d, ',', '.');
        $gestionValor = fn (?float $v, string $unidad) => $v === null ? 'Sin casos' : ($unidad === 'porcentaje' ? $numero($v, 1).' %' : $numero($v, 1).' '.((float) $v === 1.0 ? 'día' : 'días'));
    @endphp

    <div class="pie">{{ $s['titulo'] }} · Generado el {{ $s['generado'] }} · Documento de uso interno del equipo administrador</div>

    <table class="membrete">
        <tr>
            <td><img class="logo" src="{{ $logo ?? '' }}" alt="POL-Bolivia"></td>
            <td>
                <h1>{{ $s['titulo'] }}</h1>
                <p class="meta"><strong>Período:</strong> {{ $s['periodo'] }}</p>
                @if ($s['comparacion'])<p class="meta">{{ $s['comparacion'] }}</p>@endif
                <p class="meta"><strong>Generado:</strong> {{ $s['generado'] }}</p>
            </td>
        </tr>
    </table>

    {{-- 1. Resumen principal --}}
    <h2>Resumen principal</h2>
    <span class="etiqueta-alcance alcance-periodo">{{ \App\Services\ExportadorReporte::ETIQUETA_PERIODO }}</span>
    <table class="kpis">
        <tr>
            @foreach ($s['periodoKpis'] as [$etiqueta, $valor, $anterior])
                <td>
                    <div class="texto">{{ $etiqueta }}</div>
                    <div class="valor">{{ $valor }}</div>
                    @if ($s['conPeriodo'])<div class="antes">Período anterior: {{ $anterior }}</div>@endif
                </td>
            @endforeach
            @if ($s['gestion'] !== null)
                @foreach ($s['gestion'] as $g)
                    <td>
                        <div class="texto">{{ $g['etiqueta'] }}</div>
                        <div class="valor">{{ $gestionValor($g['actual'], $g['unidad']) }}</div>
                        <div class="antes">Período anterior: {{ $gestionValor($g['anterior'], $g['unidad']) }}</div>
                    </td>
                @endforeach
            @endif
        </tr>
    </table>
    @unless ($s['conPeriodo'])<p class="nota">Elige un período en la pantalla de reportes para compararlo con el anterior y ver los indicadores de gestión.</p>@endunless

    <div style="margin-top: 7pt;"><span class="etiqueta-alcance alcance-actual">{{ \App\Services\ExportadorReporte::ETIQUETA_ACTUAL }}</span></div>
    <table class="kpis">
        <tr>
            @foreach ($s['actualKpis'] as [$etiqueta, $valor])
                <td><div class="texto">{{ $etiqueta }}</div><div class="valor">{{ $valor }}</div></td>
            @endforeach
        </tr>
    </table>

    {{-- 2. Situación actual --}}
    <div class="bloque">
        <h2>Situación actual</h2>
        <span class="etiqueta-alcance alcance-actual">{{ \App\Services\ExportadorReporte::ETIQUETA_ACTUAL }}</span>
        <table class="situacion">
            @foreach (array_chunk($s['situacion'], 2, true) as $par)
                <tr>
                    @foreach ($par as $grupo => $conceptos)
                        <td class="columna">
                            <table>
                                <thead><tr><th>{{ $grupo }}</th><th class="num">Cantidad</th></tr></thead>
                                <tbody>@foreach ($conceptos as $concepto => $cantidad)<tr><td>{{ $concepto }}</td><td class="num">{{ $cantidad }}</td></tr>@endforeach</tbody>
                            </table>
                        </td>
                    @endforeach
                    @if (count($par) === 1)<td class="columna"></td>@endif
                </tr>
            @endforeach
        </table>
    </div>
    {{-- 3. Requiere atención (siempre empieza en una página nueva, para que el título no quede solo) --}}
    <h2 style="page-break-before: always; margin-top: 0;">Requiere atención</h2>
    <span class="etiqueta-alcance alcance-actual">{{ \App\Services\ExportadorReporte::ETIQUETA_ACTUAL }}</span>
    @foreach ($s['atencion'] as $a)
        <div class="bloque {{ $a['total'] > 0 && $a['clave'] !== 'esperan_hogar' ? 'alerta' : '' }}">
            <h3>{{ $a['titulo'] }}: <span class="total">{{ $a['total'] }}</span></h3>
            <p class="ayuda">{{ $a['ayuda'] }}</p>
            @if ($a['filas'] === [])
                <p class="vacio">✓ Nada pendiente.</p>
            @else
                <table>
                    <thead><tr>@foreach ($a['columnas'] as $c => $columna)<th class="{{ $loop->last ? 'num' : '' }}">{{ $columna }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($a['filas'] as $fila)
                            <tr>@foreach ($fila as $valor)<td class="{{ $loop->last ? 'num' : '' }}">{{ $valor }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($a['total'] > count($a['filas']))<p class="nota">Y {{ $a['total'] - count($a['filas']) }} más. La lista completa está en el Excel y en la pantalla del sistema.</p>@endif
            @endif
        </div>
    @endforeach

</body>
</html>
