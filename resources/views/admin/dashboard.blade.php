{{--
    Panel principal del administrador: «¿Qué tengo que hacer hoy?».
    Todos los datos llegan preparados por App\Services\PanelAdministrador ($panel); la vista no consulta la base.
    Orden: saludo, cuatro indicadores, «Necesitan tu atención» (avisos urgentes y, debajo, cada caso por pestañas) y la
    agenda de la semana. La actividad completa está en Auditoría.
--}}
@php
    $encabezado = $panel['encabezado'];
    $indicadores = $panel['indicadores'];
    $bandeja = $panel['bandeja'];
    $agenda = $panel['agenda'];
    // Mismos avisos que la campana de la barra superior.
    $avisos = \App\Services\PanelAdministrador::ordenarAvisos($panel['avisos'], $indicadores['incidenciasAbiertas']);

    $hoy = now()->startOfDay();
    $diasDesde = fn ($fecha) => (int) abs($fecha->copy()->startOfDay()->diffInDays($hoy));
    $cantidad = fn (int $n, string $singular, string $plural) => $n.' '.($n === 1 ? $singular : $plural);
    $esperando = fn (int $dias) => $dias === 0 ? 'Recibida hoy' : 'Esperando '.($dias === 1 ? '1 día' : "{$dias} días");
    $fechaCorta = fn ($fecha) => $fecha->translatedFormat('j M');
    $nombre = fn ($persona) => $persona ? trim($persona->nombres.' '.$persona->apellidos) : 'Persona no disponible';
    $avatar = fn ($mascota) => ($mascota?->especie === 'gato') ? '🐱' : '🐶';

    $umbralSolicitud = (int) config('pol.panel.solicitud_dias_alerta');
    $umbralSeguimiento = (int) config('pol.panel.seguimiento_sin_asignar_dias');
    $umbralPostulacion = (int) config('pol.panel.postulacion_dias_alerta');

    $totalSolicitudes = $bandeja['solicitudesPendientes']['total'] + $bandeja['solicitudesEnEvaluacion']['total'];
    $pestanas = [
        'solicitudes' => ['Solicitudes', $totalSolicitudes],
        'seguimientos' => ['Seguimientos', $bandeja['seguimientosSinAsignar']['total']],
        'incidencias' => ['Incidencias', $bandeja['incidenciasAbiertas']['total']],
        'postulaciones' => ['Postulaciones', $bandeja['postulacionesPendientes']['total']],
    ];
    $tiposIncidencia = ['salud' => 'Salud', 'maltrato' => 'Posible maltrato', 'perdida_contacto' => 'Pérdida de contacto', 'otro' => 'Otra incidencia'];

    // Indicadores: [título, número, detalle, color del detalle, enlace, icono, colores del icono]
    $tarjetas = [
        ['Solicitudes pendientes', $indicadores['solicitudesPendientes'],
            $indicadores['solicitudesUrgentes'] > 0 ? $cantidad($indicadores['solicitudesUrgentes'], 'urgente', 'urgentes') : 'Ninguna urgente',
            $indicadores['solicitudesUrgentes'] > 0 ? 'text-red-700' : 'text-slate-500',
            route('admin.solicitudes-adopcion.index', ['estado' => 'pendiente']),
            'M9 12h6M9 16h6M8.25 3.75h7.5l3.75 3.75v12a.75.75 0 0 1-.75.75H5.25a.75.75 0 0 1-.75-.75V4.5a.75.75 0 0 1 .75-.75h3Z', 'bg-blue-50 text-blue-700'],
        ['Seguimientos sin voluntario', $indicadores['seguimientosSinAsignar'],
            $indicadores['seguimientosSinAsignar'] > 0 ? 'Asigna un voluntario' : 'Todos tienen voluntario',
            $indicadores['seguimientosSinAsignar'] > 0 ? 'text-orange-700' : 'text-slate-500',
            route('admin.seguimientos.index', ['estado' => 'sin_asignar']),
            'M8.25 3v2.25M15.75 3v2.25M3.75 9h16.5M5.25 5.25h13.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5ZM9 14.25l2.25 2.25L15 12.75', 'bg-indigo-50 text-indigo-700'],
        ['Incidencias abiertas', $indicadores['incidenciasAbiertas'],
            $indicadores['incidenciasAbiertas'] > 0 ? 'Por atender en seguimientos' : 'Ninguna por atender',
            $indicadores['incidenciasAbiertas'] > 0 ? 'text-red-700' : 'text-slate-500',
            route('admin.seguimientos.index', ['estado' => 'activo']),
            'M12 9v3.75m0 3.75h.008M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z', 'bg-red-50 text-red-700'],
        ['Postulaciones por revisar', $indicadores['postulacionesPendientes'], 'Voluntariado', 'text-slate-500',
            route('admin.postulaciones-voluntariado.index', ['estado' => 'pendiente']),
            'M9 4.5H7.5A1.5 1.5 0 0 0 6 6v13.5A1.5 1.5 0 0 0 7.5 21h9a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H15M9 4.5A1.5 1.5 0 0 0 10.5 6h3A1.5 1.5 0 0 0 15 4.5M9 4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5M9.75 13.5l1.5 1.5 3-3', 'bg-amber-50 text-amber-700'],
    ];
    $prioridades = ['alta' => ['Prioridad alta', 'rojo', 'bg-red-500'], 'media' => ['Prioridad media', 'ambar', 'bg-orange-400'], 'baja' => ['Prioridad baja', 'azul', 'bg-sky-400']];

    $boton = 'inline-flex min-h-10 shrink-0 items-center justify-center rounded-full bg-blue-950 px-5 text-sm font-semibold text-white hover:bg-blue-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2';
    $enlace = 'inline-flex items-center gap-1 rounded text-sm font-semibold text-blue-800 hover:text-blue-950 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400';
    $grupo = 'border-y border-slate-100 bg-slate-50 px-5 py-2 text-sm font-semibold text-slate-700 sm:px-6';
    $fila = 'flex gap-3 px-5 py-4 sm:px-6';
    $racimo = 'mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 md:mt-0 md:justify-end lg:mt-2 lg:justify-start xl:mt-0 xl:justify-end';
    $contenidoFila = 'min-w-0 flex-1 md:flex md:items-center md:gap-4 lg:block xl:flex';
    $insignia = 'inline-flex whitespace-nowrap rounded-full px-3 py-1 text-[13px] font-semibold leading-none ring-1 ring-inset';
