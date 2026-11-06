@use('App\Support\CuestionarioVoluntariado', 'V')
@php $hayFiltros = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty(); @endphp
<x-admin.pagina titulo="Postulaciones de voluntariado" :contador="$postulaciones->total().($postulaciones->total() === 1 ? ' postulación' : ' postulaciones')" seccion="Voluntariado" descripcion="Revisa las postulaciones: al aprobar una, la persona pasa a ser voluntaria y puede recibir seguimientos.">
    <x-admin.tarjeta>
        {{-- El estado se elige con los botones de abajo (se conserva al buscar). --}}
        <form method="GET" action="{{ route('admin.postulaciones-voluntariado.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
            @if (filled($filters['estado'] ?? null))<input type="hidden" name="estado" value="{{ $filters['estado'] }}">@endif
            <div>
                <label for="area" class="admin-etiqueta">Área de interés</label>
                <select id="area" name="area" class="admin-campo">
                    <option value="">Todas</option>
                    @foreach (V::OPCIONES['areas_interes'] as $clave => $texto)<option value="{{ $clave }}" @selected(($filters['area'] ?? '') === $clave)>{{ $texto }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="dia" class="admin-etiqueta">Día disponible</label>
                <select id="dia" name="dia" class="admin-campo">
                    <option value="">Todos</option>
                    @foreach (V::OPCIONES['dias_disponibles'] as $clave => $texto)<option value="{{ $clave }}" @selected(($filters['dia'] ?? '') === $clave)>{{ $texto }}</option>@endforeach
                </select>
            </div>
            <div class="flex items-end gap-3">
                <x-admin.boton>Buscar</x-admin.boton>
                @if ($hayFiltros)<a href="{{ route('admin.postulaciones-voluntariado.index') }}" class="admin-boton-borde">Limpiar</a>@endif
            </div>
        </form>
    </x-admin.tarjeta>

    {{-- Estado: botones debajo de los filtros, como en las demás listas. --}}
    @php
        $opcionesEstado = collect([
            ['Todas', null, \App\Support\IconosPestanas::LISTA],
            ['Por revisar', 'pendiente', \App\Support\IconosPestanas::BANDEJA],
            ['Aprobadas', 'aprobada', \App\Support\IconosPestanas::CHECK],
            ['Rechazadas', 'rechazada', \App\Support\IconosPestanas::RECHAZO],
            ['Canceladas', 'cancelada', \App\Support\IconosPestanas::ARCHIVO],
        ])->map(fn ($o) => [$o[0], route('admin.postulaciones-voluntariado.index', array_filter([...$filters, 'estado' => $o[1]])), ($filters['estado'] ?? null) === $o[1], $o[2]])->all();
    @endphp
    <x-admin.pestanas etiqueta="Filtrar por estado" :opciones="$opcionesEstado" />

    @if ($postulaciones->isEmpty())
        <x-admin.tarjeta>
            <x-admin.vacio titulo="No hay postulaciones." :texto="$hayFiltros ? 'Prueba con otros filtros.' : 'Cuando un ciudadano postule como voluntario, aparecerá aquí.'" />
        </x-admin.tarjeta>
    @else
        @php
            $diasAlerta = (int) config('pol.panel.postulacion_dias_alerta');
            $chip = 'whitespace-nowrap rounded-full px-2.5 py-0.5 text-[13px] font-semibold';
            $coloresFinal = ['aprobada' => 'text-emerald-700', 'rechazada' => 'text-red-700'];
            $grupos = $postulaciones->getCollection()->groupBy(fn ($p) => $p->estado === 'pendiente' ? 'pendientes' : 'revisadas');
        @endphp

        {{-- Por revisar: fila blanca; borde ámbar si ya espera más de los días del aviso, azul si es reciente. --}}
        @if ($grupos->has('pendientes'))
            <section aria-labelledby="titulo-por-revisar" class="space-y-2">
                <h2 id="titulo-por-revisar" class="text-base font-semibold text-blue-950">Por revisar</h2>
                <ul class="space-y-2">
                    @foreach ($grupos['pendientes'] as $postulacion)
                        @php $demorada = $postulacion->created_at->lte(now()->subDays($diasAlerta)); @endphp
                        <li class="flex flex-col gap-3 rounded-xl border border-s-[6px] border-slate-200 {{ $demorada ? 'border-s-amber-400' : 'border-s-blue-500' }} bg-white px-4 py-3 shadow-sm transition hover:shadow-md sm:flex-row sm:items-center sm:px-5">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('admin.postulaciones-voluntariado.show', $postulacion) }}" class="text-base font-semibold text-blue-950 hover:underline">{{ $postulacion->usuario->nombres }} {{ $postulacion->usuario->apellidos }}</a>
                                <p class="text-sm text-slate-600">
                                    Quiere ser voluntario
                                    <span aria-hidden="true">·</span>
                                    <time datetime="{{ $postulacion->created_at->toIso8601String() }}" title="Enviada el {{ $postulacion->created_at->format('d/m/Y H:i') }}">{{ $postulacion->created_at->diffForHumans() }}</time>
                                    @if ($postulacion->zona_preferencia)<span aria-hidden="true">·</span> {{ $postulacion->zona_preferencia }}@endif
                                </p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    @if ($demorada)<span class="{{ $chip }} bg-amber-100 text-amber-900">Espera más de {{ $diasAlerta }} días</span>@endif
                                    @forelse ($postulacion->areas_interes ?? [] as $area)
                                        <span class="{{ $chip }} bg-slate-100 font-medium text-slate-700">{{ \App\Support\CuestionarioVoluntariado::OPCIONES['areas_interes'][$area] ?? $area }}</span>
                                    @empty
                                        <span class="text-[13px] text-slate-500">Sin áreas indicadas</span>
                                    @endforelse
                                </div>
                            </div>
                            <x-admin.boton class="w-full sm:w-auto" :href="route('admin.postulaciones-voluntariado.show', $postulacion)">Revisar<span class="sr-only"> la postulación de {{ $postulacion->usuario->nombres }}</span></x-admin.boton>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Revisadas: historial plegado («cajón cerrado»). Se abre solo si no hay pendientes en la página o se filtró por estado. --}}
        @if ($grupos->has('revisadas'))
            @php
                $cantidadRevisadas = $grupos['revisadas']->count();
                $abrirHistorial = ! $grupos->has('pendientes') || filled($filters['estado'] ?? null);
            @endphp
            <details class="group" @if ($abrirHistorial) open @endif>
                <summary class="inline-flex min-h-10 cursor-pointer list-none items-center gap-2 rounded-full border border-slate-300 bg-white px-4 text-sm font-semibold text-blue-950 shadow-sm hover:bg-blue-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                    <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M5 7l1 12a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1l1-12M9 4h6" /></svg>
                    <span class="group-open:hidden">Ver historial de revisadas</span><span class="hidden group-open:inline">Ocultar historial de revisadas</span>
                    ({{ $cantidadRevisadas }}{{ $postulaciones->hasPages() ? ' en esta página' : '' }})
                    <svg class="h-4 w-4 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4Z" clip-rule="evenodd"/></svg>
                </summary>
            <section aria-label="Postulaciones revisadas" class="mt-3 space-y-2">
                <ul class="space-y-1.5">
                    @foreach ($grupos['revisadas'] as $postulacion)
                        <li class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-slate-100/70 px-4 py-2 sm:flex-row sm:items-center sm:px-5">
                            <p class="min-w-0 flex-1 text-sm text-slate-600">
                                <a href="{{ route('admin.postulaciones-voluntariado.show', $postulacion) }}" class="font-semibold text-slate-800 hover:underline">{{ $postulacion->usuario->nombres }} {{ $postulacion->usuario->apellidos }}</a>
                                <span aria-hidden="true">·</span> <span class="font-semibold {{ $coloresFinal[$postulacion->estado] ?? 'text-slate-700' }}">{{ ucfirst($postulacion->estado) }}</span>@if ($postulacion->estado === 'aprobada' && $postulacion->voluntario) <a href="{{ route('admin.voluntarios.show', $postulacion->voluntario) }}" class="font-semibold text-blue-800 underline underline-offset-2 hover:text-blue-950">ya es voluntario →</a>@endif
                                <span aria-hidden="true">·</span> {{ ($postulacion->revisado_at ?? $postulacion->updated_at ?? $postulacion->created_at)->timezone('America/La_Paz')->format('d/m/Y') }}
                            </p>
                            <x-admin.boton variante="secundario" tamano="chico" class="self-start sm:self-center" :href="route('admin.postulaciones-voluntariado.show', $postulacion)">Ver detalle</x-admin.boton>
                        </li>
                    @endforeach
                </ul>
            </section>
            </details>
        @endif

        @if ($postulaciones->hasPages())<div>{{ $postulaciones->links() }}</div>@endif
    @endif
</x-admin.pagina>
