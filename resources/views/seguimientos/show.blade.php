<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Seguimiento: {{ $seguimiento->solicitud->mascota->nombre }}</h2></x-slot>
    <div class="py-12"><div class="max-w-4xl mx-auto space-y-6 sm:px-6 lg:px-8">
        @if (session('status'))<div class="p-4 text-green-700 bg-green-50">{{ session('status') }}</div>@endif
        @if (session('warning'))<div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="status">{{ session('warning') }}</div>@endif
        @php
            // La visita 2 se habilita cuando la visita 1 terminó: realizada o «No asistió».
            $terminadas = \App\Http\Controllers\SeguimientoAdopcionController::VISITA_TERMINADA;
            $visitasOrdenadas = $seguimiento->visitas->sortBy('id')->values();
            $primeraTerminada = in_array($visitasOrdenadas->first()?->estado, $terminadas, true);
            $siguienteNumeroVisita = $visitasOrdenadas->count() + 1;
            $puedeProgramarSiguiente = $visitasOrdenadas->isEmpty() || ($visitasOrdenadas->count() === 1 && $primeraTerminada);
        @endphp
        @if ($fechasSugeridas)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <h3 class="font-semibold text-lg text-amber-950">Fechas sugeridas para visitas de seguimiento</h3>
                <p class="mt-1 text-sm text-amber-900">Se calculan desde la fecha real de entrega. Son sugerencias; cada visita se programa cuando coordines con la familia.</p>
                <ul class="mt-3 space-y-2 text-sm text-gray-800">
                    @foreach ($fechasSugeridas as $numero => $fechas)
                        @php $visitaSugerida = $visitasOrdenadas->get($numero - 1); @endphp
                        <li><span class="font-semibold">Visita {{ $numero }} · objetivo a los {{ $fechas['dias'] }} días:</span> {{ \Illuminate\Support\Carbon::parse($fechas['fecha'])->format('d/m/Y') }} <span class="text-gray-600">(margen recomendado: {{ \Illuminate\Support\Carbon::parse($fechas['desde'])->format('d/m/Y') }}–{{ \Illuminate\Support\Carbon::parse($fechas['hasta'])->format('d/m/Y') }})</span> · @if ($visitaSugerida && $visitaSugerida->estado === 'realizada')<span class="font-medium text-green-700">Realizada: {{ $visitaSugerida->fecha_realizada->format('d/m/Y') }}</span>@elseif ($visitaSugerida && $visitaSugerida->estado === 'no_asistio')<span class="font-medium text-amber-800">No asistió ({{ $visitaSugerida->fecha_programada->format('d/m/Y') }})</span>@elseif ($visitaSugerida) programada: {{ $visitaSugerida->fecha_programada->format('d/m/Y') }}@if (!\App\Support\FechasSeguimientoAdopcion::dentroDelMargen($visitaSugerida->fecha_programada->toDateString(), $fechas))<span class="font-semibold text-amber-800"> · Fuera del margen recomendado</span>@endif @elseif ($numero === $siguienteNumeroVisita)<span class="font-medium text-blue-900">Próxima para coordinar</span>@else<span class="text-gray-600">Se habilita al registrar la visita 1</span>@endif</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @can('contactAdopter', $seguimiento)
            <div class="p-6 bg-white shadow-sm sm:rounded-lg"><h3 class="font-semibold text-lg">Contacto para seguimiento</h3><p class="mb-3 text-sm text-gray-600">Coordina únicamente asuntos relacionados con las visitas de seguimiento.</p><x-botones-contacto :telefono="$seguimiento->solicitud->solicitante->telefono" mensaje="seguimiento" :datos="['nombre' => \App\Support\ContactoTelefonico::primerNombre($seguimiento->solicitud->solicitante->nombres), 'mascota' => $seguimiento->solicitud->mascota->nombre, 'voluntario' => auth()->user()->nombres]" :llamar="false" /></div>
        @endcan
        @if ($seguimiento->estado === 'activo')
            @if ($puedeProgramarSiguiente && $siguienteNumeroVisita <= 2)
                <div class="p-6 bg-white shadow-sm sm:rounded-lg">
                    <h3 class="font-semibold text-lg">Programar visita {{ $siguienteNumeroVisita }}</h3>
                    <p class="mt-1 text-sm text-gray-600">Coordina primero el día por WhatsApp con la familia. El rango sugerido es una guía; puedes elegir otro día y el sistema avisará si queda fuera del margen recomendado.</p>
                    <form method="POST" action="{{ route('seguimientos.visitas.store', $seguimiento) }}" class="mt-3 space-y-2">@csrf
                        <label class="block" for="fecha_programada">Fecha acordada</label>
                        <input id="fecha_programada" type="date" name="fecha_programada" value="{{ old('fecha_programada') }}" required>
                        @error('fecha_programada')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <button class="text-indigo-600">Programar visita {{ $siguienteNumeroVisita }}</button>
                    </form>
                </div>
            @elseif ($visitasOrdenadas->count() === 1)
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950" role="status">
                    La visita 1 está programada. Cuando la registres como realizada (o «No asistió»), aparecerá la opción para programar la visita 2. Si cambia el día, usa «Reprogramar».
                </div>
            @endif
            <div class="p-6 bg-white shadow-sm sm:rounded-lg"><h3 class="font-semibold text-lg">Reportar incidencia</h3><form method="POST" action="{{ route('seguimientos.incidencias.store', $seguimiento) }}" class="mt-3 space-y-2">@csrf <label class="block" for="tipo">Tipo</label><select id="tipo" name="tipo">@foreach (['salud' => 'Salud', 'maltrato' => 'Maltrato', 'perdida_contacto' => 'Pérdida de contacto', 'otro' => 'Otro'] as $valor => $etiqueta)<option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $etiqueta }}</option>@endforeach</select>@error('tipo')<p class="text-sm text-red-600">{{ $message }}</p>@enderror <label class="block" for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" required>{{ old('descripcion') }}</textarea>@error('descripcion')<p class="text-sm text-red-600">{{ $message }}</p>@enderror <label class="block" for="visita_id">Visita asociada</label><select id="visita_id" name="visita_id"><option value="">Sin visita</option>@foreach ($seguimiento->visitas as $visita)<option value="{{ $visita->id }}" @selected(old('visita_id') == $visita->id)>Visita {{ $visita->fecha_programada }}</option>@endforeach</select>@error('visita_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror <button class="block text-red-600">Reportar</button></form></div>
        @endif
        <div class="p-6 bg-white shadow-sm sm:rounded-lg">
            <h3 class="font-semibold text-lg">Visitas</h3>
            @forelse ($seguimiento->visitas->sortBy('id')->values() as $visita)
                @php
                    $numeroVisita = $loop->iteration;
                    $preguntasVisita = \App\Support\CuestionarioVisitaSeguimiento::preguntas($numeroVisita);
                    $puedeCompletarVisita = $numeroVisita === 1 || ($numeroVisita === 2 && $primeraTerminada);
                @endphp
                <div class="pt-4 mt-4 space-y-2 border-t">
                    <p class="font-semibold">Visita {{ $numeroVisita }} · {{ $numeroVisita === 1 ? 'Adaptación' : 'Seguimiento de salud y estabilidad' }}</p>
                    <p><span class="font-medium">Fecha programada:</span> {{ $visita->fecha_programada->format('d/m/Y') }}</p>
                    <p><span class="font-medium">Estado:</span> {{ ['programada' => 'Programada', 'realizada' => 'Realizada', 'no_asistio' => 'No asistió'][$visita->estado] ?? $visita->estado }}</p>
                    @if ($visita->fecha_realizada)<p><span class="font-medium">Fecha realizada:</span> {{ $visita->fecha_realizada->format('d/m/Y') }}</p>@endif
                    @if ($visita->estado_salud)<p><span class="font-medium">Estado de salud:</span> {{ $visita->estado_salud }}</p>@endif
                    @if ($visita->observaciones)<div><p class="font-medium">Cuestionario y observaciones:</p><p class="whitespace-pre-line text-gray-700">{{ $visita->observaciones }}</p></div>@endif
                    @if ($visita->foto_url)
                        <a href="{{ route('seguimientos.visitas.foto', [$seguimiento, $visita]) }}" target="_blank" rel="noopener" class="inline-flex items-center text-sm font-medium text-blue-900 underline">Ver foto privada de la visita</a>
                    @endif
                    @if ($visita->estado === 'programada' && $seguimiento->estado === 'activo' && $visita->voluntario_id === auth()->id())
                        <div class="flex flex-wrap items-end gap-4 rounded-lg bg-slate-50 p-3">
                            <form method="POST" action="{{ route('seguimientos.visitas.reschedule', [$seguimiento, $visita]) }}" class="flex flex-wrap items-end gap-2">
                                @csrf @method('PATCH')
                                <div><label class="block text-sm font-medium" for="reprogramar_{{ $visita->id }}">Reprogramar para</label><input id="reprogramar_{{ $visita->id }}" type="date" name="fecha_programada" min="{{ now()->toDateString() }}" required class="mt-1 rounded-md border-slate-300 text-sm"></div>
                                <button class="rounded-md border border-blue-900 bg-white px-3 py-2 text-sm font-semibold text-blue-950 hover:bg-blue-50">Reprogramar</button>
                            </form>
                            <form method="POST" action="{{ route('seguimientos.visitas.no-show', [$seguimiento, $visita]) }}" class="flex flex-wrap items-end gap-2"
                                  x-data x-on:submit="if (! confirm({{ Js::from('¿Registrar que la familia no recibió esta visita? Ya no se podrá cambiar.') }})) $event.preventDefault()">
                                @csrf @method('PATCH')
                                <div><label class="block text-sm font-medium" for="no_asistio_{{ $visita->id }}">Detalle <span class="font-normal text-gray-500">(opcional)</span></label><input id="no_asistio_{{ $visita->id }}" name="observaciones" maxlength="500" class="mt-1 rounded-md border-slate-300 text-sm" placeholder="Ej. No contestó el teléfono"></div>
                                <button class="rounded-md border border-amber-600 bg-white px-3 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-50">No asistió</button>
                            </form>
                        </div>
                        @error('fecha_programada')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    @endif
                    @if ($visita->estado === 'programada' && $seguimiento->estado === 'activo' && $puedeCompletarVisita)
                        <form class="pt-3 space-y-4" method="POST" enctype="multipart/form-data" action="{{ route('seguimientos.visitas.complete', [$seguimiento, $visita]) }}">
                            @csrf @method('PATCH')
                            <h4 class="font-semibold">Registrar visita realizada</h4>
                            <div><label class="block font-medium" for="fecha_realizada_{{ $visita->id }}">Fecha realizada</label><input id="fecha_realizada_{{ $visita->id }}" type="date" name="fecha_realizada" max="{{ now()->toDateString() }}" value="{{ old('fecha_realizada') }}" required>@error('fecha_realizada')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                            @foreach ($preguntasVisita as $campo => $pregunta)
                                @php $multiple = $pregunta['multiple'] ?? false; $seleccionActual = old('respuestas.'.$campo, $multiple ? [] : ''); @endphp
                                <fieldset class="rounded-lg border border-slate-200 p-4">
                                    <legend class="px-1 font-medium text-slate-800">{{ $pregunta['label'] }}</legend>
                                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                        @foreach ($pregunta['options'] as $valor => $etiqueta)
                                            <label class="inline-flex items-center gap-2 rounded-md p-2 text-sm text-slate-700 hover:bg-slate-50">
                                                <input type="{{ $multiple ? 'checkbox' : 'radio' }}" name="{{ $multiple ? 'respuestas['.$campo.'][]' : 'respuestas['.$campo.']' }}" value="{{ $valor }}" @checked($multiple ? in_array($valor, (array) $seleccionActual, true) : $seleccionActual === $valor) @if (! $multiple) required @endif>
                                                <span>{{ $etiqueta }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('respuestas.'.$campo)<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                                </fieldset>
                            @endforeach
                            <div><label class="block font-medium" for="observaciones_{{ $visita->id }}">Observaciones adicionales <span class="font-normal text-gray-500">(opcional)</span></label><textarea id="observaciones_{{ $visita->id }}" name="observaciones" rows="3" maxlength="2000" class="mt-1 w-full rounded-md border-slate-300">{{ old('observaciones') }}</textarea>@error('observaciones')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                            <div><label class="block font-medium" for="foto_{{ $visita->id }}">Foto de la visita <span class="font-normal text-gray-500">(opcional, JPG/PNG/WebP hasta 5 MB)</span></label><input id="foto_{{ $visita->id }}" type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm">@error('foto')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                            <button class="rounded-md bg-blue-950 px-4 py-2 font-semibold text-white">Guardar visita</button>
                        </form>
                    @elseif ($visita->estado === 'programada' && $seguimiento->estado === 'activo')
                        <p class="rounded-md bg-slate-50 p-3 text-sm text-slate-700">La visita 2 se podrá registrar después de completar la visita 1.</p>
                    @endif
                </div>
            @empty <p class="mt-2 text-gray-500">Sin visitas programadas.</p>@endforelse
        </div>
        <div class="p-6 bg-white shadow-sm sm:rounded-lg"><h3 class="font-semibold text-lg">Incidencias</h3>@forelse ($seguimiento->incidencias as $incidencia)<div class="pt-3 mt-3 space-y-2 border-t"><p><span class="font-medium">Tipo:</span> {{ ucfirst(str_replace('_', ' ', $incidencia->tipo)) }}</p><p><span class="font-medium">Estado:</span> {{ ucfirst(str_replace('_', ' ', $incidencia->estado)) }}</p><p><span class="font-medium">Descripción:</span> {{ $incidencia->descripcion }}</p>@if ($incidencia->comentarios_resolucion)<p><span class="font-medium">Resolución:</span> {{ $incidencia->comentarios_resolucion }}</p>@endif @can('attend', $incidencia)<form method="POST" action="{{ route('seguimientos.incidencias.attend', [$seguimiento, $incidencia]) }}">@csrf @method('PATCH')<label for="comentarios_resolucion_{{ $incidencia->id }}">Resolución</label><input id="comentarios_resolucion_{{ $incidencia->id }}" name="comentarios_resolucion" required value="{{ old('comentarios_resolucion') }}">@error('comentarios_resolucion')<p class="text-sm text-red-600">{{ $message }}</p>@enderror <button class="text-green-600">Atender</button></form>@endcan</div>@empty <p class="mt-2 text-gray-500">Sin incidencias.</p>@endforelse</div>
    </div></div>
</x-app-layout>
