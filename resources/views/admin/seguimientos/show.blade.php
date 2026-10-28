@php
    $fecha = fn (string $f) => \Illuminate\Support\Carbon::parse($f)->format('d/m/Y');
    $tiposIncidencia = ['salud' => 'Salud', 'maltrato' => 'Posible maltrato', 'perdida_contacto' => 'Pérdida de contacto', 'otro' => 'Otro'];
    $visitasPorOrden = $seguimiento->visitas->sortBy('created_at')->values();
@endphp
<x-admin.pagina :titulo="'Seguimiento de '.$seguimiento->solicitud->mascota->nombre" seccion="Seguimientos" :volver="route('admin.seguimientos.index')" volver-texto="Volver a seguimientos">
    <x-slot name="subtitulo">
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <x-admin.estado de="seguimiento" :valor="$seguimiento->estado" />
            <a href="{{ route('admin.solicitudes-adopcion.show', $seguimiento->solicitud) }}" class="text-sm font-medium text-blue-900 hover:underline">Ver la solicitud de adopción</a>
        </div>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @if ($fechasSugeridas)
                <x-admin.tarjeta tono="ambar" titulo="Fechas sugeridas para visitas de seguimiento" descripcion="Se calculan desde la fecha real de entrega. Son sugerencias; cada visita se programa cuando coordines con la familia.">
                    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($fechasSugeridas as $numero => $fechas)
                            @php $visitaSugerida = $visitasPorOrden->get($numero - 1); @endphp
                            <li class="rounded-xl bg-white p-4 text-sm ring-1 ring-amber-200">
                                <p class="font-semibold text-blue-950">Visita {{ $numero }} · objetivo a los {{ $fechas['dias'] }} días: {{ $fecha($fechas['fecha']) }}</p>
                                <p class="mt-0.5 text-slate-600">(margen recomendado: {{ $fecha($fechas['desde']) }}–{{ $fecha($fechas['hasta']) }})</p>
                                <p class="mt-2">
                                    @if ($visitaSugerida)
                                        programada: {{ $visitaSugerida->fecha_programada->format('d/m/Y') }}
                                        @if (! \App\Support\FechasSeguimientoAdopcion::dentroDelMargen($visitaSugerida->fecha_programada->toDateString(), $fechas))<span class="font-semibold text-amber-800"> · Fuera del margen recomendado</span>@endif
                                    @else
                                        <span class="text-slate-500">sugerencia sin visita programada</span>
                                    @endif
                                </p>
                            </li>
                        @endforeach
                    </ul>
                </x-admin.tarjeta>
            @endif

            <x-admin.tarjeta titulo="Visitas">
                @forelse ($seguimiento->visitas as $visita)
                    <article class="{{ $loop->first ? '' : 'mt-4 border-t border-slate-100 pt-4' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-blue-950">Fecha programada: {{ $visita->fecha_programada->format('d/m/Y') }}</p>
                            <x-admin.estado de="visita" :valor="$visita->estado" />
                        </div>
                        <dl class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @if ($visita->fecha_realizada)<x-admin.dato etiqueta="Fecha realizada">{{ $visita->fecha_realizada->format('d/m/Y') }}</x-admin.dato>@endif
                            @if ($visita->estado_salud)<x-admin.dato etiqueta="Estado de salud">{{ $visita->estado_salud }}</x-admin.dato>@endif
                        </dl>
                        @if ($visita->observaciones)
                            <div class="mt-3 rounded-xl bg-slate-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cuestionario y observaciones:</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-slate-800">{{ $visita->observaciones }}</p>
                            </div>
                        @endif
                        @if ($visita->foto_url)<x-admin.boton variante="secundario" tamano="chico" class="mt-3" :href="route('admin.seguimientos.visitas.foto', [$seguimiento, $visita])" target="_blank" rel="noopener">Ver foto privada</x-admin.boton>@endif
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Sin visitas registradas.</p>
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
                            @can('progress', $incidencia)
                                <form method="POST" action="{{ route('admin.incidencias.progress', $incidencia) }}">@csrf @method('PATCH')
                                    <x-admin.boton variante="secundario" tamano="chico" title="Opcional: avisa al equipo que alguien ya se ocupa">Marcar en proceso (opcional)</x-admin.boton>
                                </form>
                            @endcan
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Sin incidencias.</p>
                @endforelse
            </x-admin.tarjeta>
        </div>

        <div class="min-w-0 space-y-6">
            <x-admin.tarjeta titulo="Información del seguimiento">
                <dl class="space-y-3">
                    <x-admin.dato etiqueta="Ciudadano">{{ $seguimiento->solicitud->solicitante->nombres }} {{ $seguimiento->solicitud->solicitante->apellidos }}</x-admin.dato>
                    <x-admin.dato etiqueta="Voluntario">{{ $seguimiento->voluntario ? $seguimiento->voluntario->nombres.' '.$seguimiento->voluntario->apellidos : 'Sin asignar' }}</x-admin.dato>
                    @if ($seguimiento->solicitud->entregada_at)<x-admin.dato etiqueta="Entrega">{{ $seguimiento->solicitud->entregada_at->timezone('America/La_Paz')->format('d/m/Y') }}</x-admin.dato>@endif
                    @if ($seguimiento->zona_aproximada)<x-admin.dato etiqueta="Zona">{{ $seguimiento->zona_aproximada }}</x-admin.dato>@endif
                    @if ($seguimiento->horario_preferido)<x-admin.dato etiqueta="Horario preferido">{{ $seguimiento->horario_preferido }}</x-admin.dato>@endif
                </dl>

                @can('assign', $seguimiento)
                    <form method="POST" action="{{ route('admin.seguimientos.assign', $seguimiento) }}" class="mt-4 border-t border-slate-100 pt-4">@csrf @method('PATCH')
                        <label for="voluntario_id" class="admin-etiqueta">Asignar voluntario</label>
                        <select id="voluntario_id" name="voluntario_id" required class="admin-campo">
                            @foreach ($voluntarios as $voluntario)<option value="{{ $voluntario->id }}" @selected(old('voluntario_id') == $voluntario->id)>{{ $voluntario->nombres }} {{ $voluntario->apellidos }}</option>@endforeach
                        </select>
                        @error('voluntario_id')<p class="admin-error">{{ $message }}</p>@enderror
                        <x-admin.boton class="mt-3 w-full">Asignar y activar</x-admin.boton>
                    </form>
                @endcan
                @can('close', $seguimiento)
                    @php($pendientesCierre = $seguimiento->pendientesParaCerrar())
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        @error('cierre')<p class="mb-3 rounded-xl bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror
                        @if ($pendientesCierre)
                            {{-- Sin botón: PostgreSQL rechazaría el cierre. Se dice qué falta. --}}
                            <div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-950">
                                <p class="font-semibold">Para cerrar el seguimiento:</p>
                                <ul class="mt-1 list-inside list-disc space-y-0.5">@foreach ($pendientesCierre as $pendiente)<li>{{ $pendiente }}</li>@endforeach</ul>
                            </div>
                        @else
                            <form method="POST" action="{{ route('admin.seguimientos.close', $seguimiento) }}">@csrf @method('PATCH')
                                <x-admin.boton variante="peligro-suave" class="w-full" x-data x-on:click="if (! confirm('¿Cerrar seguimiento?')) $event.preventDefault()">Cerrar seguimiento</x-admin.boton>
                                <p class="admin-ayuda">Al cerrarlo termina el acompañamiento de esta adopción.</p>
                            </form>
                        @endif
                    </div>
                @endcan
            </x-admin.tarjeta>
        </div>
    </div>
</x-admin.pagina>
