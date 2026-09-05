<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalle de solicitud</h2></x-slot>
    <div class="py-12"><div class="max-w-3xl mx-auto sm:px-6 lg:px-8"><div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div><dt class="font-medium text-gray-500">Mascota</dt><dd class="mt-1 text-gray-900">{{ $solicitud->mascota->nombre }}</dd></div>
            <div><dt class="font-medium text-gray-500">Estado</dt><dd class="mt-1 text-gray-900">{{ ucfirst(str_replace('_', ' ', $solicitud->estado)) }}</dd></div>
            <div><dt class="font-medium text-gray-500">Fecha de solicitud</dt><dd class="mt-1 text-gray-900">{{ $solicitud->created_at->format('d/m/Y H:i') }}</dd></div>
            <div><dt class="font-medium text-gray-500">Zona aproximada</dt><dd class="mt-1 text-gray-900">{{ $solicitud->zona_aproximada }}</dd></div>
            <div><dt class="font-medium text-gray-500">Horario preferido</dt><dd class="mt-1 text-gray-900">{{ $solicitud->horario_preferido }}</dd></div>
            <div><dt class="font-medium text-gray-500">Otras mascotas</dt><dd class="mt-1 text-gray-900">{{ $solicitud->tiene_otras_mascotas ? 'Sí' : 'No' }}</dd></div>
            <div><dt class="font-medium text-gray-500">Otras personas en el hogar</dt><dd class="mt-1 text-gray-900">{{ $solicitud->viven_otras_personas ? 'Sí' : 'No' }}</dd></div>
            <div><dt class="font-medium text-gray-500">Espacio adecuado</dt><dd class="mt-1 text-gray-900">{{ $solicitud->tiene_espacio_adecuado ? 'Sí' : 'No' }}</dd></div>
            <div class="sm:col-span-2"><dt class="font-medium text-gray-500">Comentarios</dt><dd class="mt-1 whitespace-pre-line text-gray-900">{{ $solicitud->comentarios_solicitante ?? '—' }}</dd></div>
        </dl>
        <a href="{{ route('solicitudes-adopcion.index') }}" class="mt-6 inline-block text-sm text-gray-600 hover:text-gray-900">Volver a mis solicitudes</a>
    </div></div></div>
</x-app-layout>
