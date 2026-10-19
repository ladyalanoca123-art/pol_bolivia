<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestión de usuarios</h2>
        <p class="mt-1 text-sm text-gray-600">Consulta, edita y activa o desactiva las cuentas del sistema.</p>
    </x-slot>

    @php
        $coloresRol = [
            'administrador' => 'bg-blue-100 text-blue-900 ring-blue-300',
            'voluntario' => 'bg-amber-100 text-amber-900 ring-amber-300',
            'ciudadano' => 'bg-gray-100 text-gray-700 ring-gray-300',
        ];
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md border-l-4 border-emerald-400 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif

            {{-- Búsqueda y filtros --}}
            <div class="rounded-2xl bg-white p-6 shadow-sm">
                <form method="GET" action="{{ route('admin.usuarios.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-12 md:items-end">
                    <div class="md:col-span-5">
                        <x-input-label for="buscar" :value="__('Buscar por nombre, apellido o correo')" />
                        <x-text-input id="buscar" name="buscar" type="search" class="mt-1 block w-full" :value="$filters['buscar'] ?? ''" placeholder="Nombre, apellido o correo" />
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="rol" :value="__('Rol')" />
                        <select id="rol" name="rol" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-800 focus:ring-blue-800">
                            <option value="">Todos</option>
                            @foreach (['administrador', 'voluntario', 'ciudadano'] as $role)
                                <option value="{{ $role }}" @selected(($filters['rol'] ?? '') === $role)>{{ ucfirst($role) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="estado" :value="__('Estado')" />
                        <select id="estado" name="estado" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-800 focus:ring-blue-800">
                            <option value="">Todos</option>
                            <option value="activo" @selected(($filters['estado'] ?? '') === 'activo')>Activo</option>
                            <option value="inactivo" @selected(($filters['estado'] ?? '') === 'inactivo')>Inactivo</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-3 md:col-span-3 md:justify-end">
                        <x-primary-button class="tracking-wide">BUSCAR</x-primary-button>
                        <a href="{{ route('admin.usuarios.index') }}" class="rounded-full border border-gray-300 px-5 py-2.5 text-sm font-bold tracking-wide text-gray-700 hover:bg-gray-50">LIMPIAR</a>
                    </div>
                </form>
            </div>

            {{-- Listado --}}
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-base font-bold text-blue-950">Usuarios</h3>
                    <span class="text-sm text-gray-500">{{ $users->total() }} {{ $users->total() === 1 ? 'registro' : 'registros' }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Usuario</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Teléfono</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Rol</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Estado</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Registro</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($users as $user)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-950 text-xs font-bold text-amber-300" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->nombres, 0, 1).mb_substr($user->apellidos ?? '', 0, 1)) }}</div>
                                            <div class="min-w-0">
                                                <div class="font-semibold text-gray-900">{{ $user->nombres }} {{ $user->apellidos }}</div>
                                                <div class="break-all text-gray-500">{{ $user->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-600">{{ $user->telefono ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-4 text-sm">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $coloresRol[$user->rol] ?? 'bg-gray-100 text-gray-700 ring-gray-300' }}">{{ ucfirst($user->rol) }}</span>
                                        @if ($user->es_principal)
                                            <span class="ml-1 inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-300">Administrador principal</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-sm">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $user->estado ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $user->estado ? 'bg-emerald-500' : 'bg-gray-400' }}" aria-hidden="true"></span>
                                            {{ $user->estado ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-600">
                                        {{ $user->created_at->format('d/m/Y') }}
                                        <div class="text-xs text-gray-400">{{ $user->created_at->format('H:i') }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            <a href="{{ route('admin.usuarios.show', $user) }}" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-blue-900 hover:bg-gray-50">Ver</a>
                                            @can('update', $user)
                                                <a href="{{ route('admin.usuarios.edit', $user) }}" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-blue-900 hover:bg-gray-50">Editar</a>
                                            @endcan
                                            @if ($user->hasRole('voluntario'))
                                                <a href="{{ route('admin.voluntarios.index') }}" class="px-1 text-xs text-gray-500 hover:text-blue-900 hover:underline" title="El estado de un voluntario se gestiona desde Voluntarios">Gestionar en Voluntarios</a>
                                            @elseif (auth()->user()->can('updateStatus', $user))
                                                <form method="POST" action="{{ route('admin.usuarios.estado.update', $user) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="estado" value="{{ $user->estado ? 0 : 1 }}">
                                                    @if ($user->estado)
                                                        <button class="rounded-full border border-red-300 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-50">Desactivar</button>
                                                    @else
                                                        <button class="rounded-full border border-emerald-300 px-3 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Activar</button>
                                                    @endif
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">No se encontraron usuarios.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="border-t border-gray-100 px-6 py-4">{{ $users->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
