<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Solicitudes de adopción</h2></x-slot>
    <div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @php
            $coloresEstado = [
                'pendiente' => 'bg-amber-100 text-amber-900 ring-amber-300',
                'en_espera' => 'bg-blue-100 text-blue-900 ring-blue-300',
                'aprobada' => 'bg-indigo-100 text-indigo-900 ring-indigo-300',
                'aceptada' => 'bg-green-100 text-green-900 ring-green-300',
                'rechazada' => 'bg-red-100 text-red-900 ring-red-300',
                'no_concretada' => 'bg-amber-100 text-amber-900 ring-amber-300',
                'cancelada' => 'bg-gray-100 text-gray-700 ring-gray-300',
                'cerrada' => 'bg-gray-100 text-gray-700 ring-gray-300',
            ];
        @endphp
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <form method="GET" action="{{ route('admin.solicitudes-adopcion.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div><x-input-label for="buscar" value="Buscar ciudadano o mascota" /><x-text-input id="buscar" name="buscar" type="search" class="mt-1 block w-full" :value="$filters['buscar'] ?? ''" /></div>
                <div><x-input-label for="estado" value="Estado" /><select id="estado" name="estado" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">Todos</option>@foreach (\App\Models\SolicitudAdopcion::ESTADOS_LEGIBLES as $estado => $texto)<option value="{{ $estado }}" @selected(($filters['estado'] ?? '') === $estado)>{{ $texto }}</option>@endforeach</select></div>
                <div><x-input-label for="nivel_alerta" value="Revisión" /><select id="nivel_alerta" name="nivel_alerta" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">Todas</option>@foreach (\App\Models\SolicitudAdopcion::NIVELES_ALERTA as $nivel => $texto)<option value="{{ $nivel }}" @selected(($filters['nivel_alerta'] ?? '') === $nivel)>{{ $texto }}</option>@endforeach</select></div>
                <div class="flex items-end gap-3"><x-primary-button>Filtrar</x-primary-button><a href="{{ route('admin.solicitudes-adopcion.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Limpiar</a></div>
            </form>
            <p class="mt-3 text-xs text-gray-500">Primero se muestran las solicitudes en trámite (recibidas, en evaluación y reservadas), ordenadas por nivel de revisión: 🔴, 🟡 y 🟢. Las alertas ayudan a priorizar; no aprueban ni rechazan.</p>
        </div>
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ciudadano</th><th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Mascota</th><th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th><th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Revisión</th><th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fecha</th><th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Detalle</th></tr></thead>
            <tbody class="divide-y divide-gray-200 bg-white">@forelse ($solicitudes as $solicitud)
                <tr><td class="px-6 py-4 text-sm text-gray-900">{{ $solicitud->solicitante->nombres }} {{ $solicitud->solicitante->apellidos }}</td><td class="px-6 py-4 text-sm text-gray-600">{{ $solicitud->mascota->nombre }}</td><td class="px-6 py-4 text-sm"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $coloresEstado[$solicitud->estado] ?? 'bg-gray-100 text-gray-700 ring-gray-300' }}">{{ $solicitud->estadoLegible() }}</span></td><td class="px-6 py-4 text-sm"><x-nivel-alerta :nivel="$solicitud->nivel_alerta" :cantidad="count($solicitud->alertas ?? []) ?: null" /></td><td class="px-6 py-4 text-sm text-gray-600">{{ $solicitud->created_at->format('d/m/Y H:i') }}</td><td class="px-6 py-4 text-right text-sm"><a href="{{ route('admin.solicitudes-adopcion.show', $solicitud) }}" class="text-indigo-600 hover:text-indigo-900">Ver detalle</a></td></tr>
            @empty<tr><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No se encontraron solicitudes.</td></tr>@endforelse</tbody>
        </table></div>@if ($solicitudes->hasPages())<div class="p-6">{{ $solicitudes->links() }}</div>@endif</div>
    </div></div>
</x-app-layout>
