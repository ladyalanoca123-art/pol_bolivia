<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Mis solicitudes de adopción</h2></x-slot>
    <div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))<div class="mb-6 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>@endif
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Mascota</th><th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th><th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fecha</th><th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Detalle</th></tr></thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse ($solicitudes as $solicitud)
                    <tr><td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $solicitud->mascota->nombre }}</td><td class="px-6 py-4 text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $solicitud->estado)) }}</td><td class="px-6 py-4 text-sm text-gray-600">{{ $solicitud->created_at->format('d/m/Y H:i') }}</td><td class="px-6 py-4 text-right text-sm"><a class="text-indigo-600 hover:text-indigo-900" href="{{ route('solicitudes-adopcion.show', $solicitud) }}">Ver detalle</a></td></tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">Todavía no tienes solicitudes de adopción.</td></tr>
                @endforelse
            </tbody>
        </table></div>@if ($solicitudes->hasPages())<div class="p-6">{{ $solicitudes->links() }}</div>@endif</div>
    </div></div>
</x-app-layout>
