<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Seguimientos de adopción</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr class="text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                <th class="px-4 py-3">Mascota</th>
                                <th class="px-4 py-3">Ciudadano</th>
                                <th class="px-4 py-3">Voluntario</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3"><span class="sr-only">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($seguimientos as $seguimiento)
                                <tr>
                                    <td class="px-4 py-3">{{ $seguimiento->solicitud->mascota->nombre }}</td>
                                    <td class="px-4 py-3">{{ $seguimiento->solicitud->solicitante->nombres }} {{ $seguimiento->solicitud->solicitante->apellidos }}</td>
                                    <td class="px-4 py-3">{{ $seguimiento->voluntario?->nombres ?? 'Sin asignar' }}</td>
                                    <td class="px-4 py-3">{{ ucfirst(str_replace('_', ' ', $seguimiento->estado)) }}</td>
                                    <td class="px-4 py-3"><a class="text-indigo-600 hover:text-indigo-900" href="{{ route('admin.seguimientos.show', $seguimiento) }}">Ver detalle</a></td>
                                </tr>
                            @empty
                                <tr><td class="px-4 py-6 text-gray-500" colspan="5">No hay seguimientos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $seguimientos->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
