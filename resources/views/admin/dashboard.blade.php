<x-app-layout>
    {{--
        Panel principal del administrador: «¿Qué tengo que hacer hoy?».
        Todos los datos llegan preparados por App\Services\PanelAdministrador ($panel); la vista no consulta la base.
    --}}
    @php
        $encabezado = $panel['encabezado'];
        $indicadores = $panel['indicadores'];
        $avisos = collect($panel['avisos'])->filter(fn (array $aviso) => $aviso['total'] > 0);
        $bandeja = $panel['bandeja'];
        $agenda = $panel['agenda'];
        $actividad = $panel['actividad'];

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

        // Actividad reciente: frases construidas solo con acción, modelo, actor y estado (sin valores del JSON).
        $sustantivos = [
            'Mascota' => 'una mascota',
            'SolicitudAdopcion' => 'una solicitud de adopción',
            'SeguimientoAdopcion' => 'un seguimiento de adopción',
            'VisitaSeguimiento' => 'una visita de seguimiento',
            'Incidencia' => 'una incidencia',
            'PostulacionVoluntariado' => 'una postulación de voluntariado',
        ];
        $verbos = ['creado' => 'registró', 'actualizado' => 'actualizó', 'eliminado' => 'eliminó'];
        $estados = [
            'Mascota' => ['disponible' => 'Disponible', 'adoptada' => 'Adoptada'],
            'SolicitudAdopcion' => \App\Models\SolicitudAdopcion::ESTADOS_LEGIBLES,
            'SeguimientoAdopcion' => ['sin_asignar' => 'Sin asignar', 'activo' => 'Activo', 'cerrado' => 'Cerrado'],
            'VisitaSeguimiento' => ['programada' => 'Programada', 'realizada' => 'Realizada', 'no_asistio' => 'No asistió'],
            'Incidencia' => ['pendiente' => 'Pendiente', 'en_proceso' => 'En proceso', 'atendida' => 'Atendida'],
            'PostulacionVoluntariado' => ['pendiente' => 'Pendiente', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada', 'cancelada' => 'Cancelada'],
        ];
        $estadoLegible = fn (string $modelo, string $estado) => $estados[$modelo][$estado] ?? ucfirst(str_replace('_', ' ', $estado));
        $frase = function (array $entrada) use ($sustantivos, $verbos, $estadoLegible): string {
            $sustantivo = $sustantivos[$entrada['modelo']] ?? 'un registro';
            if ($entrada['accion'] === 'actualizado' && $entrada['estado_anterior'] && $entrada['estado_nuevo'] && $entrada['estado_anterior'] !== $entrada['estado_nuevo']) {
                return "{$entrada['actor']} cambió {$sustantivo} de «{$estadoLegible($entrada['modelo'], $entrada['estado_anterior'])}» a «{$estadoLegible($entrada['modelo'], $entrada['estado_nuevo'])}»";
            }

            return $entrada['actor'].' '.($verbos[$entrada['accion']] ?? 'modificó').' '.$sustantivo;
        };
        $momento = function ($fecha): string {
            return match (true) {
                $fecha === null => 'Fecha no disponible',
                $fecha->isToday() => ucfirst($fecha->diffForHumans()),
                $fecha->isYesterday() => 'Ayer, '.$fecha->format('H:i'),
                default => $fecha->translatedFormat('j M, H:i'),
            };
        };
        $puntos = ['creado' => 'bg-blue-600', 'actualizado' => 'bg-violet-600', 'eliminado' => 'bg-slate-400'];

        $boton = 'inline-flex min-h-10 shrink-0 items-center justify-center rounded-full bg-blue-950 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2';
        $enlace = 'inline-flex items-center gap-1 rounded text-sm font-semibold text-blue-800 hover:text-blue-950 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400';
        $tarjeta = 'rounded-2xl border border-slate-200 bg-white';
        $grupo = 'border-y border-slate-100 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-600 sm:px-5';
        $fila = 'flex gap-3 px-4 py-4 sm:px-5';
        $racimo = 'mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 md:mt-0 md:justify-end lg:mt-2 lg:justify-start xl:mt-0 xl:justify-end';
        $contenidoFila = 'min-w-0 flex-1 md:flex md:items-center md:gap-4 lg:block xl:flex';
    @endphp

    <div class="min-h-[calc(100vh-4rem)] bg-slate-50">
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">

            {{-- Encabezado --}}
            <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">{{ $encabezado['fecha'] }}</p>
                    <h1 class="mt-2 text-2xl font-bold text-blue-950 sm:text-3xl">
                        {{ $encabezado['saludo'] }}@if ($encabezado['nombre'] !== ''), {{ $encabezado['nombre'] }}@endif
                    </h1>
                    <p class="mt-1 text-sm text-slate-600 sm:text-base"><span class="font-semibold text-slate-700">Panel principal</span> · Esto es lo que necesita tu atención hoy.</p>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap lg:shrink-0">
                    <a href="{{ route('admin.mascotas.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-full bg-amber-400 px-5 py-2 text-center text-sm font-semibold text-blue-950 hover:bg-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-950 focus-visible:ring-offset-2">
                        <span aria-hidden="true" class="me-1">+</span> Registrar mascota
                    </a>
                    <a href="{{ route('admin.solicitudes-adopcion.index', ['estado' => 'pendiente', 'nivel_alerta' => 'rojo']) }}" class="inline-flex min-h-11 items-center justify-center rounded-full border border-red-300 bg-white px-5 py-2 text-center text-sm font-semibold text-red-700 hover:border-red-400 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2">
                        Revisar solicitudes urgentes
                    </a>
                </div>
            </header>

            {{-- Indicadores --}}
            <section aria-labelledby="titulo-indicadores">
                <h2 id="titulo-indicadores" class="sr-only">Indicadores del día</h2>
                <dl class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    @php
                        $variacion = $indicadores['variacion'];
                        $tarjetas = [
                            ['📥', 'Solicitudes nuevas (7 días)', $indicadores['solicitudesNuevas'], null],
                            ['🗓️', 'En evaluación', $indicadores['solicitudesEnEvaluacion'], 'Entrevista o visita'],
                            ['🏡', 'Seguimientos activos', $indicadores['seguimientosActivos'], 'Familias acompañadas'],
                            ['⚠️', 'Incidencias abiertas', $indicadores['incidenciasAbiertas'], 'Pendientes o en proceso'],
                        ];
                    @endphp
                    @foreach ($tarjetas as [$icono, $etiqueta, $valor, $ayuda])
                        <div class="{{ $tarjeta }} min-w-0 p-4 sm:p-5">
                            <dt class="flex items-start gap-2 text-sm font-medium text-slate-600">
                                <span aria-hidden="true" class="hidden h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-base sm:inline-flex">{{ $icono }}</span>
                                <span class="min-w-0 pt-0.5">{{ $etiqueta }}</span>
                            </dt>
                            <dd class="mt-3 text-3xl font-bold leading-none text-blue-950">{{ $valor }}</dd>
                            <dd class="mt-3 text-xs text-slate-500">
                                @if ($ayuda)
                                    {{ $ayuda }}
                                @else
                                    @switch($variacion['tipo'])
                                        @case('aumento')
                                            <span class="font-semibold text-emerald-700"><span aria-hidden="true">▲</span><span class="sr-only">Aumento de</span> {{ $variacion['diferencia'] }}</span> vs. semana anterior
                                            @break
                                        @case('disminucion')
                                            <span class="font-semibold text-slate-700"><span aria-hidden="true">▼</span><span class="sr-only">Disminución de</span> {{ $variacion['diferencia'] }}</span> vs. semana anterior
                                            @break
                                        @case('nuevo')
                                            <span class="font-semibold text-emerald-700">Nuevo</span> · ninguna la semana anterior
                                            @break
                                        @default
                                            Igual que la semana anterior
                                    @endswitch
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            {{-- Avisos: solo los que tienen casos --}}
            @if ($avisos->isNotEmpty())
                <section aria-labelledby="titulo-avisos" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:p-5">
                    <h2 id="titulo-avisos" class="flex items-center gap-2 font-semibold text-amber-900"><span aria-hidden="true">⏰</span> Avisos</h2>
                    <ul class="mt-2 divide-y divide-amber-200/70">
                        @foreach ($avisos as $aviso)
                            <li class="flex flex-col gap-1 py-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                <p class="text-sm text-amber-950">{{ $aviso['texto'] }}</p>
                                <a href="{{ route($aviso['ruta'], $aviso['parametros']) }}" class="{{ $enlace }} shrink-0">{{ $aviso['accion'] }} <span aria-hidden="true">→</span></a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                {{-- Bandeja «Requiere tu atención» --}}
                <section aria-labelledby="titulo-bandeja" class="{{ $tarjeta }} min-w-0 overflow-hidden lg:col-span-2 lg:self-start"
                         x-data="{
                             pestana: 'solicitudes',
                             orden: ['solicitudes', 'seguimientos', 'incidencias', 'postulaciones'],
                             mover(paso) {
                                 const i = (this.orden.indexOf(this.pestana) + paso + this.orden.length) % this.orden.length;
                                 this.pestana = this.orden[i];
                                 this.$nextTick(() => this.$refs['pestana-' + this.pestana].focus());
                             },
                         }">
                    <h2 id="titulo-bandeja" class="px-4 pt-5 text-lg font-semibold text-blue-950 sm:px-5">Requiere tu atención</h2>

                    <div role="tablist" aria-label="Tareas pendientes" class="mt-3 flex gap-1 overflow-x-auto overflow-y-hidden border-b border-slate-200 px-2 sm:px-3"
                         @keydown.right.prevent="mover(1)" @keydown.left.prevent="mover(-1)">
                        @foreach ($pestanas as $clave => [$titulo, $total])
                            <button type="button" role="tab" id="pestana-{{ $clave }}" aria-controls="panel-{{ $clave }}" x-ref="pestana-{{ $clave }}"
                                    @click="pestana = '{{ $clave }}'"
                                    :aria-selected="pestana === '{{ $clave }}' ? 'true' : 'false'"
                                    :tabindex="pestana === '{{ $clave }}' ? 0 : -1"
                                    aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}"
                                    :class="{ 'border-blue-950 text-blue-950': pestana === '{{ $clave }}', 'border-transparent text-slate-500 hover:text-slate-700': pestana !== '{{ $clave }}' }"
                                    class="{{ $loop->first ? 'border-blue-950 text-blue-950' : 'border-transparent text-slate-500 hover:text-slate-700' }} -mb-px inline-flex shrink-0 items-center gap-2 whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-400">
                                {{ $titulo }}
                                <span :class="{ 'bg-blue-950 text-white': pestana === '{{ $clave }}', 'bg-slate-100 text-slate-600': pestana !== '{{ $clave }}' }"
                                      class="{{ $loop->first ? 'bg-blue-950 text-white' : 'bg-slate-100 text-slate-600' }} inline-flex min-w-5 items-center justify-center rounded-full px-1.5 text-xs font-semibold">{{ $total }}</span>
                            </button>
                        @endforeach
                    </div>

                    {{-- Solicitudes --}}
                    <div role="tabpanel" id="panel-solicitudes" aria-labelledby="pestana-solicitudes" x-show="pestana === 'solicitudes'">
                        <h3 class="sr-only">Solicitudes de adopción por revisar</h3>
                        @if ($totalSolicitudes === 0)
                            <p class="px-5 py-10 text-center text-sm text-slate-500">No hay solicitudes pendientes ni en evaluación. Todo está al día.</p>
                        @else
                            @if ($bandeja['solicitudesPendientes']['total'] > 0)
                                <h4 class="{{ $grupo }}">Pendientes de decisión</h4>
                                <ul class="divide-y divide-slate-100">
                                    @foreach ($bandeja['solicitudesPendientes']['items'] as $solicitud)
                                        @php $dias = $diasDesde($solicitud->created_at); @endphp
                                        <li class="{{ $fila }}">
                                            <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-xl">{{ $avatar($solicitud->mascota) }}</span>
                                            <div class="{{ $contenidoFila }}">
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm text-slate-800"><span class="font-semibold text-blue-950">{{ $solicitud->mascota?->nombre ?? 'Mascota no disponible' }}</span> · solicitud de {{ $nombre($solicitud->solicitante) }}</p>
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
                                            <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-xl">{{ $avatar($solicitud->mascota) }}</span>
                                            <div class="{{ $contenidoFila }}">
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm text-slate-800"><span class="font-semibold text-blue-950">{{ $solicitud->mascota?->nombre ?? 'Mascota no disponible' }}</span> · solicitud de {{ $nombre($solicitud->solicitante) }}</p>
                                                    <p class="mt-0.5 text-sm text-slate-500">Recibida el {{ $fechaCorta($solicitud->created_at) }} · {{ $solicitud->zona_aproximada }}</p>
                                                </div>
                                                <div class="{{ $racimo }}">
                                                    <span class="inline-flex whitespace-nowrap rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-800 ring-1 ring-inset ring-indigo-200">En evaluación</span>
                                                    <span class="text-sm text-slate-500">{{ $dias === 0 ? 'Recibida hoy' : 'Recibida hace '.$cantidad($dias, 'día', 'días') }}</span>
                                                    <a href="{{ route('admin.solicitudes-adopcion.show', $solicitud) }}" class="{{ $boton }} ms-auto md:ms-0 lg:ms-auto xl:ms-0">Revisar<span class="sr-only"> la solicitud de {{ $solicitud->mascota?->nombre }}</span></a>
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="flex flex-col items-end gap-1 border-t border-slate-100 px-4 py-4 sm:flex-row sm:justify-end sm:gap-6 sm:px-5">
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
                                <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-xl">{{ $avatar($seguimiento->solicitud?->mascota) }}</span>
                                <div class="{{ $contenidoFila }}">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-slate-800"><span class="font-semibold text-blue-950">{{ $seguimiento->solicitud?->mascota?->nombre ?? 'Mascota no disponible' }}</span> · adopción de {{ $nombre($seguimiento->solicitud?->solicitante) }}</p>
                                        <p class="mt-0.5 text-sm text-slate-500">Creado el {{ $fechaCorta($seguimiento->created_at) }} · {{ $seguimiento->zona_aproximada }}</p>
                                    </div>
                                    <div class="{{ $racimo }}">
                                        <span class="inline-flex whitespace-nowrap rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">Sin asignar</span>
                                        <span class="text-sm font-medium {{ $dias > $umbralSeguimiento ? 'text-red-700' : 'text-slate-500' }}">{{ $dias === 0 ? 'Desde hoy' : 'Hace '.$cantidad($dias, 'día', 'días') }}</span>
                                        <a href="{{ route('admin.seguimientos.show', $seguimiento) }}" class="{{ $boton }} ms-auto md:ms-0 lg:ms-auto xl:ms-0">Asignar<span class="sr-only"> voluntario al seguimiento de {{ $seguimiento->solicitud?->mascota?->nombre }}</span></a>
                                    </div>
                                </div>
                            </li>
                            @if ($loop->last)
                                </ul>
                            @endif
                        @empty
                            <p class="px-5 py-10 text-center text-sm text-slate-500">Todos los seguimientos tienen un voluntario asignado.</p>
                        @endforelse
                        <div class="flex justify-end border-t border-slate-100 px-4 py-4 sm:px-5">
                            <a href="{{ route('admin.seguimientos.index') }}" class="{{ $enlace }}">Ver todos los seguimientos <span aria-hidden="true">→</span></a>
                        </div>
                    </div>

                    {{-- Incidencias abiertas --}}
                    <div role="tabpanel" id="panel-incidencias" aria-labelledby="pestana-incidencias" x-show="pestana === 'incidencias'" x-cloak>
                        <h3 class="sr-only">Incidencias abiertas</h3>
                        @forelse ($bandeja['incidenciasAbiertas']['items'] as $incidencia)
                            @if ($loop->first)
                                <ul class="divide-y divide-slate-100">
                            @endif
                            @php $dias = $diasDesde($incidencia->created_at); @endphp
                            <li class="{{ $fila }}">
                                <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-xl">⚠️</span>
                                <div class="{{ $contenidoFila }}">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-slate-800"><span class="font-semibold text-blue-950">{{ $tiposIncidencia[$incidencia->tipo] ?? 'Incidencia' }}</span> · {{ $incidencia->seguimiento?->solicitud?->mascota?->nombre ?? 'Mascota no disponible' }}</p>
                                        <p class="mt-0.5 text-sm text-slate-500">Reportada por {{ $nombre($incidencia->reportadoPor) }} el {{ $fechaCorta($incidencia->created_at) }}</p>
                                    </div>
                                    <div class="{{ $racimo }}">
                                        @if ($incidencia->estado === 'pendiente')
                                            <span class="inline-flex whitespace-nowrap rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-800 ring-1 ring-inset ring-red-200">Pendiente</span>
                                        @else
                                            <span class="inline-flex whitespace-nowrap rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">En proceso</span>
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
                            <p class="px-5 py-10 text-center text-sm text-slate-500">No hay incidencias abiertas.</p>
                        @endforelse
                        <div class="flex flex-col items-end gap-1 border-t border-slate-100 px-4 py-4 text-right sm:flex-row sm:items-center sm:justify-between sm:px-5 sm:text-left">
                            <p class="text-xs text-slate-500">Las incidencias se atienden desde el seguimiento de cada adopción.</p>
                            <a href="{{ route('admin.seguimientos.index') }}" class="{{ $enlace }}">Ver seguimientos <span aria-hidden="true">→</span></a>
                        </div>
                    </div>

                    {{-- Postulaciones pendientes --}}
                    <div role="tabpanel" id="panel-postulaciones" aria-labelledby="pestana-postulaciones" x-show="pestana === 'postulaciones'" x-cloak>
                        <h3 class="sr-only">Postulaciones de voluntariado pendientes</h3>
                        @forelse ($bandeja['postulacionesPendientes']['items'] as $postulacion)
                            @if ($loop->first)
                                <ul class="divide-y divide-slate-100">
                            @endif
                            @php $dias = $diasDesde($postulacion->created_at); @endphp
                            <li class="{{ $fila }}">
                                <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-xl">🙋</span>
                                <div class="{{ $contenidoFila }}">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-slate-800"><span class="font-semibold text-blue-950">{{ $nombre($postulacion->usuario) }}</span> · postulación de voluntariado</p>
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
                            <p class="px-5 py-10 text-center text-sm text-slate-500">No hay postulaciones de voluntariado pendientes.</p>
                        @endforelse
                        @if ($bandeja['postulacionesPendientes']['total'] > 0)
                            <div class="flex justify-end border-t border-slate-100 px-4 py-4 sm:px-5">
                                <a href="{{ route('admin.postulaciones-voluntariado.index', ['estado' => 'pendiente']) }}" class="{{ $enlace }}">Ver postulaciones pendientes ({{ $bandeja['postulacionesPendientes']['total'] }}) <span aria-hidden="true">→</span></a>
                            </div>
                        @endif
                    </div>
                </section>

                <div class="min-w-0 space-y-6">
                    {{-- Agenda de la semana --}}
                    <section aria-labelledby="titulo-agenda" class="{{ $tarjeta }} p-4 sm:p-5">
                        <h2 id="titulo-agenda" class="text-lg font-semibold text-blue-950">Agenda de la semana</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Visitas programadas de hoy al {{ \Illuminate\Support\Carbon::parse($agenda['hasta'])->translatedFormat('j \d\e F') }}</p>

                        @if ($agenda['items']->isEmpty())
                            <p class="mt-4 text-sm text-slate-500">No hay visitas programadas para los próximos 7 días.</p>
                        @else
                            <ul class="mt-4 space-y-4">
                                @foreach ($agenda['items'] as $visita)
                                    @php
                                        $fecha = $visita->fecha_programada;
                                        $mascota = $visita->seguimiento?->solicitud?->mascota;
                                    @endphp
                                    <li class="flex gap-3">
                                        <span class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-blue-50 text-blue-950">
                                            <span class="text-[0.65rem] font-semibold uppercase leading-none">{{ str_replace('.', '', $fecha->isoFormat('ddd')) }}</span>
                                            <span class="text-lg font-bold leading-tight">{{ $fecha->format('j') }}</span>
                                            <span class="sr-only">{{ $fecha->translatedFormat('l j \d\e F') }}</span>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm">
                                                <a href="{{ route('admin.seguimientos.show', $visita->seguimiento_id) }}" class="font-semibold text-blue-950 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">Visita a {{ $mascota?->nombre ?? 'mascota no disponible' }}</a>
                                                @if ($fecha->isToday())
                                                    <span class="ms-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Hoy</span>
                                                @elseif ($fecha->isTomorrow())
                                                    <span class="ms-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">Mañana</span>
                                                @endif
                                            </p>
                                            <p class="text-sm text-slate-500">Adoptante: {{ $nombre($visita->seguimiento?->solicitud?->solicitante) }} · {{ $visita->seguimiento?->zona_aproximada }}</p>
                                            <p class="text-sm text-slate-500">A cargo de {{ $nombre($visita->voluntario) }} · Programada</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-4 text-sm text-slate-500">
                                @if ($agenda['total'] > $agenda['items']->count())
                                    Y {{ $cantidad($agenda['total'] - $agenda['items']->count(), 'visita más', 'visitas más') }} en estos días.
                                @else
                                    No hay más visitas en los próximos 7 días.
                                @endif
                            </p>
                        @endif
                    </section>

                    {{-- Actividad reciente --}}
                    <section aria-labelledby="titulo-actividad" class="{{ $tarjeta }} p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h2 id="titulo-actividad" class="text-lg font-semibold text-blue-950">Actividad reciente</h2>
                            <a href="{{ route('admin.auditoria.index') }}" class="{{ $enlace }} shrink-0">Ver auditoría <span aria-hidden="true">→</span></a>
                        </div>

                        @if ($actividad->isEmpty())
                            <p class="mt-4 text-sm text-slate-500">Todavía no hay actividad registrada.</p>
                        @else
                            <ul class="mt-3 divide-y divide-slate-100">
                                @foreach ($actividad as $entrada)
                                    <li class="flex gap-3 py-3">
                                        <span aria-hidden="true" class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $puntos[$entrada['accion']] ?? 'bg-slate-400' }}"></span>
                                        <div class="min-w-0">
                                            <p class="text-sm text-slate-800">{{ $frase($entrada) }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500">
                                                @if ($entrada['fecha'])
                                                    <time datetime="{{ $entrada['fecha']->toIso8601String() }}">{{ $momento($entrada['fecha']) }}</time>
                                                @else
                                                    Fecha no disponible
                                                @endif
                                            </p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>
            </div>

            {{-- Acceso a Reportes --}}
            <div class="pb-4 text-center">
                <a href="{{ route('admin.reportes.index') }}" class="{{ $enlace }}">Ver reportes y estadísticas <span aria-hidden="true">→</span></a>
            </div>
        </div>
    </div>
</x-app-layout>
