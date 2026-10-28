<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Seguimiento de {{ $seguimiento->solicitud->mascota->nombre }}</h2></x-slot>
    <div class="py-12"><div class="max-w-4xl mx-auto space-y-6 sm:px-6 lg:px-8">
        @if (session('status'))<div class="p-4 text-green-700 bg-green-50">{{ session('status') }}</div>@endif
        @if (session('warning'))<div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="status">{{ session('warning') }}</div>@endif
        @if ($fechasSugeridas)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <h3 class="font-semibold text-lg text-amber-950">Fechas sugeridas para visitas de seguimiento</h3>
                <p class="mt-1 text-sm text-amber-900">Se calculan desde la fecha real de entrega. Son sugerencias; cada visita se programa cuando coordines con la familia.</p>
                <ul class="mt-3 space-y-2 text-sm text-gray-800">
                    @foreach ($fechasSugeridas as $numero => $fechas)
                        @php $visitaSugerida = $seguimiento->visitas->sortBy('created_at')->values()->get($numero - 1); @endphp
                        <li><span class="font-semibold">Visita {{ $numero }} · objetivo a los {{ $fechas['dias'] }} días:</span> {{ \Illuminate\Support\Carbon::parse($fechas['fecha'])->format('d/m/Y') }} <span class="text-gray-600">(margen recomendado: {{ \Illuminate\Support\Carbon::parse($fechas['desde'])->format('d/m/Y') }}–{{ \Illuminate\Support\Carbon::parse($fechas['hasta'])->format('d/m/Y') }})</span> · @if ($visitaSugerida) programada: {{ $visitaSugerida->fecha_programada->format('d/m/Y') }}@if (!\App\Support\FechasSeguimientoAdopcion::dentroDelMargen($visitaSugerida->fecha_programada->toDateString(), $fechas))<span class="font-semibold text-amber-800"> · Fuera del margen recomendado</span>@endif @else sugerencia sin visita programada @endif</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="p-6 bg-white shadow-sm sm:rounded-lg space-y-2">
            <h3 class="font-semibold text-lg">Información del seguimiento</h3>
            <p><span class="font-medium">Estado:</span> {{ ucfirst(str_replace('_', ' ', $seguimiento->estado)) }}</p>
            <p><span class="font-medium">Ciudadano:</span> {{ $seguimiento->solicitud->solicitante->nombres }} {{ $seguimiento->solicitud->solicitante->apellidos }}</p>
            <p><span class="font-medium">Voluntario:</span> {{ $seguimiento->voluntario?->nombres ?? 'Sin asignar' }}</p>
            @can('assign', $seguimiento)
                <form method="POST" action="{{ route('admin.seguimientos.assign', $seguimiento) }}" class="pt-4 space-y-2">@csrf @method('PATCH')
                    <label for="voluntario_id" class="block font-medium">Asignar voluntario</label>
                    <select id="voluntario_id" name="voluntario_id" required class="rounded border-gray-300">
                        @foreach ($voluntarios as $voluntario)<option value="{{ $voluntario->id }}" @selected(old('voluntario_id') == $voluntario->id)>{{ $voluntario->nombres }} {{ $voluntario->apellidos }}</option>@endforeach
                    </select>
                    @error('voluntario_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <button class="ml-2 text-indigo-600">Asignar y activar</button>
                </form>
            @endcan
            @can('close', $seguimiento)
                <form method="POST" action="{{ route('admin.seguimientos.close', $seguimiento) }}" class="pt-4">@csrf @method('PATCH')
                    <button x-data x-on:click="if (! confirm('¿Cerrar seguimiento?')) $event.preventDefault()" class="text-red-600">Cerrar seguimiento</button>
                </form>
            @endcan
        </div>
        <div class="p-6 bg-white shadow-sm sm:rounded-lg">
            <h3 class="font-semibold text-lg">Visitas</h3>
            @forelse ($seguimiento->visitas as $visita)
                <div class="pt-3 mt-3 space-y-2 border-t"><p><span class="font-medium">Fecha programada:</span> {{ $visita->fecha_programada->format('d/m/Y') }}</p><p><span class="font-medium">Estado:</span> {{ ['programada' => 'Programada', 'realizada' => 'Realizada', 'no_asistio' => 'No asistió'][$visita->estado] ?? $visita->estado }}</p>@if ($visita->fecha_realizada)<p><span class="font-medium">Fecha realizada:</span> {{ $visita->fecha_realizada->format('d/m/Y') }}</p>@endif @if ($visita->observaciones)<div><p class="font-medium">Cuestionario y observaciones:</p><p class="whitespace-pre-line text-gray-700">{{ $visita->observaciones }}</p></div>@endif @if ($visita->estado_salud)<p><span class="font-medium">Estado de salud:</span> {{ $visita->estado_salud }}</p>@endif @if ($visita->foto_url)<a href="{{ route('admin.seguimientos.visitas.foto', [$seguimiento, $visita]) }}" target="_blank" rel="noopener" class="inline-flex text-sm font-medium text-blue-900 underline">Ver foto privada</a>@endif</div>
            @empty <p class="mt-2 text-gray-500">Sin visitas registradas.</p>
            @endforelse
        </div>
        <div class="p-6 bg-white shadow-sm sm:rounded-lg"><h3 class="font-semibold text-lg">Incidencias</h3>
            @forelse ($seguimiento->incidencias as $incidencia)
                <div class="pt-3 mt-3 space-y-2 border-t"><p><span class="font-medium">Tipo:</span> {{ ucfirst(str_replace('_', ' ', $incidencia->tipo)) }}</p><p><span class="font-medium">Estado:</span> {{ ucfirst(str_replace('_', ' ', $incidencia->estado)) }}</p><p><span class="font-medium">Descripción:</span> {{ $incidencia->descripcion }}</p>@if ($incidencia->comentarios_resolucion)<p><span class="font-medium">Resolución:</span> {{ $incidencia->comentarios_resolucion }}</p>@endif
                    @can('progress', $incidencia)<form class="inline" method="POST" action="{{ route('admin.incidencias.progress', $incidencia) }}">@csrf @method('PATCH')<button class="text-indigo-600" title="Opcional: avisa al equipo que alguien ya se ocupa">Marcar en proceso (opcional)</button></form>@endcan
                    @can('attend', $incidencia)<form method="POST" action="{{ route('admin.seguimientos.incidencias.attend', [$seguimiento, $incidencia]) }}" class="space-y-2">@csrf @method('PATCH')<label class="block font-medium" for="comentarios_resolucion_{{ $incidencia->id }}">Resolución</label><input id="comentarios_resolucion_{{ $incidencia->id }}" name="comentarios_resolucion" required class="rounded border-gray-300" value="{{ old('comentarios_resolucion') }}">@error('comentarios_resolucion')<p class="text-sm text-red-600">{{ $message }}</p>@enderror<button class="text-green-600">Atender</button></form>@endcan
                </div>
            @empty <p class="mt-2 text-gray-500">Sin incidencias.</p>
            @endforelse
        </div>
    </div></div>
</x-app-layout>