@endphp

<x-app-layout>
    <div class="min-h-[calc(100vh-4rem)] bg-slate-50">
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-10">

            {{-- Saludo --}}
            <header class="relative overflow-hidden rounded-3xl border border-sky-100 bg-gradient-to-r from-sky-50 via-white to-amber-50/60 px-6 py-6 shadow-sm shadow-slate-200/60 sm:px-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <p class="flex items-center gap-2 text-[15px] font-medium text-slate-700">
                            <svg class="h-5 w-5 text-blue-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v2.25M15.75 3v2.25M3.75 9h16.5M5.25 5.25h13.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5Z" /></svg>
                            {{ ucfirst($encabezado['fecha']) }}
                        </p>
                        <h1 class="mt-2 text-[30px] font-bold leading-tight text-blue-950 sm:text-4xl">
                            {{ $encabezado['saludo'] }}@if ($encabezado['nombre'] !== ''), {{ $encabezado['nombre'] }}@endif
                        </h1>
                        <p class="mt-2 text-base text-slate-600"><span class="font-semibold text-slate-800">Panel principal</span> · Esto es lo que necesita tu atención hoy.</p>
                    </div>

                    <div class="flex items-center gap-6">
                        @include('admin.partials.ilustracion-mascotas', ['clase' => 'hidden h-28 w-auto shrink-0 md:block'])
                        <a href="{{ route('admin.mascotas.create') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-amber-400 px-6 text-[15px] font-semibold text-blue-950 shadow-sm hover:bg-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-950 focus-visible:ring-offset-2">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                            Registrar mascota
                        </a>
                    </div>
                </div>
            </header>

            {{-- Alarma: una incidencia es urgente (la mascota puede estar en peligro), va antes que todo lo demás. --}}
            @if ($indicadores['incidenciasAbiertas'] > 0)
                @php $primeraIncidencia = $bandeja['incidenciasAbiertas']['items']->first(); @endphp
                <div class="flex flex-col gap-4 rounded-2xl border-2 border-red-600 bg-red-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between" role="alert">
                    <div class="flex items-center gap-3">
                        <span class="relative flex h-4 w-4 shrink-0" aria-hidden="true"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75"></span><span class="relative inline-flex h-4 w-4 rounded-full bg-red-600"></span></span>
                        <div>
                            <p class="text-lg font-bold text-red-800">🚨 {{ $indicadores['incidenciasAbiertas'] === 1 ? 'Hay 1 incidencia urgente' : 'Hay '.$indicadores['incidenciasAbiertas'].' incidencias urgentes' }}</p>
                            <p class="text-sm text-red-900">Un voluntario reportó un problema en un seguimiento. Atiéndela cuanto antes: la mascota puede estar en peligro.</p>
                        </div>
                    </div>
                    {{-- Con una sola incidencia abre su seguimiento; con varias, la pestaña «Incidencias» de abajo. --}}
                    @if ($primeraIncidencia && $indicadores['incidenciasAbiertas'] === 1)
                        <a href="{{ route('admin.seguimientos.show', $primeraIncidencia->seguimiento_id) }}"
                    @else
                        <a href="{{ route('admin.seguimientos.index', ['estado' => 'activo']) }}" x-on:click.prevent="$dispatch('abrir-pestana', 'incidencias'); document.getElementById('titulo-atencion').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                    @endif
                       class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-full bg-red-600 px-5 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:ring-offset-2">Atender ahora</a>
                </div>
            @endif

            {{-- Indicadores --}}
            <section aria-labelledby="titulo-indicadores">
                <h2 id="titulo-indicadores" class="sr-only">Indicadores principales</h2>
                <ul class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                    @foreach ($tarjetas as [$titulo, $valor, $detalle, $tonoDetalle, $url, $icono, $tonoIcono])
                        @php $pestanaDeTarjeta = ['solicitudes', 'seguimientos', 'incidencias', 'postulaciones'][$loop->index] ?? 'solicitudes'; @endphp
                        <li>
                            {{-- Abre su pestaña en «Necesitan tu atención» (sin JavaScript, el enlace lleva a la lista). --}}
                            <a href="{{ $url }}" x-on:click.prevent="$dispatch('abrir-pestana', '{{ $pestanaDeTarjeta }}'); document.getElementById('titulo-atencion').scrollIntoView({ behavior: 'smooth', block: 'start' })" class="group flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60 transition hover:border-blue-200 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 sm:p-5">
                                <div class="flex items-center gap-3">
                                    <span class="hidden h-12 w-12 shrink-0 items-center justify-center rounded-full sm:flex {{ $tonoIcono }}" aria-hidden="true">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icono }}" /></svg>
                                    </span>
                                    <p class="text-[15px] font-semibold leading-snug text-slate-800 sm:text-base">{{ $titulo }}</p>
                                </div>
                                <p class="mt-3 text-3xl font-bold leading-none text-blue-950 sm:mt-4 sm:text-4xl">{{ $valor }}</p>
                                <p class="mt-3 flex items-center justify-between gap-2 text-sm font-medium {{ $tonoDetalle }}">
                                    <span>{{ $detalle }}</span>
                                    <span class="text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-blue-900" aria-hidden="true">→</span>
                                </p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                {{-- Necesitan tu atención: avisos urgentes arriba y cada caso por pestañas --}}
                <section aria-labelledby="titulo-atencion" class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60 lg:col-span-2 lg:self-start"
                         x-on:abrir-pestana.window="pestana = $event.detail"
                         x-data="{
                             pestana: 'solicitudes',
                             orden: ['solicitudes', 'seguimientos', 'incidencias', 'postulaciones'],
                             mover(paso) {
                                 const i = (this.orden.indexOf(this.pestana) + paso + this.orden.length) % this.orden.length;
                                 this.pestana = this.orden[i];
                                 this.$nextTick(() => this.$refs['pestana-' + this.pestana].focus());
                             },
                         }">
                    <div class="flex items-center gap-3 px-5 pt-5 sm:px-6">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-800" aria-hidden="true">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="8.25" /><circle cx="12" cy="12" r="4.5" /><circle cx="12" cy="12" r="1" fill="currentColor" /></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 id="titulo-atencion" class="text-xl font-semibold text-blue-950">Necesitan tu atención</h2>
                            <p class="text-sm text-slate-600">Lo urgente primero y, debajo, cada caso pendiente.</p>
                        </div>
                    </div>

                    {{-- Avisos: solo los que tienen casos --}}
                    @if ($avisos->isNotEmpty())
                        <ul class="mx-5 mt-4 divide-y divide-amber-200/70 rounded-xl border border-amber-200 bg-amber-50/70 sm:mx-6" aria-label="Avisos">
                            @foreach ($avisos as $aviso)
                                @php [$textoPrioridad, $tono, $punto] = $prioridades[$aviso['prioridad']]; @endphp
                                <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:gap-4">
                                    <div class="flex min-w-0 flex-1 items-start gap-3">
                                        <span class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full {{ $punto }}" aria-hidden="true"></span>
                                        <div class="min-w-0">
                                            <p class="text-[15px] text-slate-900"><span class="font-semibold">{{ $aviso['titulo'] }} ({{ $aviso['total'] }})</span> <span class="sr-only">{{ $textoPrioridad }}.</span></p>
                                            <p class="text-sm text-slate-600">{{ $aviso['descripcion'] }}</p>
                                        </div>
                                    </div>
                                    <a href="{{ $aviso['url'] }}" class="{{ $enlace }} shrink-0 ps-5 sm:ps-0">{{ $aviso['accion'] }} <span aria-hidden="true">→</span></a>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Pestañas --}}
                    <div role="tablist" aria-label="Casos pendientes" class="mt-4 flex gap-2 overflow-x-auto px-5 pb-4 sm:px-6"
                         @keydown.right.prevent="mover(1)" @keydown.left.prevent="mover(-1)">
                        @foreach ($pestanas as $clave => [$titulo, $total])
                            <button type="button" role="tab" id="pestana-{{ $clave }}" aria-controls="panel-{{ $clave }}" x-ref="pestana-{{ $clave }}"
                                    @click="pestana = '{{ $clave }}'"
                                    :aria-selected="pestana === '{{ $clave }}' ? 'true' : 'false'"
                                    :tabindex="pestana === '{{ $clave }}' ? 0 : -1"
                                    aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}"
                                    :class="pestana === '{{ $clave }}' ? 'bg-blue-950 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                    class="{{ $loop->first ? 'bg-blue-950 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }} inline-flex min-h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-full px-4 text-[15px] font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                                {{ $titulo }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Solicitudes --}}
                    <div role="tabpanel" id="panel-solicitudes" aria-labelledby="pestana-solicitudes" x-show="pestana === 'solicitudes'">
                        <h3 class="sr-only">Solicitudes de adopción por revisar</h3>
                        @if ($totalSolicitudes === 0)
                            <p class="border-t border-slate-100 px-6 py-10 text-center text-[15px] text-slate-600">No hay solicitudes pendientes ni en evaluación. Todo está al día.</p>
                        @else
                            @if ($bandeja['solicitudesPendientes']['total'] > 0)
                                <h4 class="{{ $grupo }}">Pendientes de decisión</h4>
                                <ul class="divide-y divide-slate-100">
                                    @foreach ($bandeja['solicitudesPendientes']['items'] as $solicitud)
                                        @php $dias = $diasDesde($solicitud->created_at); @endphp
                                        <li class="{{ $fila }}">
                                            <span aria-hidden="true" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-xl">{{ $avatar($solicitud->mascota) }}</span>
                                            <div class="{{ $contenidoFila }}">
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-[15px] text-slate-800"><span class="font-semibold text-blue-950">{{ $solicitud->mascota?->nombre ?? 'Mascota no disponible' }}</span> · Solicitud de {{ $nombre($solicitud->solicitante) }}</p>
                                                    <p class="mt-0.5 text-sm text-slate-500">Recibida el {{ $fechaCorta($solicitud->created_at) }} · {{ $solicitud->zona_aproximada }}</p>
                                                </div>
                                                <div class="{{ $racimo }}">
                                                    <x-nivel-alerta :nivel="$solicitud->nivel_alerta" />
                                                    <span class="text-sm font-medium {{ $dias > $umbralSolicitud ? 'text-red-700' : 'text-slate-500' }}">{{ $esperando($dias) }}</span>
                                                    <a href="{{ route('admin.solicitudes-adopcion.show', $solicitud) }}" class="{{ $boton }} ms-auto md:ms-0 lg:ms-auto xl:ms-0">Revisar<span class="sr-only"> la solicitud de {{ $solicitud->mascota?->nombre }}</span></a>
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($bandeja['solicitudesEnEvaluacion']['total'] > 0)
                                <h4 class="{{ $grupo }}">En evaluación</h4>
                                <ul class="divide-y divide-slate-100">
                                    @foreach ($bandeja['solicitudesEnEvaluacion']['items'] as $solicitud)
                                        @php $dias = $diasDesde($solicitud->created_at); @endphp
                                        <li class="{{ $fila }}">
                                            <span aria-hidden="true" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-xl">{{ $avatar($solicitud->mascota) }}</span>
                                            <div class="{{ $contenidoFila }}">
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-[15px] text-slate-800"><span class="font-semibold text-blue-950">{{ $solicitud->mascota?->nombre ?? 'Mascota no disponible' }}</span> · Solicitud de {{ $nombre($solicitud->solicitante) }}</p>
                                                    <p class="mt-0.5 text-sm text-slate-500">Recibida el {{ $fechaCorta($solicitud->created_at) }} · {{ $solicitud->zona_aproximada }}</p>
                                                </div>
                                                <div class="{{ $racimo }}">
                                                    <span class="{{ $insignia }} bg-indigo-50 text-indigo-800 ring-indigo-200">En evaluación</span>
                                                    <span class="text-sm text-slate-500">{{ $dias === 0 ? 'Recibida hoy' : 'Recibida hace '.$cantidad($dias, 'día', 'días') }}</span>
                                                    <a href="{{ route('admin.solicitudes-adopcion.show', $solicitud) }}" class="{{ $boton }} ms-auto md:ms-0 lg:ms-auto xl:ms-0">Revisar<span class="sr-only"> la solicitud de {{ $solicitud->mascota?->nombre }}</span></a>
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="flex flex-col items-end gap-1 border-t border-slate-100 px-5 py-4 sm:flex-row sm:justify-end sm:gap-6 sm:px-6">
                                @if ($bandeja['solicitudesPendientes']['total'] > 0)
                                    <a href="{{ route('admin.solicitudes-adopcion.index', ['estado' => 'pendiente']) }}" class="{{ $enlace }}">Ver pendientes ({{ $bandeja['solicitudesPendientes']['total'] }}) <span aria-hidden="true">→</span></a>
                                @endif
                                @if ($bandeja['solicitudesEnEvaluacion']['total'] > 0)
                                    <a href="{{ route('admin.solicitudes-adopcion.index', ['estado' => 'en_espera']) }}" class="{{ $enlace }}">Ver en evaluación ({{ $bandeja['solicitudesEnEvaluacion']['total'] }}) <span aria-hidden="true">→</span></a>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Seguimientos sin voluntario --}}
                    <div role="tabpanel" id="panel-seguimientos" aria-labelledby="pestana-seguimientos" x-show="pestana === 'seguimientos'" x-cloak>
                        <h3 class="sr-only">Seguimientos sin voluntario asignado</h3>
                        @forelse ($bandeja['seguimientosSinAsignar']['items'] as $seguimiento)
                            @if ($loop->first)
                                <h4 class="{{ $grupo }}">Sin voluntario asignado</h4>
                                <ul class="divide-y divide-slate-100">
                            @endif
                            @php $dias = $diasDesde($seguimiento->created_at); @endphp
                            <li class="{{ $fila }}">
                                <span aria-hidden="true" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-xl">{{ $avatar($seguimiento->solicitud?->mascota) }}</span>
                                <div class="{{ $contenidoFila }}">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[15px] text-slate-800"><span class="font-semibold text-blue-950">{{ $seguimiento->solicitud?->mascota?->nombre ?? 'Mascota no disponible' }}</span> · Adopción de {{ $nombre($seguimiento->solicitud?->solicitante) }}</p>
                                        <p class="mt-0.5 text-sm text-slate-500">Creado el {{ $fechaCorta($seguimiento->created_at) }} · {{ $seguimiento->zona_aproximada }}</p>
                                    </div>
                                    <div class="{{ $racimo }}">
                                        <span class="{{ $insignia }} bg-amber-50 text-amber-800 ring-amber-200">Sin asignar</span>
                                        <span class="text-sm font-medium {{ $dias > $umbralSeguimiento ? 'text-red-700' : 'text-slate-500' }}">{{ $dias === 0 ? 'Desde hoy' : 'Hace '.$cantidad($dias, 'día', 'días') }}</span>
                                        <a href="{{ route('admin.seguimientos.show', $seguimiento) }}" class="{{ $boton }} ms-auto md:ms-0 lg:ms-auto xl:ms-0">Asignar<span class="sr-only"> voluntario al seguimiento de {{ $seguimiento->solicitud?->mascota?->nombre }}</span></a>
                                    </div>
                                </div>
                            </li>
                            @if ($loop->last)
                                </ul>
                            @endif
                        @empty
                            <p class="border-t border-slate-100 px-6 py-10 text-center text-[15px] text-slate-600">Todos los seguimientos tienen un voluntario asignado.</p>
                        @endforelse
                        <div class="flex justify-end border-t border-slate-100 px-5 py-4 sm:px-6">
                            <a href="{{ route('admin.seguimientos.index') }}" class="{{ $enlace }}">Ver todos los seguimientos <span aria-hidden="true">→</span></a>
                        </div>
                    </div>

                    {{-- Incidencias abiertas --}}
                    <div role="tabpanel" id="panel-incidencias" aria-labelledby="pestana-incidencias" x-show="pestana === 'incidencias'" x-cloak>
                        <h3 class="sr-only">Incidencias abiertas</h3>
                        @forelse ($bandeja['incidenciasAbiertas']['items'] as $incidencia)
                            @if ($loop->first)
                                <ul class="divide-y divide-slate-100 border-t border-slate-100">
                            @endif
                            @php $dias = $diasDesde($incidencia->created_at); @endphp
                            <li class="{{ $fila }}">
                                <span aria-hidden="true" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-xl">⚠️</span>
                                <div class="{{ $contenidoFila }}">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[15px] text-slate-800"><span class="font-semibold text-blue-950">{{ $tiposIncidencia[$incidencia->tipo] ?? 'Incidencia' }}</span> · {{ $incidencia->seguimiento?->solicitud?->mascota?->nombre ?? 'Mascota no disponible' }}</p>
                                        <p class="mt-0.5 text-sm text-slate-500">Reportada por {{ $nombre($incidencia->reportadoPor) }} el {{ $fechaCorta($incidencia->created_at) }}</p>
                                    </div>
                                    <div class="{{ $racimo }}">
                                        @if ($incidencia->estado === 'pendiente')
                                            <span class="{{ $insignia }} bg-red-50 text-red-800 ring-red-200">Pendiente</span>
                                        @else
                                            <span class="{{ $insignia }} bg-amber-50 text-amber-800 ring-amber-200">En proceso</span>
                                        @endif
                                        <span class="text-sm text-slate-500">{{ $dias === 0 ? 'Hoy' : 'Hace '.$cantidad($dias, 'día', 'días') }}</span>
                                        <a href="{{ route('admin.seguimientos.show', $incidencia->seguimiento_id) }}" class="{{ $boton }} ms-auto md:ms-0 lg:ms-auto xl:ms-0">Abrir seguimiento</a>
                                    </div>
                                </div>
                            </li>
                            @if ($loop->last)
                                </ul>
                            @endif
                        @empty
                            <p class="border-t border-slate-100 px-6 py-10 text-center text-[15px] text-slate-600">No hay incidencias abiertas.</p>
                        @endforelse
                        <div class="flex flex-col items-end gap-1 border-t border-slate-100 px-5 py-4 text-right sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:text-left">
                            <p class="text-sm text-slate-500">Las incidencias se atienden desde el seguimiento de cada adopción.</p>
                            <a href="{{ route('admin.seguimientos.index') }}" class="{{ $enlace }}">Ver seguimientos <span aria-hidden="true">→</span></a>
                        </div>
                    </div>

                    {{-- Postulaciones pendientes --}}
                    <div role="tabpanel" id="panel-postulaciones" aria-labelledby="pestana-postulaciones" x-show="pestana === 'postulaciones'" x-cloak>
                        <h3 class="sr-only">Postulaciones de voluntariado pendientes</h3>
                        @forelse ($bandeja['postulacionesPendientes']['items'] as $postulacion)
                            @if ($loop->first)
                                <ul class="divide-y divide-slate-100 border-t border-slate-100">
                            @endif
                            @php $dias = $diasDesde($postulacion->created_at); @endphp
                            <li class="{{ $fila }}">
                                <span aria-hidden="true" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-xl">🙋</span>
                                <div class="{{ $contenidoFila }}">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[15px] text-slate-800"><span class="font-semibold text-blue-950">{{ $nombre($postulacion->usuario) }}</span> · Postulación de voluntariado</p>
                                        <p class="mt-0.5 text-sm text-slate-500">Recibida el {{ $fechaCorta($postulacion->created_at) }} · Zona {{ $postulacion->zona_preferencia }}</p>
                                    </div>
                                    <div class="{{ $racimo }}">
                                        <span class="text-sm font-medium {{ $dias > $umbralPostulacion ? 'text-red-700' : 'text-slate-500' }}">{{ $esperando($dias) }}</span>
                                        <a href="{{ route('admin.postulaciones-voluntariado.show', $postulacion) }}" class="{{ $boton }} ms-auto md:ms-0 lg:ms-auto xl:ms-0">Revisar<span class="sr-only"> la postulación de {{ $nombre($postulacion->usuario) }}</span></a>
                                    </div>
                                </div>
                            </li>
                            @if ($loop->last)
                                </ul>
                            @endif
                        @empty
                            <p class="border-t border-slate-100 px-6 py-10 text-center text-[15px] text-slate-600">No hay postulaciones de voluntariado pendientes.</p>
                        @endforelse
                        @if ($bandeja['postulacionesPendientes']['total'] > 0)
                            <div class="flex justify-end border-t border-slate-100 px-5 py-4 sm:px-6">
                                <a href="{{ route('admin.postulaciones-voluntariado.index', ['estado' => 'pendiente']) }}" class="{{ $enlace }}">Ver postulaciones pendientes ({{ $bandeja['postulacionesPendientes']['total'] }}) <span aria-hidden="true">→</span></a>
                            </div>
                        @endif
                    </div>
                </section>

                {{-- Agenda de la semana --}}
                <section aria-labelledby="titulo-agenda" class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60 sm:p-6 lg:self-start">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-800" aria-hidden="true">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v2.25M15.75 3v2.25M3.75 9h16.5M5.25 5.25h13.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5Z" /></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 id="titulo-agenda" class="text-xl font-semibold text-blue-950">Agenda de la semana</h2>
                            <p class="text-sm text-slate-600">Visitas programadas de hoy al {{ \Illuminate\Support\Carbon::parse($agenda['hasta'])->translatedFormat('j \d\e F') }}</p>
                        </div>
                    </div>

                    @if ($agenda['items']->isEmpty())
                        <div class="mt-5 flex flex-col items-center rounded-xl bg-slate-50 px-4 py-8 text-center">
                            <svg class="h-10 w-10 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v2.25M15.75 3v2.25M3.75 9h16.5M5.25 5.25h13.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5Z" /></svg>
                            <p class="mt-3 text-[15px] text-slate-600">No hay visitas programadas para los próximos 7 días.</p>
                        </div>
                    @else
                        <ul class="mt-5 space-y-4">
                            @foreach ($agenda['items'] as $visita)
                                @php
                                    $fecha = $visita->fecha_programada;
                                    $mascota = $visita->seguimiento?->solicitud?->mascota;
                                @endphp
                                <li class="flex gap-3">
                                    <span class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-blue-50 text-blue-950">
                                        <span class="text-xs font-semibold uppercase leading-none">{{ str_replace('.', '', $fecha->isoFormat('ddd')) }}</span>
                                        <span class="text-lg font-bold leading-tight">{{ $fecha->format('j') }}</span>
                                        <span class="sr-only">{{ $fecha->translatedFormat('l j \d\e F') }}</span>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[15px]">
                                            <a href="{{ route('admin.seguimientos.show', $visita->seguimiento_id) }}" class="font-semibold text-blue-950 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">Visita a {{ $mascota?->nombre ?? 'mascota no disponible' }}</a>
                                            @if ($fecha->isToday())
                                                <span class="ms-1 {{ $insignia }} bg-amber-50 text-amber-800 ring-amber-200">Hoy</span>
                                            @elseif ($fecha->isTomorrow())
                                                <span class="ms-1 {{ $insignia }} bg-slate-100 text-slate-700 ring-slate-200">Mañana</span>
                                            @endif
                                        </p>
                                        <p class="text-sm text-slate-600">Adoptante: {{ $nombre($visita->seguimiento?->solicitud?->solicitante) }}</p>
                                        <p class="text-sm text-slate-600">A cargo de {{ $nombre($visita->voluntario) }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        @if ($agenda['total'] > $agenda['items']->count())
                            <p class="mt-4 text-sm text-slate-600">Y {{ $cantidad($agenda['total'] - $agenda['items']->count(), 'visita más', 'visitas más') }} en estos días.</p>
                        @endif
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
