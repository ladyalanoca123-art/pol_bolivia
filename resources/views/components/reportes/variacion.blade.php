@props(['indicador'])

{{-- Comparación de un indicador de ReporteAdministrador::indicadores() con el período anterior. --}}
@php
    $v = $indicador['variacion'];
    $porcentaje = $v['porcentaje'] === null ? null : number_format($v['porcentaje'], $v['porcentaje'] == floor($v['porcentaje']) ? 0 : 1, ',', '.').' %';
@endphp

@switch($v['tipo'])
    @case('aumento')
        <span class="font-semibold text-emerald-700"><span aria-hidden="true">▲</span><span class="sr-only">Aumento de</span> {{ $porcentaje ?? $v['diferencia'] }}</span> vs. período anterior ({{ $indicador['anterior'] }})
        @break
    @case('disminucion')
        <span class="font-semibold text-slate-700"><span aria-hidden="true">▼</span><span class="sr-only">Disminución de</span> {{ $porcentaje ?? $v['diferencia'] }}</span> vs. período anterior ({{ $indicador['anterior'] }})
        @break
    @case('nuevo')
        <span class="font-semibold text-emerald-700">Nuevo</span> · ninguna en el período anterior
        @break
    @default
        Igual que el período anterior ({{ $indicador['anterior'] }})
@endswitch
