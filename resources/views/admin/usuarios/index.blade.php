<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestión de usuarios</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('admin.usuarios.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <x-input-label for="buscar" :value="__('Buscar')" />
                        <x-text-input id="buscar" name="buscar" type="search" class="mt-1 block w-full" :value="$filters['buscar'] ?? ''" placeholder="Nombres, apellidos o email" />
                    </div>
                    <div>
                        <x-input-label for="rol" :value="__('Rol')" />
                        <select id="rol" name="rol" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Todos</option>
                            @foreach (['administrador', 'voluntario', 'ciudadano'] as $role)
                                <option value="{{ $role }}" @selected(($filters['rol'] ?? '') === $role)>{{ ucfirst($role) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="estado" :value="__('Estado')" />
                        <select id="estado" name="estado" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Todos</option>
                            <option value="activo" @selected(($filters['estado'] ?? '') === 'activo')>Activo</option>
                            <option value="inactivo" @selected(($filters['estado'] ?? '') === 'inactivo')>Inactivo</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-3">
                        <x-primary-button>Filtrar</x-primary-button>
                        <a href="{{ route('admin.usuarios.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Limpiar</a>
                    </div>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombres</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Apellidos</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teléfono</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rol</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registro</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($users as $user)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $user->nombres }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $user->apellidos }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $user->email }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $user->telefono ?? '—' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ ucfirst($user->rol) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="rounded-full px-2 py-1 text-xs font-medium {{ $user->estado ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">{{ $user->estado ? 'Activo' : 'Inactivo' }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $user->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        @if ($user->hasRole('voluntario'))
                                            <span class="text-gray-400">Gestionar desde Voluntarios</span>
                                        @elseif (auth()->user()->can('updateStatus', $user))
                                            <form method="POST" action="{{ route('admin.usuarios.estado.update', $user) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="estado" value="{{ $user->estado ? 0 : 1 }}">
                                                <button class="text-indigo-600 hover:text-indigo-900">{{ $user->estado ? 'Desactivar' : 'Activar' }}</button>
                                            </form>
                                        @else
                                            <span class="text-gray-400">Sin cambios</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">No se encontraron usuarios.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="p-6">{{ $users->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
