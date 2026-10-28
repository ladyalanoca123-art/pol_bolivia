@php
    $tonosRol = ['administrador' => 'indigo', 'voluntario' => 'ambar', 'ciudadano' => 'gris'];
    $hayFiltros = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();
@endphp
<x-admin.pagina titulo="Gestión de usuarios" seccion="Usuarios" descripcion="Consulta, edita y activa o desactiva las cuentas del sistema.">
    <x-admin.tarjeta>
        <form method="GET" action="{{ route('admin.usuarios.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-12 lg:items-end">
            <div class="sm:col-span-2 lg:col-span-5">
                <label for="buscar" class="admin-etiqueta">Buscar por nombre, apellido o correo</label>
                <input id="buscar" name="buscar" type="search" class="admin-campo" value="{{ $filters['buscar'] ?? '' }}" placeholder="Nombre, apellido o correo">
            </div>
            <div class="lg:col-span-2">
                <label for="rol" class="admin-etiqueta">Rol</label>
                <select id="rol" name="rol" class="admin-campo">
                    <option value="">Todos</option>
                    @foreach (['administrador', 'voluntario', 'ciudadano'] as $role)<option value="{{ $role }}" @selected(($filters['rol'] ?? '') === $role)>{{ ucfirst($role) }}</option>@endforeach
                </select>
            </div>
            <div class="lg:col-span-2">
                <label for="estado" class="admin-etiqueta">Estado</label>
                <select id="estado" name="estado" class="admin-campo">
                    <option value="">Todos</option>
                    <option value="activo" @selected(($filters['estado'] ?? '') === 'activo')>Activo</option>
                    <option value="inactivo" @selected(($filters['estado'] ?? '') === 'inactivo')>Inactivo</option>
                </select>
            </div>
            <div class="flex items-center gap-3 sm:col-span-2 lg:col-span-3 lg:justify-end">
                <x-admin.boton>Buscar</x-admin.boton>
                @if ($hayFiltros)<x-admin.boton variante="secundario" :href="route('admin.usuarios.index')">Limpiar</x-admin.boton>@endif
            </div>
        </form>
    </x-admin.tarjeta>

    <x-admin.tarjeta :relleno="false">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-blue-950">Usuarios</h2>
            <span class="text-sm text-slate-500">{{ $users->total() }} {{ $users->total() === 1 ? 'registro' : 'registros' }}</span>
        </div>
        @if ($users->isEmpty())
            <x-admin.vacio titulo="No se encontraron usuarios." texto="Prueba con otra búsqueda o con otros filtros." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <li class="flex flex-col gap-3 px-4 py-4 sm:px-6 lg:flex-row lg:items-center">
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-950 text-xs font-bold text-amber-300">{{ mb_strtoupper(mb_substr($user->nombres, 0, 1).mb_substr($user->apellidos ?? '', 0, 1)) }}</span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('admin.usuarios.show', $user) }}" class="font-semibold text-blue-950 hover:underline">{{ $user->nombres }} {{ $user->apellidos }}</a>
                                    <x-admin.insignia :tono="$tonosRol[$user->rol] ?? 'gris'" :punto="false">{{ ucfirst($user->rol) }}</x-admin.insignia>
                                    @if ($user->es_principal)<x-admin.insignia tono="ambar" :punto="false">Administrador principal</x-admin.insignia>@endif
                                    <x-admin.estado de="usuario" :valor="(bool) $user->estado" />
                                </div>
                                <p class="mt-0.5 break-all text-sm text-slate-600">{{ $user->email }}@if ($user->telefono) <span class="text-slate-400" aria-hidden="true">·</span> {{ $user->telefono }}@endif</p>
                                <p class="text-xs text-slate-500">Registro: {{ $user->created_at->format('d/m/Y') }} {{ $user->created_at->format('H:i') }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                            <x-admin.boton variante="secundario" tamano="chico" :href="route('admin.usuarios.show', $user)">Ver</x-admin.boton>
                            @can('update', $user)
                                <x-admin.boton variante="secundario" tamano="chico" :href="route('admin.usuarios.edit', $user)">Editar</x-admin.boton>
                            @endcan
                            @if ($user->hasRole('voluntario'))
                                <a href="{{ route('admin.voluntarios.index') }}" class="px-1 text-xs text-slate-500 hover:text-blue-900 hover:underline" title="El estado de un voluntario se gestiona desde Voluntarios">Gestionar en Voluntarios</a>
                            @elseif (auth()->user()->can('updateStatus', $user))
                                <form method="POST" action="{{ route('admin.usuarios.estado.update', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="estado" value="{{ $user->estado ? 0 : 1 }}">
                                    @if ($user->estado)
                                        <x-admin.boton variante="peligro-suave" tamano="chico">Desactivar</x-admin.boton>
                                    @else
                                        <x-admin.boton variante="secundario" tamano="chico" class="!border-emerald-300 !text-emerald-700">Activar</x-admin.boton>
                                    @endif
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($users->hasPages())<div class="border-t border-slate-100 px-4 py-3 sm:px-6">{{ $users->links() }}</div>@endif
        @endif
    </x-admin.tarjeta>
</x-admin.pagina>
