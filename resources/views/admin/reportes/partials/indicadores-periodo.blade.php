{{--
    Indicadores del período (solo con período), en una lista corta: una línea por dato con su comparación.
    Las explicaciones largas van en el ícono ⓘ (title) para no ocupar espacio.
--}}
@php
    $t = $gestion['tiempo_respuesta'];
    $tasa = $gestion['tasa_aprobacion'];
    $explicaTasa = $tasa['actual'] === null
        ? 'Ninguna adopción concretada ni rechazo en el período'
        : $tasa['aceptadas'].' '.($tasa['aceptadas'] === 1 ? 'adopción concretada' : 'adopciones concretadas').' de '.$tasa['resueltas'].' '.($tasa['resueltas'] === 1 ? 'solicitud aceptada o rechazada' : 'solicitudes aceptadas o rechazadas').'. Excluye entregas no concretadas y cancelaciones.';
    $explicaTiempo = 'Desde la creación de la solicitud hasta la decisión administrativa; no incluye el tiempo posterior hasta la entrega.';
    $info = fn (string $texto) => '<span class="ms-1 inline-flex h-4 w-4 cursor-help items-center justify-center rounded-full bg-slate-200 text-[10px] font-bold text-slate-600" title="'.e($texto).'" aria-hidden="true">i</span><span class="sr-only">('.e($texto).')</span>';
@endphp
<section aria-labelledby="titulo-indicadores" class="{{ $tarjeta }} p-4 sm:p-5">
    <h3 id="titulo-indicadores" class="text-base font-semibold text-blue-950">Resultados del período</h3>
    <dl class="mt-2 divide-y divide-slate-100 text-sm">
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 py-2">
            <dt class="text-slate-700">Adopciones realizadas</dt>
            <dd class="flex items-baseline gap-3"><span class="text-[13px] text-slate-500"><x-reportes.variacion :indicador="$i['adopciones_completadas']" /></span><span class="min-w-[3rem] text-right text-lg font-bold text-blue-950">{{ $i['adopciones_completadas']['actual'] }}</span></dd>
        </div>
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 py-2">
            <dt class="text-slate-700">Solicitudes recibidas</dt>
            <dd class="flex items-baseline gap-3"><span class="text-[13px] text-slate-500"><x-reportes.variacion :indicador="$i['solicitudes_recibidas']" /></span><span class="min-w-[3rem] text-right text-lg font-bold text-blue-950">{{ $i['solicitudes_recibidas']['actual'] }}</span></dd>
        </div>
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 py-2">
            <dt class="text-slate-700">Solicitudes que terminaron en adopción {!! $info($explicaTasa) !!}</dt>
            <dd class="flex items-baseline gap-3">
                @if ($tasa['actual'] !== null && $tasa['anterior'] !== null)<span class="text-[13px] text-slate-500">antes {{ $numero($tasa['anterior'], 0) }} %</span>@endif
                <span class="min-w-[3rem] text-right text-lg font-bold {{ $tasa['actual'] === null ? 'text-base font-semibold text-slate-400' : 'text-blue-950' }}">{{ $tasa['actual'] === null ? 'Sin decisiones' : $numero($tasa['actual'], 0).' %' }}</span>
            </dd>
        </div>
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 py-2">
            <dt class="text-slate-700">Tiempo promedio de respuesta {!! $info($explicaTiempo) !!}</dt>
            <dd class="flex items-baseline gap-3">
                @if ($t['actual'] !== null)
                    <span class="text-[13px] text-slate-500">
                        @if ($t['diferencia'] === null) Sin decisiones en el período anterior
                        @elseif ($t['diferencia'] < 0)<span class="font-semibold text-emerald-700"><span aria-hidden="true">▼</span> {{ $dias(abs($t['diferencia'])) }}</span> más rápido
                        @elseif ($t['diferencia'] > 0)<span class="font-semibold text-amber-700"><span aria-hidden="true">▲</span> {{ $dias($t['diferencia']) }}</span> más lento
                        @else Igual que antes
                        @endif
                    </span>
                @endif
                <span class="min-w-[3rem] text-right text-lg font-bold {{ $t['actual'] === null ? 'text-base font-semibold text-slate-400' : 'text-blue-950' }}">{{ $t['actual'] === null ? 'Sin decisiones' : $dias($t['actual']) }}</span>
            </dd>
        </div>
    </dl>
</section>
