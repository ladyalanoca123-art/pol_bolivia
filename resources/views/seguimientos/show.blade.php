@php
    // La visita 2 se habilita cuando la visita 1 terminó: realizada o «No asistió».
    $terminadas = \App\Http\Controllers\SeguimientoAdopcionController::VISITA_TERMINADA;
    $visitasOrdenadas = $seguimiento->visitas->sortBy('id')->values();
    $primeraTerminada = in_array($visitasOrdenadas->first()?->estado, $terminadas, true);
    $siguienteNumeroVisita = $visitasOrdenadas->count() + 1;
    $puedeProgramarSiguiente = $visitasOrdenadas->isEmpty() || ($visitasOrdenadas->count() === 1 && $primeraTerminada);
    $fecha = fn (string $f) => \Illuminate\Support\Carbon::parse($f)->format('d/m/Y');
    $tiposIncidencia = ['salud' => 'Salud', 'maltrato' => 'Maltrato', 'perdida_contacto' => 'Pérdida de contacto', 'otro' => 'Otro'];
    $familia = $seguimiento->solicitud->solicitante;
@endphp
<x-admin.pagina :titulo="'Seguimiento: '.$seguimiento->solicitud->mascota->nombre" seccion="Mis seguimientos" :volver="route('seguimientos.index')" volver-texto="Volver a mis seguimientos">
    <x-slot name="subtitulo">
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <x-admin.estado de="seguimiento" :valor="$seguimiento->estado" />
            <span class="text-sm text-slate-500">Familia {{ $familia->nombres }} {{ $familia->apellidos }}</span>
        </div>
    </x-slot>

    {{-- Etapas: la misma franja azul compacta que ve la administración (✓ verde hecha, ámbar la que toca ahora). --}}
    @if ($fechasSugeridas)
        @php
            $etapas = [[
                'titulo' => 'Entrega',
                'estado' => $seguimiento->solicitud->entregada_at?->timezone('America/La_Paz')->format('d/m/Y') ?? 'Sin registrar',
                'pasada' => (bool) $seguimiento->solicitud->entregada_at, 'ok' => (bool) $seguimiento->solicitud->entregada_at, 'aviso' => null,
            ]];
            foreach ($fechasSugeridas as $numero => $fechas) {
                $visita = $visitasOrdenadas->get($numero - 1);
                $aviso = null;
                if (! $visita) {
                    $estado = $numero === $siguienteNumeroVisita ? 'Próxima para coordinar' : 'Se habilita al registrar la visita 1';
                } elseif ($visita->estado === 'programada') {
                    $estado = 'Programada '.$visita->fecha_programada->format('d/m/Y');
                    if (! \App\Support\FechasSeguimientoAdopcion::dentroDelMargen($visita->fecha_programada->toDateString(), $fechas)) {
                        $aviso = 'Fuera del margen recomendado';
                    }
                } else {
                    $estado = $visita->estado === 'realizada' ? 'Realizada '.$visita->fecha_realizada?->format('d/m/Y') : 'No asistió ('.$visita->fecha_programada->format('d/m/Y').')';
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
                'estado' => $seguimiento->estado === 'cerrado' ? 'Seguimiento cerrado' : 'Lo cierra la fundación',
                'pasada' => $seguimiento->estado === 'cerrado', 'ok' => $seguimiento->estado === 'cerrado', 'aviso' => null,
            ];
            $actual = collect($etapas)->search(fn ($etapa) => ! $etapa['pasada']);
        @endphp
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
                            <span class="block text-[15px] font-semibold leading-tight {{ $etapa['pasada'] || $esActual ? 'text-white' : 'text-blue-200' }}">{{ $etapa['titulo'] }}</span>
                            <span class="mt-0.5 block text-sm {{ $esActual ? 'text-amber-200' : 'text-blue-200' }}">{{ $etapa['estado'] }}</span>
                            @if ($etapa['aviso'])<span class="mt-1 inline-block rounded-full bg-amber-400 px-2 py-0.5 text-[13px] font-semibold text-blue-950">{{ $etapa['aviso'] }}</span>@endif
                        </span>
                    </li>
                @endforeach
            </ol>
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
        </section>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Visitas --}}
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @if ($seguimiento->estado === 'activo')
                @if ($puedeProgramarSiguiente && $siguienteNumeroVisita <= 2)
                    <x-admin.tarjeta :titulo="'Programar visita '.$siguienteNumeroVisita" descripcion="Coordina primero el día por WhatsApp con la familia. El rango sugerido es una guía; puedes elegir otro día y el sistema avisará si queda fuera del margen recomendado.">
                        <form method="POST" action="{{ route('seguimientos.visitas.store', $seguimiento) }}" class="flex flex-wrap items-end gap-3">@csrf
                            <div>
                                <label class="admin-etiqueta" for="fecha_programada">Fecha acordada</label>
                                <input id="fecha_programada" type="date" name="fecha_programada" value="{{ old('fecha_programada') }}" min="{{ now()->toDateString() }}" required class="admin-campo">
                            </div>
                            <x-admin.boton>Programar visita {{ $siguienteNumeroVisita }}</x-admin.boton>
                        </form>
                        @error('fecha_programada')<p class="admin-error">{{ $message }}</p>@enderror
                    </x-admin.tarjeta>
                @elseif ($visitasOrdenadas->count() === 1)
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600" role="status">
                        La visita 1 está programada. Cuando la registres como realizada (o «No asistió»), aparecerá la opción para programar la visita 2. Si cambia el día, usa «Reprogramar».
                    </div>
                @endif
            @endif

            <x-admin.tarjeta titulo="Visitas">
                @forelse ($visitasOrdenadas as $visita)
                    @php
                        $numeroVisita = $loop->iteration;
                        $preguntasVisita = \App\Support\CuestionarioVisitaSeguimiento::preguntas($numeroVisita);
                        $puedeCompletarVisita = $numeroVisita === 1 || ($numeroVisita === 2 && $primeraTerminada);
                    @endphp
                    {{-- La visita ya terminada queda plegada en una línea; la que falta completar, abierta. --}}
                    @php $visitaTerminada = in_array($visita->estado, $terminadas, true); @endphp
                    <details class="group {{ $loop->first ? '' : 'mt-4' }} {{ $visita->estado === 'realizada' ? 'rounded-xl border border-emerald-200 border-s-[6px] border-s-emerald-500 bg-emerald-50/60 p-4' : ($loop->first ? '' : 'border-t border-slate-100 pt-6') }}" @unless ($visitaTerminada) open @endunless>
                        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                            <span class="min-w-0">
                                <h3 class="font-semibold text-blue-950">Visita {{ $numeroVisita }} · {{ $numeroVisita === 1 ? 'Adaptación' : 'Seguimiento de salud y estabilidad' }}</h3>
                                @if ($visitaTerminada)<span class="block text-[13px] text-slate-500 group-open:hidden">{{ $visita->fecha_realizada ? 'Completada el '.$visita->fecha_realizada->format('d/m/Y') : 'Programada el '.$visita->fecha_programada->format('d/m/Y') }} · toca para ver el detalle</span>@endif
                            </span>
                            <span class="flex items-center gap-2">
                                {{-- Visita hecha: verde y con ✓ para que se note a simple vista. --}}
                                @if ($visita->estado === 'realizada')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-600 px-3 py-1 text-[13px] font-bold text-white">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                                        Completada
                                    </span>
                                @else
                                    <x-admin.estado de="visita" :valor="$visita->estado" />
                                @endif
                                <svg class="h-4 w-4 text-slate-500 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4Z" clip-rule="evenodd"/></svg>
                            </span>
                        </summary>
                        <dl class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <x-admin.dato etiqueta="Fecha programada">{{ $visita->fecha_programada->format('d/m/Y') }}</x-admin.dato>
                            @if ($visita->fecha_realizada)<x-admin.dato etiqueta="Fecha realizada">{{ $visita->fecha_realizada->format('d/m/Y') }}</x-admin.dato>@endif
                            @if ($visita->estado_salud)<x-admin.dato etiqueta="Estado de salud">{{ $visita->estado_salud }}</x-admin.dato>@endif
                        </dl>
                        @if ($visita->observaciones)
                            <div class="mt-3 rounded-xl bg-slate-50 p-3">
                                <p class="text-[13px] font-medium text-slate-500">Cuestionario y observaciones</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-slate-800">{{ $visita->observaciones }}</p>
                            </div>
                        @endif
                        @if ($visita->foto_url)
                            <x-admin.boton variante="secundario" tamano="chico" class="mt-3" :href="route('seguimientos.visitas.foto', [$seguimiento, $visita])" target="_blank" rel="noopener">Ver foto privada de la visita</x-admin.boton>
                        @endif

                        @if ($visita->estado === 'programada' && $seguimiento->estado === 'activo' && $visita->voluntario_id === auth()->id())
                            <div class="mt-4 flex flex-wrap items-start gap-2">
                                <details class="group" @error('fecha_programada') open @enderror>
                                    <summary class="inline-flex cursor-pointer list-none items-center rounded-full border border-slate-300 px-4 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 group-open:bg-slate-100">Reprogramar</summary>
                                <form method="POST" action="{{ route('seguimientos.visitas.reschedule', [$seguimiento, $visita]) }}" class="mt-2 flex flex-wrap items-end gap-2 rounded-xl bg-slate-50 p-3">
                                    @csrf @method('PATCH')
                                    <div><label class="admin-etiqueta" for="reprogramar_{{ $visita->id }}">Reprogramar para</label><input id="reprogramar_{{ $visita->id }}" type="date" name="fecha_programada" min="{{ now()->toDateString() }}" required class="admin-campo"></div>
                                    <x-admin.boton tamano="chico">Guardar nueva fecha</x-admin.boton>
                                </form>
                                </details>
                                <details class="group">
                                    <summary class="inline-flex cursor-pointer list-none items-center rounded-full border border-slate-300 px-4 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 group-open:bg-slate-100">No asistió</summary>
                                <form method="POST" action="{{ route('seguimientos.visitas.no-show', [$seguimiento, $visita]) }}" class="mt-2 flex flex-wrap items-end gap-2 rounded-xl bg-slate-50 p-3"
                                      x-data x-on:submit="if (! confirm({{ Js::from('¿Registrar que la familia no recibió esta visita? Ya no se podrá cambiar.') }})) $event.preventDefault()">
                                    @csrf @method('PATCH')
                                    <div><label class="admin-etiqueta" for="no_asistio_{{ $visita->id }}">Detalle <span class="normal-case font-normal">(opcional)</span></label><input id="no_asistio_{{ $visita->id }}" name="observaciones" maxlength="500" class="admin-campo" placeholder="Ej. No contestó el teléfono"></div>
                                    <x-admin.boton variante="secundario" tamano="chico">Confirmar «No asistió»</x-admin.boton>
                                </form>
                                </details>
                            </div>
                            @error('fecha_programada')<p class="admin-error">{{ $message }}</p>@enderror
                        @endif

                        @if ($visita->estado === 'programada' && $seguimiento->estado === 'activo' && $puedeCompletarVisita)
                            <details class="group mt-4 rounded-xl border border-slate-200" @if ($errors->hasAny(['fecha_realizada', 'respuestas.*', 'observaciones', 'foto'])) open @endif>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 text-sm font-semibold text-blue-950 hover:bg-slate-50">Registrar visita realizada <span aria-hidden="true" class="text-slate-400 group-open:rotate-180">▾</span></summary>
                                <form class="space-y-4 border-t border-slate-100 p-4" method="POST" enctype="multipart/form-data" action="{{ route('seguimientos.visitas.complete', [$seguimiento, $visita]) }}">
                                    @csrf @method('PATCH')
                                    <div><label class="admin-etiqueta" for="fecha_realizada_{{ $visita->id }}">Fecha realizada</label><input id="fecha_realizada_{{ $visita->id }}" type="date" name="fecha_realizada" max="{{ now()->toDateString() }}" value="{{ old('fecha_realizada') }}" required class="admin-campo sm:w-56">@error('fecha_realizada')<p class="admin-error">{{ $message }}</p>@enderror</div>
                                    @foreach ($preguntasVisita as $campo => $pregunta)
                                        @php $multiple = $pregunta['multiple'] ?? false; $seleccionActual = old('respuestas.'.$campo, $multiple ? [] : ''); @endphp
                                        <fieldset class="rounded-xl border border-slate-200 p-4">
                                            <legend class="px-1 text-sm font-semibold text-slate-800">{{ $pregunta['label'] }}</legend>
                                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                                @foreach ($pregunta['options'] as $valor => $etiqueta)
                                                    <label class="inline-flex items-center gap-2 rounded-lg p-2 text-sm text-slate-700 hover:bg-slate-50">
                                                        <input type="{{ $multiple ? 'checkbox' : 'radio' }}" name="{{ $multiple ? 'respuestas['.$campo.'][]' : 'respuestas['.$campo.']' }}" value="{{ $valor }}" class="text-blue-900 focus:ring-blue-900 {{ $multiple ? 'rounded' : '' }}" @checked($multiple ? in_array($valor, (array) $seleccionActual, true) : $seleccionActual === $valor) @if (! $multiple) required @endif>
                                                        <span>{{ $etiqueta }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            @error('respuestas.'.$campo)<p class="admin-error">{{ $message }}</p>@enderror
                                        </fieldset>
                                    @endforeach
                                    <div><label class="admin-etiqueta" for="observaciones_{{ $visita->id }}">Observaciones adicionales <span class="normal-case font-normal">(opcional)</span></label><textarea id="observaciones_{{ $visita->id }}" name="observaciones" rows="3" maxlength="2000" class="admin-campo">{{ old('observaciones') }}</textarea>@error('observaciones')<p class="admin-error">{{ $message }}</p>@enderror</div>
                                    <div><label class="admin-etiqueta flex items-center gap-2" for="foto_{{ $visita->id }}"><svg class="h-5 w-5 shrink-0 text-blue-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4m0 0-4.5 4.5M12 4l4.5 4.5M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3" /></svg>Subir foto de la visita <span class="normal-case font-normal">(opcional, JPG/PNG/WebP hasta 5 MB)</span></label><input id="foto_{{ $visita->id }}" type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="admin-archivo">@error('foto')<p class="admin-error">{{ $message }}</p>@enderror</div>
                                    <x-admin.boton variante="exito">Guardar visita</x-admin.boton>
                                </form>
                            </details>
                        @elseif ($visita->estado === 'programada' && $seguimiento->estado === 'activo')
                            <p class="mt-3 rounded-xl bg-slate-50 p-3 text-sm text-slate-700">La visita 2 se podrá registrar después de completar la visita 1.</p>
                        @endif
                    </details>
                @empty
                    <p class="text-sm text-slate-500">Sin visitas programadas.</p>
                @endforelse
            </x-admin.tarjeta>

            <x-admin.tarjeta titulo="Incidencias">
                @forelse ($seguimiento->incidencias as $incidencia)
                    @php
                        $tipoIncidencia = $tiposIncidencia[$incidencia->tipo] ?? ucfirst(str_replace('_', ' ', $incidencia->tipo));
                        $incidenciaAbierta = $incidencia->estado !== 'atendida';
                    @endphp
                    <article class="{{ $loop->first ? '' : 'mt-4' }} {{ $incidenciaAbierta ? 'rounded-xl border border-red-200 border-s-[6px] border-s-red-600 bg-red-50 p-4' : ($loop->first ? '' : 'border-t border-slate-100 pt-4') }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-blue-950">{{ $tipoIncidencia }}</p>
                            @if ($incidenciaAbierta)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-600 px-3 py-1 text-[13px] font-bold text-white">
                                    <span class="relative flex h-2.5 w-2.5" aria-hidden="true"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-white"></span></span>
                                    Urgente · esperando a la fundación
                                </span>
                            @else
                                <x-admin.estado de="incidencia" :valor="$incidencia->estado" />
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-slate-700">{{ $incidencia->descripcion }}</p>
                        @if ($incidencia->comentarios_resolucion)<p class="mt-2 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-900"><span class="font-semibold">Resolución:</span> {{ $incidencia->comentarios_resolucion }}</p>@endif
                        @can('attend', $incidencia)
                            <form method="POST" action="{{ route('seguimientos.incidencias.attend', [$seguimiento, $incidencia]) }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf @method('PATCH')
                                <div class="min-w-[14rem] flex-1">
                                    <label class="admin-etiqueta" for="comentarios_resolucion_{{ $incidencia->id }}">Resolución</label>
                                    <input id="comentarios_resolucion_{{ $incidencia->id }}" name="comentarios_resolucion" required value="{{ old('comentarios_resolucion') }}" class="admin-campo" placeholder="Qué se hizo para resolverla">
                                    @error('comentarios_resolucion')<p class="admin-error">{{ $message }}</p>@enderror
                                </div>
                                <x-admin.boton variante="exito" tamano="chico">Atender</x-admin.boton>
                            </form>
                        @endcan
                        {{-- El voluntario no cierra la incidencia: avisa de inmediato a la fundación, que es quien decide. --}}
                        @if ($incidenciaAbierta && ! auth()->user()->hasRole('administrador'))
                            @php
                                $avisoFundacion = \App\Support\ContactoTelefonico::whatsapp(config('pol.whatsapp.consultas'), 'incidencia', [
                                    'voluntario' => auth()->user()->nombres.' '.auth()->user()->apellidos,
                                    'tipo' => mb_strtolower($tipoIncidencia),
                                    'mascota' => $seguimiento->solicitud->mascota->nombre,
                                ]);
                            @endphp
                            <p class="mt-3 text-sm text-red-900">La fundación revisará el caso y lo cerrará. Si la mascota corre peligro, avisa ahora mismo.</p>
                            @if ($avisoFundacion)
                                <a href="{{ $avisoFundacion }}" target="_blank" rel="noopener" class="mt-3 inline-flex min-h-11 items-center gap-2 rounded-full bg-red-600 px-5 text-sm font-bold text-white shadow-sm hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:ring-offset-2">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v3.75m0 3.75h.008M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /></svg>
                                    Avisar urgente a la fundación por WhatsApp
                                </a>
                            @endif
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Sin incidencias.</p>
                @endforelse
            </x-admin.tarjeta>
        </div>

        {{-- Lateral: la mascota, contacto y reportar --}}
        <div class="min-w-0 space-y-6">
            {{-- La mascota que se visita: foto y datos básicos, para reconocerla en la visita. --}}
            @php
                $mascota = $seguimiento->solicitud->mascota;
                $fotoMascota = $mascota->fotos->firstWhere('es_principal', true) ?? $mascota->fotos->first();
                $tamanos = ['pequeno' => 'pequeño', 'mediano' => 'mediano', 'grande' => 'grande'];
            @endphp
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                @if ($fotoMascota)
                    <img src="{{ $fotoMascota->url_miniatura }}" alt="Foto de {{ $mascota->nombre }}" class="aspect-[4/3] w-full bg-slate-100 object-cover">
                @else
                    <div class="flex aspect-[4/3] w-full items-center justify-center bg-slate-100 text-5xl" aria-hidden="true">{{ $mascota->especie === 'gato' ? '🐱' : '🐶' }}</div>
                @endif
                <div class="p-5">
                    <p class="text-[13px] font-semibold uppercase tracking-wide text-sky-700">La mascota que visitas</p>
                    <h2 class="mt-0.5 text-xl font-bold text-blue-950">{{ $mascota->nombre }}</h2>
                    <p class="mt-1 text-sm text-slate-700">
                        {{ $mascota->especie === 'gato' ? 'Gato' : 'Perro' }} · {{ $mascota->sexo === 'hembra' ? 'hembra' : 'macho' }} · {{ $mascota->edad_formateada }} · {{ $tamanos[$mascota->tamano] ?? $mascota->tamano }}
                    </p>
                    @if ($mascota->raza || $mascota->color)
                        <p class="text-sm text-slate-600">{{ collect([$mascota->raza ? 'Raza: '.$mascota->raza : null, $mascota->color ? 'Color: '.$mascota->color : null])->filter()->join(' · ') }}</p>
                    @endif
                </div>
            </section>

            @can('contactAdopter', $seguimiento)
                <x-admin.tarjeta titulo="Contacto para seguimiento" descripcion="Coordina únicamente asuntos relacionados con las visitas de seguimiento.">
                    <x-botones-contacto :telefono="$familia->telefono" mensaje="seguimiento" :datos="['nombre' => \App\Support\ContactoTelefonico::primerNombre($familia->nombres), 'mascota' => $seguimiento->solicitud->mascota->nombre, 'voluntario' => auth()->user()->nombres]" />
                </x-admin.tarjeta>
            @endcan


            @if ($seguimiento->estado === 'activo')
                <x-admin.tarjeta titulo="Reportar incidencia" descripcion="Avisa a la fundación si observas un problema de salud, posible maltrato o pierdes contacto con la familia.">
                    <details class="group" @if ($errors->hasAny(['tipo', 'descripcion', 'visita_id'])) open @endif>
                    <summary class="inline-flex cursor-pointer list-none items-center rounded-full border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 group-open:hidden">Reportar una incidencia</summary>
                    <form method="POST" action="{{ route('seguimientos.incidencias.store', $seguimiento) }}" class="space-y-3">@csrf
                        <div>
                            <label class="admin-etiqueta" for="tipo">Tipo</label>
                            <select id="tipo" name="tipo" class="admin-campo">@foreach ($tiposIncidencia as $valor => $etiqueta)<option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $etiqueta }}</option>@endforeach</select>
                            @error('tipo')<p class="admin-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="admin-etiqueta" for="descripcion">Descripción</label>
                            <textarea id="descripcion" name="descripcion" rows="3" required class="admin-campo">{{ old('descripcion') }}</textarea>
                            @error('descripcion')<p class="admin-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="admin-etiqueta" for="visita_id">Visita asociada</label>
                            <select id="visita_id" name="visita_id" class="admin-campo"><option value="">Sin visita</option>@foreach ($visitasOrdenadas as $visita)<option value="{{ $visita->id }}" @selected(old('visita_id') == $visita->id)>Visita del {{ $visita->fecha_programada->format('d/m/Y') }}</option>@endforeach</select>
                            @error('visita_id')<p class="admin-error">{{ $message }}</p>@enderror
                        </div>
                        <x-admin.boton variante="peligro-suave" class="w-full">Reportar</x-admin.boton>
                    </form>
                    </details>
                </x-admin.tarjeta>
            @endif
        </div>
    </div>
</x-admin.pagina>
