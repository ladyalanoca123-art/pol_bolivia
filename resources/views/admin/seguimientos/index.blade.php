@php
    $hayFiltros = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();
@endphp
<x-admin.pagina titulo="Seguimientos de adopción" :contador="$seguimientos->total().($seguimientos->total() === 1 ? ' seguimiento' : ' seguimientos')" seccion="Seguimientos" descripcion="Cada adopción concretada tiene su seguimiento: asigna un voluntario, revisa sus visitas e incidencias y ciérralo al terminar.">
    <x-admin.tarjeta>
        <form method="GET" action="{{ route('admin.seguimientos.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]">
            <div class="sm:col-span-2 lg:col-span-1">
                <label for="buscar" class="admin-etiqueta">Buscar</label>
                <input id="buscar" name="buscar" type="search" class="admin-campo" value="{{ $filters['buscar'] ?? '' }}" placeholder="Mascota o familia">
            </div>
            {{-- El estado se elige con los botones de abajo (se conserva al buscar). --}}
            @if (filled($filters['estado'] ?? null))<input type="hidden" name="estado" value="{{ $filters['estado'] }}">@endif
            <div>
                <label for="voluntario" class="admin-etiqueta">Voluntario</label>
                <select id="voluntario" name="voluntario" class="admin-campo">
                    <option value="">Todos</option>
                    @foreach ($voluntarios as $voluntario)
                        <option value="{{ $voluntario->id }}" @selected((string) ($filters['voluntario'] ?? '') === (string) $voluntario->id)>{{ $voluntario->nombres }} {{ $voluntario->apellidos }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-1">
                <x-admin.boton>Buscar</x-admin.boton>
                @if ($hayFiltros)<a href="{{ route('admin.seguimientos.index') }}" class="admin-boton-borde">Limpiar</a>@endif
            </div>
        </form>
    </x-admin.tarjeta>

    {{-- Estado: botones debajo de los filtros, como en las demás listas. --}}
    @php
        $opcionesEstado = collect([
            ['Todos', null, \App\Support\IconosPestanas::LISTA],
            ['Sin asignar', 'sin_asignar', \App\Support\IconosPestanas::PERSONA],
            ['Activos', 'activo', \App\Support\IconosPestanas::SEGUIMIENTOS],
            ['Cerrados', 'cerrado', \App\Support\IconosPestanas::ARCHIVO],
        ])->map(fn ($o) => [$o[0], route('admin.seguimientos.index', array_filter([...$filters, 'estado' => $o[1]])), ($filters['estado'] ?? null) === $o[1], $o[2]])->all();
    @endphp
    <x-admin.pestanas etiqueta="Filtrar por estado" :opciones="$opcionesEstado" />

    @if ($seguimientos->isEmpty())
        <x-admin.tarjeta>
            <x-admin.vacio :titulo="$hayFiltros ? 'No se encontraron seguimientos.' : 'No hay seguimientos.'" :texto="$hayFiltros ? 'Prueba con otra búsqueda o quita los filtros.' : 'Se crean automáticamente al registrar la entrega de una adopción.'" />
        </x-admin.tarjeta>
    @else
        {{-- Una fila por seguimiento (en Mascotas son tarjetas con foto grande; aquí importa leer el estado de cada caso). --}}
        <ul class="space-y-3">
            @foreach ($seguimientos as $seguimiento)
                @php
                    $mascota = $seguimiento->solicitud->mascota;
                    $foto = $mascota->fotos->firstWhere('es_principal', true) ?? $mascota->fotos->first();
                    $sinVoluntario = $seguimiento->estado === 'sin_asignar';
                    $pendientes = $seguimiento->incidencias_pendientes_count;
                    $icono = 'h-5 w-5 shrink-0 text-slate-500';
                @endphp
                <li class="flex flex-col gap-4 rounded-2xl border p-3 shadow-sm transition hover:shadow-md sm:flex-row sm:flex-wrap sm:items-center xl:flex-nowrap {{ $sinVoluntario ? 'border-amber-300 bg-amber-50/70' : 'border-slate-200 bg-white hover:border-blue-200' }}">
                    <a href="{{ route('admin.seguimientos.show', $seguimiento) }}" class="block shrink-0" tabindex="-1" aria-hidden="true">
                        @if ($foto)
                            <img src="{{ $foto->url_miniatura }}" alt="" class="aspect-[4/3] w-full rounded-xl bg-slate-100 object-cover sm:h-24 sm:w-32">
                        @else
                            <span class="flex aspect-[4/3] w-full items-center justify-center rounded-xl bg-slate-100 text-4xl sm:h-24 sm:w-32">{{ $mascota->especie === 'gato' ? '🐱' : '🐶' }}</span>
                        @endif
                    </a>

                    {{-- Mascota y familia --}}
                    <div class="min-w-[13rem] flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('admin.seguimientos.show', $seguimiento) }}" class="text-lg font-bold text-blue-950 hover:underline">{{ $mascota->nombre }}</a>
                            <x-admin.estado de="seguimiento" :valor="$seguimiento->estado" />
                        </div>
                        <p class="mt-1 flex items-center gap-2 text-sm text-slate-700">
                            <svg class="{{ $icono }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0" /></svg>
                            <span class="min-w-0">Familia: {{ $seguimiento->solicitud->solicitante->nombres }} {{ $seguimiento->solicitud->solicitante->apellidos }}</span>
                        </p>
                        @if ($seguimiento->solicitud->entregada_at)
                            <p class="mt-0.5 flex items-center gap-2 text-sm text-slate-700">
                                <svg class="{{ $icono }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" /></svg>
                                Entrega: {{ $seguimiento->solicitud->entregada_at->timezone('America/La_Paz')->format('d/m/Y') }}
                            </p>
                        @endif
                    </div>

                    {{-- Voluntario, visitas e incidencias --}}
                    <div class="min-w-[15rem] flex-1 space-y-0.5 border-slate-200 text-sm text-slate-700 sm:border-s sm:ps-5">
                        <p class="flex items-center gap-2">
                            <svg class="{{ $icono }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0" /></svg>
                            <span class="min-w-0">Voluntario:
                                @if ($seguimiento->voluntario)
                                    {{ $seguimiento->voluntario->nombres }} {{ $seguimiento->voluntario->apellidos }}
                                @else
                                    <span class="font-semibold text-amber-800">Sin asignar</span>
                                @endif
                            </span>
                        </p>
                        <p class="flex items-center gap-2">
                            <svg class="{{ $icono }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" /></svg>
                            Visitas: {{ $seguimiento->visitas_count }}
                        </p>
                        <p class="flex items-center gap-2">
                            <svg class="{{ $icono }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6l-8-3Z" /></svg>
                            Incidencias:
                            @if ($pendientes > 0)
                                <span class="whitespace-nowrap rounded-full bg-red-100 px-2 py-0.5 text-[13px] font-semibold text-red-800">{{ $pendientes }} {{ $pendientes === 1 ? 'pendiente' : 'pendientes' }}</span>
                            @else
                                <span class="whitespace-nowrap rounded-full bg-emerald-100 px-2 py-0.5 text-[13px] font-semibold text-emerald-800">Sin pendientes</span>
                            @endif
                        </p>
                    </div>

                    <div class="shrink-0 sm:w-full sm:text-end xl:w-52">
                        @if ($sinVoluntario)
                            <x-admin.boton variante="destacado" class="w-full sm:w-auto" :href="route('admin.seguimientos.show', $seguimiento)">Asignar voluntario</x-admin.boton>
                        @else
                            <x-admin.boton variante="secundario" class="w-full sm:w-auto" :href="route('admin.seguimientos.show', $seguimiento)">Ver detalle<span class="sr-only"> de {{ $mascota->nombre }}</span></x-admin.boton>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($seguimientos->hasPages())<div>{{ $seguimientos->links() }}</div>@endif
    @endif
</x-admin.pagina>
