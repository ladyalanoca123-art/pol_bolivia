@php
    $fecha = fn (string $f) => \Illuminate\Support\Carbon::parse($f)->format('d/m/Y');
    $tiposIncidencia = ['salud' => 'Salud', 'maltrato' => 'Posible maltrato', 'perdida_contacto' => 'Pérdida de contacto', 'otro' => 'Otro'];
    // Íconos de cada tipo de incidencia (trazo de 24 × 24).
    $iconosIncidencia = [
        'salud' => 'M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z',
        'maltrato' => 'M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6l-8-3Zm0 6v4m0 3h.01',
        'perdida_contacto' => 'M3 3l18 18M8.5 4.5 10 8l-2 1.5a11 11 0 0 0 6.5 6.5L16 14l3.5 1.5V19a2 2 0 0 1-2 2A16 16 0 0 1 3 6.5a2 2 0 0 1 2-2h3.5Z',
        'otro' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-13v.01M12 11v5',
    ];
    $visitasPorOrden = $seguimiento->visitas->sortBy('created_at')->values();
    $incidenciasPendientes = $seguimiento->incidencias->where('estado', '<>', 'atendida')->count();
    $solicitud = $seguimiento->solicitud;
    $fotoMascota = $solicitud->mascota->fotos->firstWhere('es_principal', true) ?? $solicitud->mascota->fotos->first();
    $inicial = fn (string $nombre) => mb_strtoupper(mb_substr($nombre, 0, 1));

    // Etapas de la franja (versión compacta): título con la fecha y una línea de estado.
    // «Pasada» = ya no queda nada por hacer en ella. Los márgenes van plegados debajo.
    $etapas = [[
        'titulo' => 'Entrega',
        'estado' => $solicitud->entregada_at ? $solicitud->entregada_at->timezone('America/La_Paz')->format('d/m/Y') : 'Sin registrar',
        'pasada' => (bool) $solicitud->entregada_at,
        'ok' => (bool) $solicitud->entregada_at,
    ]];
    foreach ($fechasSugeridas as $numero => $fechas) {
        $visita = $visitasPorOrden->get($numero - 1);
        $aviso = null;
        if (! $visita) {
            $estado = 'Sin programar';
        } elseif ($visita->estado === 'programada') {
            $estado = 'Programada '.$visita->fecha_programada->format('d/m/Y');
            if (! \App\Support\FechasSeguimientoAdopcion::dentroDelMargen($visita->fecha_programada->toDateString(), $fechas)) {
                $aviso = 'Fuera del margen';
            }
        } else {
            $estado = ($visita->estado === 'realizada' ? 'Realizada' : 'No asistió').($visita->fecha_realizada ? ' '.$visita->fecha_realizada->format('d/m/Y') : '');
        }
        $etapas[] = [
            'titulo' => "Visita {$numero} · ".$fecha($fechas['fecha']),
            'estado' => $estado,
            'pasada' => $visita && $visita->estado !== 'programada',
            'ok' => $visita && $visita->estado === 'realizada',
            'aviso' => $aviso,
        ];
    }
    $etapas[] = [
        'titulo' => 'Cierre',
        'estado' => $seguimiento->fecha_cierre ? $seguimiento->fecha_cierre->timezone('America/La_Paz')->format('d/m/Y') : null,
        'pasada' => $seguimiento->estado === 'cerrado',
        'ok' => $seguimiento->estado === 'cerrado',
    ];
    $actual = collect($etapas)->search(fn ($etapa) => ! $etapa['pasada']);
