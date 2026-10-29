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
                    <article class="{{ $loop->first ? '' : 'mt-6 border-t border-slate-100 pt-6' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-semibold text-blue-950">Visita {{ $numeroVisita }} · {{ $numeroVisita === 1 ? 'Adaptación' : 'Seguimiento de salud y estabilidad' }}</h3>
                            <x-admin.estado de="visita" :valor="$visita->estado" />
                        </div>
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
                                    <div><label class="admin-etiqueta" for="foto_{{ $visita->id }}">Foto de la visita <span class="normal-case font-normal">(opcional, JPG/PNG/WebP hasta 5 MB)</span></label><input id="foto_{{ $visita->id }}" type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="admin-archivo">@error('foto')<p class="admin-error">{{ $message }}</p>@enderror</div>
                                    <x-admin.boton variante="exito">Guardar visita</x-admin.boton>
                                </form>
                            </details>
                        @elseif ($visita->estado === 'programada' && $seguimiento->estado === 'activo')
                            <p class="mt-3 rounded-xl bg-slate-50 p-3 text-sm text-slate-700">La visita 2 se podrá registrar después de completar la visita 1.</p>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Sin visitas programadas.</p>
                @endforelse
            </x-admin.tarjeta>

            <x-admin.tarjeta titulo="Incidencias">
                @forelse ($seguimiento->incidencias as $incidencia)
                    <article class="{{ $loop->first ? '' : 'mt-4 border-t border-slate-100 pt-4' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-blue-950">{{ $tiposIncidencia[$incidencia->tipo] ?? ucfirst(str_replace('_', ' ', $incidencia->tipo)) }}</p>
                            <x-admin.estado de="incidencia" :valor="$incidencia->estado" />
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
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Sin incidencias.</p>
                @endforelse
            </x-admin.tarjeta>
        </div>

        {{-- Lateral: contacto, fechas sugeridas y reportar --}}
        <div class="min-w-0 space-y-6">
            @can('contactAdopter', $seguimiento)
                <x-admin.tarjeta titulo="Contacto para seguimiento" descripcion="Coordina únicamente asuntos relacionados con las visitas de seguimiento.">
                    <x-botones-contacto :telefono="$familia->telefono" mensaje="seguimiento" :datos="['nombre' => \App\Support\ContactoTelefonico::primerNombre($familia->nombres), 'mascota' => $seguimiento->solicitud->mascota->nombre, 'voluntario' => auth()->user()->nombres]" />
                </x-admin.tarjeta>
            @endcan

            @if ($fechasSugeridas)
                <x-admin.tarjeta titulo="Fechas sugeridas para visitas de seguimiento" descripcion="Se calculan desde la fecha real de entrega. Son sugerencias; cada visita se programa cuando coordines con la familia.">
                    <ul class="space-y-3 text-sm">
                        @foreach ($fechasSugeridas as $numero => $fechas)
                            @php $visitaSugerida = $visitasOrdenadas->get($numero - 1); @endphp
                            <li class="rounded-xl bg-slate-50 p-3">
                                <p class="font-semibold text-blue-950">Visita {{ $numero }} · objetivo a los {{ $fechas['dias'] }} días: {{ $fecha($fechas['fecha']) }}</p>
                                <p class="text-slate-600">(margen recomendado: {{ $fecha($fechas['desde']) }}–{{ $fecha($fechas['hasta']) }})</p>
                                <p class="mt-1">
                                    @if ($visitaSugerida && $visitaSugerida->estado === 'realizada')<span class="font-medium text-emerald-700">Realizada: {{ $visitaSugerida->fecha_realizada->format('d/m/Y') }}</span>
                                    @elseif ($visitaSugerida && $visitaSugerida->estado === 'no_asistio')<span class="font-medium text-amber-800">No asistió ({{ $visitaSugerida->fecha_programada->format('d/m/Y') }})</span>
                                    @elseif ($visitaSugerida) programada: {{ $visitaSugerida->fecha_programada->format('d/m/Y') }}@if (! \App\Support\FechasSeguimientoAdopcion::dentroDelMargen($visitaSugerida->fecha_programada->toDateString(), $fechas))<span class="font-semibold text-amber-800"> · Fuera del margen recomendado</span>@endif
                                    @elseif ($numero === $siguienteNumeroVisita)<span class="font-medium text-blue-900">Próxima para coordinar</span>
                                    @else<span class="text-slate-500">Se habilita al registrar la visita 1</span>@endif
                                </p>
                            </li>
                        @endforeach
                    </ul>
                </x-admin.tarjeta>
            @endif

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
