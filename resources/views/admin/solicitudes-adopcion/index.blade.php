@php
    $hayFiltros = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();
    $estadoActual = $filters['estado'] ?? null;
    // Accesos rápidos a las etapas en trámite.
    $rapidos = ['' => 'Todas', 'pendiente' => 'Recibidas', 'en_espera' => 'En evaluación', 'aprobada' => 'Reservadas', 'aceptada' => 'Concretadas'];
@endphp
<x-admin.pagina titulo="Solicitudes de adopción" seccion="Adopciones" descripcion="Primero se muestran las solicitudes en trámite (recibidas, en evaluación y reservadas), ordenadas por nivel de revisión. Las alertas ayudan a priorizar; no aprueban ni rechazan.">

    <nav aria-label="Filtrar por etapa" class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
        @foreach ($rapidos as $clave => $texto)
            @php($activo = ($estadoActual ?? '') === $clave)
            <a href="{{ route('admin.solicitudes-adopcion.index', array_filter([...$filters, 'estado' => $clave ?: null])) }}"
               @if ($activo) aria-current="page" @endif
               class="whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-semibold transition {{ $activo ? 'bg-blue-950 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">{{ $texto }}</a>
        @endforeach
    </nav>

    <x-admin.tarjeta>
        <form method="GET" action="{{ route('admin.solicitudes-adopcion.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2 lg:col-span-1">
                <label for="buscar" class="admin-etiqueta">Buscar ciudadano o mascota</label>
                <input id="buscar" name="buscar" type="search" class="admin-campo" value="{{ $filters['buscar'] ?? '' }}" placeholder="Nombre o apellido">
            </div>
            <div>
                <label for="estado" class="admin-etiqueta">Estado</label>
                <select id="estado" name="estado" class="admin-campo">
                    <option value="">Todos</option>
                    @foreach (\App\Models\SolicitudAdopcion::ESTADOS_LEGIBLES as $estado => $texto)
                        <option value="{{ $estado }}" @selected(($filters['estado'] ?? '') === $estado)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="nivel_alerta" class="admin-etiqueta">Revisión</label>
                <select id="nivel_alerta" name="nivel_alerta" class="admin-campo">
                    <option value="">Todas</option>
                    @foreach (\App\Models\SolicitudAdopcion::NIVELES_ALERTA as $nivel => $texto)
                        <option value="{{ $nivel }}" @selected(($filters['nivel_alerta'] ?? '') === $nivel)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-3">
                <x-admin.boton>Filtrar</x-admin.boton>
                @if ($hayFiltros)<a href="{{ route('admin.solicitudes-adopcion.index') }}" class="pb-2 text-sm font-semibold text-slate-600 hover:text-blue-950 hover:underline">Limpiar</a>@endif
            </div>
        </form>
    </x-admin.tarjeta>

    <x-admin.tarjeta :relleno="false">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-blue-950">Solicitudes</h2>
            <span class="text-sm text-slate-500">{{ $solicitudes->total() }} {{ $solicitudes->total() === 1 ? 'registro' : 'registros' }}</span>
        </div>
        @if ($solicitudes->isEmpty())
            <x-admin.vacio titulo="No se encontraron solicitudes." :texto="$hayFiltros ? 'Prueba con otro estado o búsqueda.' : 'Cuando un ciudadano envíe una solicitud, aparecerá aquí.'" />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($solicitudes as $solicitud)
                    <li class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:px-6">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('admin.solicitudes-adopcion.show', $solicitud) }}" class="font-semibold text-blue-950 hover:underline">{{ $solicitud->solicitante->nombres }} {{ $solicitud->solicitante->apellidos }}</a>
                                <span class="text-slate-400" aria-hidden="true">·</span>
                                <span class="text-sm text-slate-700">{{ $solicitud->mascota->nombre }}</span>
                            </div>
                            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                <x-admin.estado de="solicitud" :valor="$solicitud->estado" />
                                <x-nivel-alerta :nivel="$solicitud->nivel_alerta" :cantidad="count($solicitud->alertas ?? []) ?: null" />
                                <span class="text-xs text-slate-500">Recibida el {{ $solicitud->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                        </div>
                        <x-admin.boton variante="secundario" tamano="chico" :href="route('admin.solicitudes-adopcion.show', $solicitud)" class="self-start sm:self-center">Ver detalle</x-admin.boton>
                    </li>
                @endforeach
            </ul>
            @if ($solicitudes->hasPages())<div class="border-t border-slate-100 px-4 py-3 sm:px-6">{{ $solicitudes->links() }}</div>@endif
        @endif
    </x-admin.tarjeta>
</x-admin.pagina>