@endphp
<x-admin.pagina :titulo="'Seguimiento de '.$solicitud->mascota->nombre" seccion="Seguimientos" :volver="route('admin.seguimientos.index')" volver-texto="Volver a seguimientos">
    <x-slot name="subtitulo">
        {{-- La mascota con su foto, el estado del seguimiento y el enlace a la solicitud. --}}
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-3">
            <a href="{{ route('admin.mascotas.show', $solicitud->mascota) }}" class="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white py-2 pe-4 ps-2 shadow-sm hover:border-blue-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                @if ($fotoMascota)
                    <img src="{{ $fotoMascota->url_miniatura }}" alt="" class="h-12 w-12 shrink-0 rounded-xl bg-slate-100 object-cover">
                @else
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xl" aria-hidden="true">{{ $solicitud->mascota->especie === 'gato' ? '🐱' : '🐶' }}</span>
                @endif
                <span class="min-w-0">
                    <span class="block text-[13px] text-slate-500">Mascota adoptada</span>
                    <span class="block text-base font-bold text-blue-950 group-hover:underline">{{ $solicitud->mascota->nombre }}</span>
                </span>
            </a>
            <div class="flex flex-wrap items-center gap-3">
                <x-admin.estado de="seguimiento" :valor="$seguimiento->estado" />
                <a href="{{ route('admin.solicitudes-adopcion.show', $solicitud) }}" class="admin-boton-borde">Ver la solicitud de adopción</a>
            </div>
        </div>
    </x-slot>

    {{-- Etapas: franja azul compacta (✓ verde hecha, ámbar actual, naranja si no asistió, tenue pendiente) --}}
    <section class="rounded-2xl bg-gradient-to-r from-blue-950 to-blue-900 px-4 py-4 shadow-sm sm:px-6">
        <h2 class="sr-only">Etapas del seguimiento</h2>
        <ol class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Etapas del seguimiento">
            @foreach ($etapas as $i => $etapa)
                @php
                    $esActual = $i === $actual;
                    $circulo = match (true) {
                        $etapa['ok'] => 'bg-emerald-400 text-blue-950',
                        $etapa['pasada'] => 'bg-orange-300 text-blue-950',
                        $esActual => 'bg-amber-400 text-blue-950 ring-4 ring-amber-400/30',
                        default => 'border-2 border-white/30 text-blue-200',
                    };
                @endphp
                <li class="flex items-start gap-3" @if ($esActual) aria-current="step" @endif>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $circulo }}" aria-hidden="true">
                        @if ($etapa['ok'])
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                        @elseif ($etapa['pasada'])
                            !
                        @else
                            {{ $i + 1 }}
                        @endif
                    </span>
                    <span class="min-w-0">
                        <span class="block text-[15px] font-semibold leading-tight {{ $etapa['pasada'] || $esActual ? 'text-white' : 'text-blue-200' }}">{{ $etapa['titulo'] }}<span class="sr-only">{{ $etapa['ok'] ? ' (hecha)' : ($etapa['pasada'] ? ' (no asistió)' : ($esActual ? ' (etapa actual)' : ' (pendiente)')) }}</span></span>
                        @if ($etapa['estado'])<span class="mt-0.5 block text-sm {{ $esActual ? 'text-amber-200' : 'text-blue-200' }}">{{ $etapa['estado'] }}</span>@endif
                        @if (! empty($etapa['aviso']))<span class="mt-1 inline-block rounded-full bg-amber-400 px-2 py-0.5 text-[13px] font-semibold text-blue-950">{{ $etapa['aviso'] }}</span>@endif
                    </span>
                </li>
            @endforeach
        </ol>
        @if ($fechasSugeridas)
            {{-- Márgenes plegados: se abren solo si se necesitan. --}}
            <details class="group mt-3 border-t border-white/10 pt-2">
                <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded text-sm font-semibold text-blue-200 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                    Ver márgenes recomendados
                    <svg class="h-4 w-4 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4Z" clip-rule="evenodd"/></svg>
                </summary>
                <ul class="mt-2 space-y-1 text-sm text-blue-100">
                    @foreach ($fechasSugeridas as $numero => $fechas)
                        <li>Visita {{ $numero }}: objetivo a los {{ $fechas['dias'] }} días ({{ $fecha($fechas['fecha']) }}) · margen recomendado: {{ $fecha($fechas['desde']) }}–{{ $fecha($fechas['hasta']) }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-[13px] text-blue-200">Se calculan desde la entrega real. Son sugerencias; cada visita se programa cuando coordines con la familia.</p>
            </details>
        @endif
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            {{-- Visitas: una tarjeta por visita, con el color de su estado --}}
            <x-admin.tarjeta titulo="Visitas">
                <x-slot name="acciones"><x-admin.insignia tono="gris">{{ $visitasPorOrden->count() }} {{ $visitasPorOrden->count() === 1 ? 'visita' : 'visitas' }}</x-admin.insignia></x-slot>
                @forelse ($visitasPorOrden as $visita)
                    @php
                        $borde = ['programada' => 'border-s-blue-500', 'realizada' => 'border-s-emerald-500', 'no_asistio' => 'border-s-amber-500'][$visita->estado] ?? 'border-s-slate-300';
                    @endphp
                    <article class="{{ $loop->first ? '' : 'mt-4' }} rounded-xl border border-s-4 border-slate-200 {{ $borde }} bg-white p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-[13px] font-semibold uppercase tracking-wide text-slate-500">Visita {{ $loop->iteration }}</p>
                                <p class="text-xl font-bold text-blue-950">{{ $visita->fecha_programada->format('d/m/Y') }}</p>
                                <p class="text-sm text-slate-500">Fecha programada</p>
                            </div>
                            <x-admin.estado de="visita" :valor="$visita->estado" />
                        </div>
                        @if ($visita->fecha_realizada || $visita->estado_salud)
                            <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                @if ($visita->fecha_realizada)<x-admin.dato etiqueta="Fecha realizada">{{ $visita->fecha_realizada->format('d/m/Y') }}</x-admin.dato>@endif
                                @if ($visita->estado_salud)<x-admin.dato etiqueta="Estado de salud">{{ $visita->estado_salud }}</x-admin.dato>@endif
                            </dl>
                        @endif
                        @if ($visita->observaciones)
                            <div class="mt-3 rounded-xl bg-slate-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cuestionario y observaciones:</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-slate-800">{{ $visita->observaciones }}</p>
                            </div>
                        @endif
                        @if ($visita->foto_url)<x-admin.boton variante="secundario" tamano="chico" class="mt-3" :href="route('admin.seguimientos.visitas.foto', [$seguimiento, $visita])" target="_blank" rel="noopener">Ver foto privada</x-admin.boton>@endif
                    </article>
                @empty
                    <x-admin.vacio titulo="Sin visitas registradas." texto="El voluntario asignado programa las visitas al coordinar con la familia." />
                @endforelse
            </x-admin.tarjeta>

            {{-- Incidencias: en rojo mientras haya alguna pendiente --}}
            <x-admin.tarjeta titulo="Incidencias" :tono="$incidenciasPendientes > 0 ? 'rojo' : 'blanco'">
                <x-slot name="acciones">
                    @if ($incidenciasPendientes > 0)
                        <x-admin.insignia tono="rojo">{{ $incidenciasPendientes }} {{ $incidenciasPendientes === 1 ? 'pendiente' : 'pendientes' }}</x-admin.insignia>
                    @elseif ($seguimiento->incidencias->isNotEmpty())
                        <x-admin.insignia tono="verde">Todas atendidas</x-admin.insignia>
                    @endif
                </x-slot>
                @forelse ($seguimiento->incidencias as $incidencia)
                    @php
                        $pendiente = $incidencia->estado !== 'atendida';
                        $colorIcono = $pendiente ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700';
                    @endphp
                    <article class="{{ $loop->first ? '' : 'mt-4' }} rounded-xl border p-4 {{ $pendiente ? 'border-red-200 bg-red-50/50' : 'border-slate-200 bg-white' }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $colorIcono }}" aria-hidden="true">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $iconosIncidencia[$incidencia->tipo] ?? $iconosIncidencia['otro'] }}" /></svg>
                                </span>
                                <div>
                                    <p class="text-[15px] font-semibold text-blue-950">{{ $tiposIncidencia[$incidencia->tipo] ?? ucfirst(str_replace('_', ' ', $incidencia->tipo)) }}</p>
                                    <p class="text-[13px] text-slate-500">Reportada el {{ $incidencia->created_at->timezone('America/La_Paz')->format('d/m/Y') }}</p>
                                </div>
                            </div>
                            <x-admin.estado de="incidencia" :valor="$incidencia->estado" />
                        </div>
                        <p class="mt-3 text-sm text-slate-800">{{ $incidencia->descripcion }}</p>
                        @if ($incidencia->comentarios_resolucion)<p class="mt-3 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-900"><span class="font-semibold">Resolución:</span> {{ $incidencia->comentarios_resolucion }}</p>@endif
                        <div class="mt-3 flex flex-wrap items-end gap-3">
                            @can('attend', $incidencia)
                                <form method="POST" action="{{ route('admin.seguimientos.incidencias.attend', [$seguimiento, $incidencia]) }}" class="flex min-w-0 flex-1 flex-wrap items-end gap-2">
                                    @csrf @method('PATCH')
                                    <div class="min-w-[14rem] flex-1">
                                        <label class="admin-etiqueta" for="comentarios_resolucion_{{ $incidencia->id }}">Resolución</label>
                                        <input id="comentarios_resolucion_{{ $incidencia->id }}" name="comentarios_resolucion" required class="admin-campo" value="{{ old('comentarios_resolucion') }}" placeholder="Qué se hizo para resolverla">
                                        @error('comentarios_resolucion')<p class="admin-error">{{ $message }}</p>@enderror
                                    </div>
                                    <x-admin.boton variante="exito" tamano="chico">Atender</x-admin.boton>
                                </form>
                            @endcan
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Sin incidencias.</p>
                @endforelse
            </x-admin.tarjeta>
        </div>

        <div class="columna-fija min-w-0 space-y-6 lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:self-start lg:overflow-y-auto lg:pe-1">
            {{-- 1. Asignar voluntario: arriba y en ámbar mientras falte --}}
            @can('assign', $seguimiento)
                <x-admin.tarjeta tono="ambar" titulo="Asignar voluntario" descripcion="Al asignarlo, el seguimiento se activa y el voluntario puede programar las visitas.">
                    <form method="POST" action="{{ route('admin.seguimientos.assign', $seguimiento) }}">@csrf @method('PATCH')
                        <label for="voluntario_id" class="admin-etiqueta">Voluntario</label>
                        <select id="voluntario_id" name="voluntario_id" required class="admin-campo">
                            @foreach ($voluntarios as $voluntario)<option value="{{ $voluntario->id }}" @selected(old('voluntario_id') == $voluntario->id)>{{ $voluntario->nombres }} {{ $voluntario->apellidos }}</option>@endforeach
                        </select>
                        @error('voluntario_id')<p class="admin-error">{{ $message }}</p>@enderror
                        @if ($voluntarios->isEmpty())<p class="mt-2 text-sm font-semibold text-amber-900">No hay voluntarios activos disponibles.</p>@endif
                        <x-admin.boton variante="destacado" class="mt-3 w-full">Asignar y activar</x-admin.boton>
                    </form>
                </x-admin.tarjeta>
            @endcan

            {{-- 2. Personas: familia y voluntario con sus iniciales --}}
            <x-admin.tarjeta titulo="Información del seguimiento">
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-400 text-base font-bold text-blue-950" aria-hidden="true">{{ $inicial($solicitud->solicitante->nombres) }}</span>
                        <div class="min-w-0">
                            <p class="text-[13px] text-slate-500">Familia adoptante</p>
                            <p class="font-semibold leading-snug text-blue-950">{{ $solicitud->solicitante->nombres }} {{ $solicitud->solicitante->apellidos }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        @if ($seguimiento->voluntario)
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-950 text-base font-bold text-white" aria-hidden="true">{{ $inicial($seguimiento->voluntario->nombres) }}</span>
                        @else
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-amber-400 text-base font-bold text-amber-700" aria-hidden="true">?</span>
                        @endif
                        <div class="min-w-0">
                            <p class="text-[13px] text-slate-500">Voluntario</p>
                            @if ($seguimiento->voluntario)
                                <p class="font-semibold leading-snug text-blue-950">{{ $seguimiento->voluntario->nombres }} {{ $seguimiento->voluntario->apellidos }}</p>
                            @else
                                <p class="font-semibold text-amber-800">Sin asignar</p>
                            @endif
                        </div>
                    </div>
                </div>
                <dl class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                    @if ($seguimiento->zona_aproximada)<x-admin.dato etiqueta="Zona">{{ $seguimiento->zona_aproximada }}</x-admin.dato>@endif
                    @if ($seguimiento->horario_preferido)<x-admin.dato etiqueta="Horario preferido">{{ $seguimiento->horario_preferido }}</x-admin.dato>@endif
                </dl>
            </x-admin.tarjeta>

            {{-- 3. Cierre: lista de lo que está listo (✓) y lo que falta (✗); el botón solo aparece con todo listo --}}
            @can('close', $seguimiento)
                @php
                    $pendientesCierre = $seguimiento->pendientesParaCerrar();
                    $faltanIncidencias = collect($pendientesCierre)->filter(fn ($texto) => str_starts_with($texto, 'Atiende'));
                    $faltanVisitas = collect($pendientesCierre)->reject(fn ($texto) => str_starts_with($texto, 'Atiende'));
                    $pasosCierre = [
                        ...($faltanIncidencias->isEmpty() ? [[true, 'Sin incidencias pendientes.']] : $faltanIncidencias->map(fn ($t) => [false, $t])->all()),
                        ...($faltanVisitas->isEmpty() ? [[true, 'Todas las visitas programadas están registradas.']] : $faltanVisitas->map(fn ($t) => [false, $t])->all()),
                    ];
                @endphp
                <x-admin.tarjeta titulo="Cerrar el seguimiento">
                    @error('cierre')<p class="mb-3 rounded-xl bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror
                    @if ($pendientesCierre)<p class="mb-2 text-sm font-semibold text-amber-900">Para cerrar el seguimiento:</p>@endif
                    <ul class="space-y-2">
                        @foreach ($pasosCierre as [$listo, $texto])
                            <li class="flex items-start gap-2 text-sm {{ $listo ? 'text-slate-700' : 'font-medium text-amber-950' }}">
                                @if ($listo)
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white" aria-hidden="true"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg></span>
                                @else
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-red-500 text-white" aria-hidden="true"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18" /></svg></span>
                                @endif
                                <span><span class="sr-only">{{ $listo ? 'Listo: ' : 'Falta: ' }}</span>{{ $texto }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @unless ($pendientesCierre)
                        {{-- Sin pendientes: PostgreSQL acepta el cierre. Con pendientes no hay botón; se dice qué falta. --}}
                        <form method="POST" action="{{ route('admin.seguimientos.close', $seguimiento) }}" class="mt-4">@csrf @method('PATCH')
                            <x-admin.boton variante="peligro-suave" class="w-full" x-data x-on:click="if (! confirm('¿Cerrar seguimiento?')) $event.preventDefault()">Cerrar seguimiento</x-admin.boton>
                            <p class="admin-ayuda">Al cerrarlo termina el acompañamiento de esta adopción.</p>
                        </form>
                    @endunless
                </x-admin.tarjeta>
            @endcan
        </div>
    </div>
</x-admin.pagina>
