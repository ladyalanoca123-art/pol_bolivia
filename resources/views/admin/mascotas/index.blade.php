<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestión de mascotas</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('admin.mascotas.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-6">
                    <div class="md:col-span-2">
                        <x-input-label for="buscar" :value="__('Buscar')" />
                        <x-text-input id="buscar" name="buscar" type="search" class="mt-1 block w-full" :value="$filters['buscar'] ?? ''" placeholder="Nombre de la mascota" />
                    </div>

                    <div>
                        <x-input-label for="especie" :value="__('Especie')" />
                        <select id="especie" name="especie" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Todas</option>
                            @foreach (['perro', 'gato'] as $species)
                                <option value="{{ $species }}" @selected(($filters['especie'] ?? '') === $species)>{{ ucfirst($species) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="sexo" :value="__('Sexo')" />
                        <select id="sexo" name="sexo" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Todos</option>
                            @foreach (['macho', 'hembra'] as $sex)
                                <option value="{{ $sex }}" @selected(($filters['sexo'] ?? '') === $sex)>{{ ucfirst($sex) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="tamano" :value="__('Tamaño')" />
                        <select id="tamano" name="tamano" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Todos</option>
                            @foreach (['pequeno', 'mediano', 'grande'] as $size)
                                <option value="{{ $size }}" @selected(($filters['tamano'] ?? '') === $size)>{{ ucfirst($size) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="estado" :value="__('Estado')" />
                        <select id="estado" name="estado" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Todos</option>
                            @foreach (['borrador', 'disponible', 'adoptada'] as $status)
                                <option value="{{ $status }}" @selected(($filters['estado'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-6 flex items-center gap-3">
                        <x-primary-button>Filtrar</x-primary-button>
                        <a href="{{ route('admin.mascotas.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Limpiar</a>
                    </div>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Foto</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Especie</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Raza</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Edad</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sexo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tamaño</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registro</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($mascotas as $mascota)
                                @php($fotoPrincipal = $mascota->fotos->first())
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($fotoPrincipal)
                                            <img src="{{ $fotoPrincipal->url }}" alt="{{ $mascota->nombre }}" class="h-12 w-12 rounded-md object-cover">
                                        @else
                                            <span class="text-sm text-gray-400">Sin foto</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $mascota->nombre }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ ucfirst($mascota->especie) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $mascota->raza ?? '—' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $mascota->edad }} años</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ ucfirst($mascota->sexo) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ ucfirst($mascota->tamano) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ ucfirst($mascota->estado) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $mascota->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <a href="#" class="text-indigo-600 hover:text-indigo-900" aria-disabled="true">Ver detalle</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="px-6 py-8 text-center text-sm text-gray-500">No se encontraron mascotas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($mascotas->hasPages())
                    <div class="p-6">{{ $mascotas->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
