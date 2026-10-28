@use('App\Support\CuestionarioVoluntariado', 'V')
@php($hayFiltros = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty())
<x-admin.pagina titulo="Postulaciones de voluntariado" seccion="Voluntariado" descripcion="Revisa las postulaciones: al aprobar una, la persona pasa a ser voluntaria y puede recibir seguimientos.">
    <x-admin.tarjeta>
        <form method="GET" action="{{ route('admin.postulaciones-voluntariado.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="estado" class="admin-etiqueta">Estado</label>
                <select id="estado" name="estado" class="admin-campo">
                    <option value="">Todos</option>
                    @foreach (['pendiente', 'aprobada', 'rechazada', 'cancelada'] as $estado)<option value="{{ $estado }}" @selected(($filters['estado'] ?? '') === $estado)>{{ ucfirst($estado) }}</option>@endforeach
                </select>
            </div>
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
                <x-admin.boton>Filtrar</x-admin.boton>
                @if ($hayFiltros)<a href="{{ route('admin.postulaciones-voluntariado.index') }}" class="pb-2 text-sm font-semibold text-slate-600 hover:text-blue-950 hover:underline">Limpiar</a>@endif
            </div>
        </form>
    </x-admin.tarjeta>

    <x-admin.tarjeta :relleno="false">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-blue-950">Postulaciones</h2>
            <span class="text-sm text-slate-500">{{ $postulaciones->total() }} {{ $postulaciones->total() === 1 ? 'registro' : 'registros' }}</span>
        </div>
        @if ($postulaciones->isEmpty())
            <x-admin.vacio titulo="No hay postulaciones." :texto="$hayFiltros ? 'Prueba con otros filtros.' : 'Cuando un ciudadano postule como voluntario, aparecerá aquí.'" />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($postulaciones as $postulacion)
                    <li class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:px-6">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('admin.postulaciones-voluntariado.show', $postulacion) }}" class="font-semibold text-blue-950 hover:underline">{{ $postulacion->usuario->nombres }} {{ $postulacion->usuario->apellidos }}</a>
                                <x-admin.estado de="postulacion" :valor="$postulacion->estado" />
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ V::etiquetas('areas_interes', $postulacion->areas_interes) ?: '—' }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $postulacion->disponibilidad }}@if ($postulacion->disponibilidad) · @endif Enviada el {{ $postulacion->created_at->format('d/m/Y') }}</p>
                        </div>
                        <x-admin.boton :variante="$postulacion->estado === 'pendiente' ? 'primario' : 'secundario'" tamano="chico" :href="route('admin.postulaciones-voluntariado.show', $postulacion)" class="self-start sm:self-center">
                            {{ $postulacion->estado === 'pendiente' ? 'Revisar' : 'Ver detalle' }}
                        </x-admin.boton>
                    </li>
                @endforeach
            </ul>
            @if ($postulaciones->hasPages())<div class="border-t border-slate-100 px-4 py-3 sm:px-6">{{ $postulaciones->links() }}</div>@endif
        @endif
    </x-admin.tarjeta>
</x-admin.pagina>
