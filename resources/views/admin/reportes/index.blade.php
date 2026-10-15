<x-app-layout>
    {{--
        Reportes: estadísticas históricas por período (App\Services\ReporteAdministrador + App\Support\PeriodoReporte)
        y, aparte, cantidades del estado actual. La vista solo presenta: no consulta la base ni calcula indicadores.
    --}}
    @php
        $conPeriodo = $periodoReporte !== null;
        $i = $indicadoresPeriodo;
        $numero = fn ($valor, int $decimales = 1) => number_format((float) $valor, $decimales, ',', '.');
        $dias = fn (?float $valor) => $valor === null ? null : $numero($valor).' '.((float) $valor === 1.0 ? 'día' : 'días');
        $maxEvolucion = max(1, ...array_map(fn ($m) => max($m['solicitudes'], $m['adopciones']), $evolucionMensual));
        $hayEvolucion = collect($evolucionMensual)->contains(fn ($m) => $m['solicitudes'] + $m['adopciones'] > 0);
        $especies = ['perro' => 'Perros', 'gato' => 'Gatos'];
        $tamanos = ['pequeno' => 'Pequeño', 'mediano' => 'Mediano', 'grande' => 'Grande'];
        $maxDisponibles = max(1, ...array_values($disponibles['especie']), ...array_values($disponibles['tamano']));
        $edad = fn ($m) => $m->edad.' '.($m->edad_en === 'meses' ? ($m->edad === 1 ? 'mes' : 'meses') : ($m->edad === 1 ? 'año' : 'años'));
        $tarjeta = 'rounded-2xl border border-slate-200 bg-white';
        $enlace = 'inline-flex items-center gap-1 rounded text-sm font-semibold text-blue-800 hover:text-blue-950 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400';
        $vacio = 'text-sm text-slate-500';
        $personalizadoAbierto = $periodoReporte?->tipo === 'personalizado' || $errors->hasAny(['fecha_inicio', 'fecha_fin']);
        $fechasPersonalizadas = $periodoReporte?->tipo === 'personalizado' ? $periodoReporte->fechas() : ['fecha_inicio' => '', 'fecha_fin' => ''];
    @endphp

    <div class="min-h-[calc(100vh-4rem)] bg-slate-50">
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">

            {{-- Encabezado --}}
            <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Reportes y estadísticas</p>
                <h1 class="mt-2 text-2xl font-bold text-blue-950 sm:text-3xl">Reportes</h1>
                <p class="mt-1 text-sm text-slate-600 sm:text-base">
                    @if ($conPeriodo)
                        {{ ucfirst($periodoReporte->rango()) }} · comparado con el período anterior ({{ $periodoAnterior->rango() }})
                    @else
                        Totales acumulados y estado actual. Elige un período para ver comparaciones, el embudo y los indicadores de gestión.
                    @endif
                </p>
                </div>

                {{-- Descargas: mismo período que la pantalla (parametrosExportacion), protegidas como esta ruta. --}}
                <div class="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap lg:shrink-0" aria-label="Descargar el reporte">
                    <a href="{{ route('admin.reportes.pdf', $parametrosExportacion) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-red-700 px-5 text-sm font-semibold text-white hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2">
                        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 11v6"/><path d="m9 14 3 3 3-3"/></svg>
                        Descargar PDF
                    </a>
                    <a href="{{ route('admin.reportes.excel', $parametrosExportacion) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-emerald-700 px-5 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M4 9h16M4 15h16M10 3v18"/></svg>
                        Descargar Excel
                    </a>
                </div>
            </header>

            {{-- Selector de período --}}
            <section aria-labelledby="titulo-periodo" class="{{ $tarjeta }} p-3 sm:p-4">
                <h2 id="titulo-periodo" class="sr-only">Período del reporte</h2>
                <div class="flex flex-wrap items-start gap-2">
                <nav aria-label="Períodos" class="-mx-1 flex max-w-full gap-2 overflow-x-auto px-1 pb-1">
                    @foreach (\App\Support\PeriodoReporte::TIPOS as $clave => $nombre)
                        @continue($clave === 'personalizado')
                        @php $activo = $periodoReporte?->tipo === $clave; @endphp
                        <a href="{{ route('admin.reportes.index', ['periodo' => $clave]) }}" @if ($activo) aria-current="true" @endif
                           class="{{ $activo ? 'border-blue-950 bg-blue-950 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400' }} inline-flex min-h-10 shrink-0 items-center whitespace-nowrap rounded-full border px-4 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">{{ $nombre }}</a>
                    @endforeach
                    @if ($conPeriodo)
                        <a href="{{ route('admin.reportes.index') }}" class="inline-flex min-h-10 shrink-0 items-center whitespace-nowrap rounded-full px-3 text-sm font-medium text-slate-500 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">Quitar período</a>
                    @endif
                </nav>

                <details class="group open:basis-full" @if ($personalizadoAbierto) open @endif>
                    <summary class="{{ $periodoReporte?->tipo === 'personalizado' ? 'border-blue-950 bg-blue-950 text-white' : 'border-slate-300 bg-white text-slate-700' }} inline-flex min-h-10 cursor-pointer list-none items-center gap-2 rounded-full border px-4 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                        <span aria-hidden="true">📅</span> Personalizado
                    </summary>
                    <form method="GET" action="{{ route('admin.reportes.index') }}" class="mt-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <input type="hidden" name="periodo" value="personalizado">
                        <div>
                            <label for="fecha_inicio" class="block text-sm font-medium text-slate-700">Fecha inicial</label>
                            <input id="fecha_inicio" type="date" name="fecha_inicio" value="{{ old('fecha_inicio', $fechasPersonalizadas['fecha_inicio']) }}" min="{{ \App\Support\PeriodoReporte::FECHA_MINIMA }}" max="{{ now()->toDateString() }}" required
                                   class="mt-1 w-full rounded-lg border-slate-300 text-sm sm:w-auto" @error('fecha_inicio') aria-invalid="true" aria-describedby="error-fecha-inicio" @enderror>
                            @error('fecha_inicio')<p id="error-fecha-inicio" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="fecha_fin" class="block text-sm font-medium text-slate-700">Fecha final</label>
                            <input id="fecha_fin" type="date" name="fecha_fin" value="{{ old('fecha_fin', $fechasPersonalizadas['fecha_fin']) }}" min="{{ \App\Support\PeriodoReporte::FECHA_MINIMA }}" max="{{ now()->toDateString() }}" required
                                   class="mt-1 w-full rounded-lg border-slate-300 text-sm sm:w-auto" @error('fecha_fin') aria-invalid="true" aria-describedby="error-fecha-fin" @enderror>
                            @error('fecha_fin')<p id="error-fecha-fin" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-full bg-blue-950 px-5 text-sm font-semibold text-white hover:bg-blue-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2">Aplicar</button>
                        <p class="text-xs text-slate-500 sm:basis-full">Hasta {{ \App\Support\PeriodoReporte::MAX_DIAS_PERSONALIZADO }} días, entre el {{ \Illuminate\Support\Carbon::parse(\App\Support\PeriodoReporte::FECHA_MINIMA)->translatedFormat('j \d\e F \d\e Y') }} y hoy.</p>
                    </form>
                </details>
                </div>
                @error('periodo')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </section>

            {{-- Indicadores principales --}}
            <section aria-labelledby="titulo-indicadores">
                <h2 id="titulo-indicadores" class="sr-only">Indicadores principales</h2>
                <dl class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    @if ($conPeriodo)
                        <div class="{{ $tarjeta }} min-w-0 p-4 sm:p-5">
                            <dt class="text-sm font-medium text-slate-600">Adopciones realizadas</dt>
                            <dd class="mt-2 text-3xl font-bold text-blue-950">{{ $i['adopciones_completadas']['actual'] }}</dd>
                            <dd class="mt-2 text-xs text-slate-500"><x-reportes.variacion :indicador="$i['adopciones_completadas']" /></dd>
                        </div>
                        <div class="{{ $tarjeta }} min-w-0 p-4 sm:p-5">
                            <dt class="text-sm font-medium text-slate-600">Solicitudes recibidas</dt>
                            <dd class="mt-2 text-3xl font-bold text-blue-950">{{ $i['solicitudes_recibidas']['actual'] }}</dd>
                            <dd class="mt-2 text-xs text-slate-500"><x-reportes.variacion :indicador="$i['solicitudes_recibidas']" /></dd>
                        </div>
                        <div class="{{ $tarjeta }} min-w-0 p-4 sm:p-5">
                            <dt class="text-sm font-medium text-slate-600">Tasa de aprobación</dt>
                            @if ($gestion['tasa_aprobacion']['actual'] === null)
                                <dd class="mt-2 text-xl font-semibold text-slate-400">Sin decisiones</dd>
                                <dd class="mt-2 text-xs text-slate-500">Ninguna solicitud aceptada ni rechazada en el período</dd>
                            @else
                                <dd class="mt-2 text-3xl font-bold text-blue-950">{{ $numero($gestion['tasa_aprobacion']['actual'], 0) }} <span class="text-lg font-semibold text-slate-500">%</span></dd>
                                <dd class="mt-2 text-xs text-slate-500">
                                    {{ $gestion['tasa_aprobacion']['aceptadas'] }} {{ $gestion['tasa_aprobacion']['aceptadas'] === 1 ? 'aceptada' : 'aceptadas' }} de {{ $gestion['tasa_aprobacion']['resueltas'] }} {{ $gestion['tasa_aprobacion']['resueltas'] === 1 ? 'resuelta' : 'resueltas' }}
                                    @if ($gestion['tasa_aprobacion']['anterior'] !== null) · antes {{ $numero($gestion['tasa_aprobacion']['anterior'], 0) }} % @endif
                                </dd>
                            @endif
                        </div>
                        <div class="{{ $tarjeta }} min-w-0 p-4 sm:p-5">
                            <dt class="text-sm font-medium text-slate-600">Tiempo promedio de respuesta</dt>
                            @php $t = $gestion['tiempo_respuesta']; @endphp
                            @if ($t['actual'] === null)
                                <dd class="mt-2 text-xl font-semibold text-slate-400">Sin decisiones</dd>
                                <dd class="mt-2 text-xs text-slate-500">Desde la solicitud hasta su aceptación o rechazo</dd>
                            @else
                                <dd class="mt-2 text-3xl font-bold text-blue-950">{{ $numero($t['actual']) }} <span class="text-lg font-semibold text-slate-500">{{ (float) $t['actual'] === 1.0 ? 'día' : 'días' }}</span></dd>
                                <dd class="mt-2 text-xs text-slate-500">
                                    @if ($t['diferencia'] === null)
                                        Sin decisiones en el período anterior
                                    @elseif ($t['diferencia'] < 0)
                                        <span class="font-semibold text-emerald-700"><span aria-hidden="true">▼</span> {{ $dias(abs($t['diferencia'])) }}</span> más rápido que antes
                                    @elseif ($t['diferencia'] > 0)
                                        <span class="font-semibold text-amber-700"><span aria-hidden="true">▲</span> {{ $dias($t['diferencia']) }}</span> más lento que antes
                                    @else
                                        Igual que el período anterior
                                    @endif
                                </dd>
                            @endif
                        </div>
                    @else
                        @foreach ([['Adopciones realizadas', $totalAdopciones, 'Total acumulado'], ['Solicitudes de adopción', $totalSolicitudes, 'Total acumulado'], ['Mascotas disponibles', $mascotasPorEstado['disponible'], 'Estado actual'], ['Seguimientos activos', $seguimientosPorEstado['activo'], 'Estado actual']] as [$titulo, $valor, $ayuda])
                            <div class="{{ $tarjeta }} min-w-0 p-4 sm:p-5">
                                <dt class="text-sm font-medium text-slate-600">{{ $titulo }}</dt>
                                <dd class="mt-2 text-3xl font-bold text-blue-950">{{ $valor }}</dd>
                                <dd class="mt-2 text-xs text-slate-500">{{ $ayuda }}</dd>
                            </div>
                        @endforeach
                    @endif
                </dl>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {{-- Evolución mensual --}}
                <section aria-labelledby="titulo-evolucion" class="{{ $tarjeta }} min-w-0 p-4 sm:p-5 lg:col-span-2">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 id="titulo-evolucion" class="text-lg font-semibold text-blue-950">Solicitudes y adopciones por mes</h2>
                            <p class="text-sm text-slate-500">Últimos 6 meses (no depende del período elegido)</p>
                        </div>
                        <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium text-slate-600" aria-hidden="true">
                            <li class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-blue-600"></span> Solicitudes recibidas</li>
                            <li class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-amber-500"></span> Adopciones</li>
                        </ul>
                    </div>

                    @if ($hayEvolucion)
                        <div class="mt-4" aria-hidden="true">
                            <div class="flex items-end justify-between gap-2 border-b border-slate-200 sm:gap-4">
                                @foreach ($evolucionMensual as $mes)
                                    <div class="flex min-w-0 flex-1 justify-center gap-1">
                                        @foreach ([['solicitudes', 'fill-blue-600'], ['adopciones', 'fill-amber-500']] as [$serie, $color])
                                            @php $alto = round($mes[$serie] / $maxEvolucion * 100, 1); @endphp
                                            <svg class="h-40 w-3 sm:w-6" viewBox="0 0 10 100" preserveAspectRatio="none">
                                                <rect x="0" y="{{ 100 - $alto }}" width="10" height="{{ $alto }}" rx="1" class="{{ $color }}"><title>{{ $mes[$serie] }}</title></rect>
                                            </svg>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-1 flex justify-between gap-2 sm:gap-4">
                                @foreach ($evolucionMensual as $mes)
                                    <div class="min-w-0 flex-1 text-center">
                                        <p class="text-xs font-medium capitalize text-slate-600">{{ $mes['etiqueta'] }}@if ($mes['en_curso'])*@endif</p>
                                        <p class="text-[0.7rem] text-slate-500"><span class="text-blue-700">{{ $mes['solicitudes'] }}</span> · <span class="text-amber-700">{{ $mes['adopciones'] }}</span></p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="mt-6 {{ $vacio }}">No hay solicitudes ni adopciones registradas en los últimos 6 meses.</p>
                    @endif

                    <details class="mt-4">
                        <summary class="{{ $enlace }} cursor-pointer">Ver como tabla</summary>
                        <div class="mt-2 overflow-x-auto">
                            <table class="w-full min-w-[18rem] text-left text-sm">
                                <caption class="sr-only">Solicitudes recibidas y adopciones completadas por mes</caption>
                                <thead class="text-xs uppercase text-slate-500"><tr><th scope="col" class="py-1 pe-3">Mes</th><th scope="col" class="py-1 pe-3 text-right">Solicitudes</th><th scope="col" class="py-1 text-right">Adopciones</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($evolucionMensual as $mes)
                                        <tr><th scope="row" class="py-1 pe-3 font-medium capitalize text-slate-700">{{ \Illuminate\Support\Carbon::parse($mes['mes'].'-01')->translatedFormat('F Y') }}@if ($mes['en_curso']) (en curso)@endif</th><td class="py-1 pe-3 text-right">{{ $mes['solicitudes'] }}</td><td class="py-1 text-right">{{ $mes['adopciones'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                    <div class="mt-3 flex flex-col gap-1 border-t border-slate-100 pt-3 text-xs text-slate-500 sm:flex-row sm:justify-between">
                        <p>* Mes en curso. Adopciones por fecha de aceptación; solicitudes por fecha de envío.</p>
                        @if ($conPeriodo)
                            <p class="font-medium text-slate-700">Adopciones en el período: {{ $totalAdopciones }}</p>
                        @endif
                    </div>
                </section>

                {{-- Embudo de adopción --}}
                <section aria-labelledby="titulo-embudo" class="{{ $tarjeta }} min-w-0 p-4 sm:p-5">
                    <h2 id="titulo-embudo" class="text-lg font-semibold text-blue-950">Embudo de adopción</h2>
                    <p class="text-sm text-slate-500">Estado actual de las solicitudes recibidas en el período</p>
                    @if (! $conPeriodo)
                        <p class="mt-6 {{ $vacio }}">Elige un período para ver qué pasó con sus solicitudes.</p>
                    @elseif ($embudo['recibidas'] === 0)
                        <p class="mt-6 {{ $vacio }}">No se recibieron solicitudes en este período.</p>
                    @else
                        <ol class="mt-4 space-y-3">
                            @foreach ([['Solicitudes recibidas', 'recibidas', 'fill-blue-900'], ['Evaluadas por el equipo', 'evaluadas', 'fill-blue-700'], ['Aceptadas', 'aceptadas', 'fill-blue-500'], ['Seguimiento cerrado', 'seguimiento_cerrado', 'fill-blue-300']] as [$etiqueta, $clave, $color])
                                @php $pct = round($embudo[$clave] / $embudo['recibidas'] * 100); @endphp
                                <li>
                                    <div class="flex items-baseline justify-between gap-2 text-sm"><span class="text-slate-700">{{ $etiqueta }}</span><span class="font-semibold text-blue-950">{{ $embudo[$clave] }} <span class="text-xs font-normal text-slate-500">({{ $pct }} %)</span></span></div>
                                    <svg class="mt-1 h-2.5 w-full" viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true"><rect width="100" height="10" rx="5" class="fill-slate-100"/><rect width="{{ $pct }}" height="10" rx="5" class="{{ $color }}"/></svg>
                                </li>
                            @endforeach
                        </ol>
                        <p class="mt-4 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-900">El {{ $numero($embudo['porcentaje_adopcion'], 0) }} % de las solicitudes recibidas en el período terminó en adopción.</p>
                    @endif
                </section>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                {{-- Indicadores de gestión --}}
                <section aria-labelledby="titulo-gestion" class="{{ $tarjeta }} min-w-0 p-4 sm:p-5">
                    <h2 id="titulo-gestion" class="text-lg font-semibold text-blue-950">Indicadores de gestión</h2>
                    <p class="text-sm text-slate-500">Calidad del servicio en el período</p>
                    @if (! $conPeriodo)
                        <p class="mt-6 {{ $vacio }}">Elige un período para calcular los tiempos de atención.</p>
                    @else
                        <dl class="mt-3 divide-y divide-slate-100 text-sm">
                            @foreach ([
                                ['Días promedio hasta la adopción', $dias($gestion['dias_hasta_adopcion']['actual'])],
                                ['Visitas de seguimiento cumplidas', $gestion['visitas_cumplidas']['actual'] === null ? null : $numero($gestion['visitas_cumplidas']['actual'], 0).' % ('.$gestion['visitas_cumplidas']['realizadas'].' de '.$gestion['visitas_cumplidas']['con_resultado'].')'],
                                ['Atención de incidencias', $dias($gestion['atencion_incidencias']['actual'])],
                                ['Revisión de postulaciones', $dias($gestion['revision_postulaciones']['actual'])],
                            ] as [$etiqueta, $valor])
                                <div class="flex items-baseline justify-between gap-3 py-2.5">
                                    <dt class="text-slate-700">{{ $etiqueta }}</dt>
                                    <dd class="shrink-0 text-right font-semibold {{ $valor === null ? 'font-normal text-slate-400' : 'text-blue-950' }}">{{ $valor ?? 'Sin casos' }}</dd>
                                </div>
                            @endforeach
                            <div class="flex items-baseline justify-between gap-3 py-2.5">
                                <dt class="text-slate-700">Seguimientos sin voluntario <span class="ms-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">ahora</span></dt>
                                <dd class="shrink-0 font-semibold text-blue-950">{{ $seguimientosPorEstado['sin_asignar'] }}</dd>
                            </div>
                        </dl>
                    @endif
                </section>

                {{-- Mascotas disponibles (estado actual) --}}
                <section aria-labelledby="titulo-disponibles" class="{{ $tarjeta }} min-w-0 p-4 sm:p-5">
                    <h2 id="titulo-disponibles" class="text-lg font-semibold text-blue-950">Mascotas disponibles</h2>
                    <p class="text-sm text-slate-500">
                        @if ($disponibles['total'] === 0) Estado actual @else {{ $disponibles['total'] }} {{ $disponibles['total'] === 1 ? 'mascota espera' : 'mascotas esperan' }} hogar (estado actual) @endif
                    </p>
                    @if ($disponibles['total'] === 0)
                        <p class="mt-6 {{ $vacio }}">No hay mascotas disponibles en este momento.</p>
                    @else
                        @foreach ([['Por especie', $disponibles['especie'], $especies], ['Por tamaño', $disponibles['tamano'], $tamanos]] as [$grupo, $valores, $etiquetas])
                            <h3 class="mt-4 text-sm font-semibold text-slate-700">{{ $grupo }}</h3>
                            <dl class="mt-2 space-y-2">
                                @foreach ($etiquetas as $clave => $etiqueta)
                                    <div class="grid grid-cols-[5.5rem_1fr_2rem] items-center gap-3 text-sm">
                                        <dt class="text-slate-700">{{ $etiqueta }}</dt>
                                        <svg class="h-2.5 w-full" viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true"><rect width="100" height="10" rx="5" class="fill-slate-100"/><rect width="{{ round(($valores[$clave] ?? 0) / $maxDisponibles * 100, 1) }}" height="10" rx="5" class="fill-blue-600"/></svg>
                                        <dd class="text-right font-semibold text-blue-950">{{ $valores[$clave] ?? 0 }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endforeach
                    @endif
                </section>

                {{-- Mayor tiempo de espera (estado actual) --}}
                <section aria-labelledby="titulo-espera" class="{{ $tarjeta }} min-w-0 p-4 sm:p-5 md:col-span-2 lg:col-span-1">
                    <h2 id="titulo-espera" class="text-lg font-semibold text-blue-950">Esperan hogar hace más tiempo</h2>
                    <p class="text-sm text-slate-500">Días desde su registro; ideal para darles difusión</p>
                    @if ($esperanMasTiempo->isEmpty())
                        <p class="mt-6 {{ $vacio }}">No hay mascotas disponibles en este momento.</p>
                    @else
                        <ul class="mt-3 divide-y divide-slate-100">
                            @foreach ($esperanMasTiempo as ['mascota' => $m, 'dias' => $espera])
                                <li class="flex items-center gap-3 py-2.5">
                                    <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-xl">{{ $m->especie === 'gato' ? '🐱' : '🐶' }}</span>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('admin.mascotas.show', $m) }}" class="font-semibold text-blue-950 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">{{ $m->nombre }}</a>
                                        <p class="truncate text-sm text-slate-500">{{ $m->especie === 'gato' ? 'Gato' : 'Perro' }} · {{ mb_strtolower($tamanos[$m->tamano] ?? $m->tamano) }} · {{ $edad($m) }}</p>
                                    </div>
                                    <p class="shrink-0 text-sm font-semibold text-amber-700">{{ $espera }} {{ $espera === 1 ? 'día' : 'días' }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            {{-- Estado actual (no depende del período) --}}
            <section aria-labelledby="titulo-estado" class="{{ $tarjeta }} p-4 sm:p-5">
                <h2 id="titulo-estado" class="text-lg font-semibold text-blue-950">Estado actual</h2>
                <p class="text-sm text-slate-500">Cantidades a hoy, sin filtro de período</p>
                <div class="mt-4 grid grid-cols-1 gap-x-8 gap-y-5 sm:grid-cols-2 xl:grid-cols-5">
                    @foreach ([
                        'Mascotas' => ['Total mascotas' => $totalMascotas, 'Mascotas disponibles' => $mascotasPorEstado['disponible'], 'Mascotas adoptadas' => $mascotasPorEstado['adoptada'], 'Perros' => $mascotasPorEspecie['perro'], 'Gatos' => $mascotasPorEspecie['gato']],
                        'Solicitudes de adopción' => ['Pendientes' => $solicitudesPorEstado['pendiente'], 'En evaluación' => $solicitudesPorEstado['en_espera'], 'Aceptadas' => $solicitudesPorEstado['aceptada'], 'Rechazadas' => $solicitudesPorEstado['rechazada'], 'Canceladas' => $solicitudesPorEstado['cancelada'], 'Cerradas al adoptarse la mascota' => $solicitudesPorEstado['cerrada']],
                        'Seguimientos' => ['Sin asignar' => $seguimientosPorEstado['sin_asignar'], 'Activos' => $seguimientosPorEstado['activo'], 'Cerrados' => $seguimientosPorEstado['cerrado']],
                        'Incidencias' => ['Pendientes' => $incidenciasPorEstado['pendiente'], 'En proceso' => $incidenciasPorEstado['en_proceso'], 'Atendidas' => $incidenciasPorEstado['atendida']],
                        'Voluntariado' => ['Voluntarios activos' => $voluntariosPorEstado['activo'], 'Voluntarios inactivos' => $voluntariosPorEstado['inactivo'], 'Postulaciones pendientes' => $postulacionesPorEstado['pendiente'], 'Postulaciones aprobadas' => $postulacionesPorEstado['aprobada'], 'Postulaciones rechazadas' => $postulacionesPorEstado['rechazada'], 'Postulaciones canceladas' => $postulacionesPorEstado['cancelada']],
                    ] as $grupo => $filas)
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-700">{{ $grupo }}</h3>
                            <dl class="mt-2 space-y-1 text-sm">
                                @foreach ($filas as $etiqueta => $valor)
                                    <div class="flex justify-between gap-3"><dt class="text-slate-600">{{ $etiqueta }}</dt><dd class="font-semibold text-blue-950">{{ $valor }}</dd></div>
                                @endforeach
                            </dl>
                        </div>
                    @endforeach
                </div>
            </section>

            <p class="pb-4 text-xs text-slate-500">Generado el {{ now()->format('d/m/Y H:i') }} (hora de Bolivia).</p>
        </div>
    </div>
</x-app-layout>
