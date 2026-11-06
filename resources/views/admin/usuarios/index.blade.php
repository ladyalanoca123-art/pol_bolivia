@php
    $tonosRol = ['administrador' => 'indigo', 'voluntario' => 'ambar', 'ciudadano' => 'gris'];
    $hayFiltros = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();
@endphp
<x-admin.pagina titulo="Gestión de usuarios" :contador="$users->total().($users->total() === 1 ? ' usuario' : ' usuarios')" seccion="Usuarios" descripcion="Consulta, edita y activa o desactiva las cuentas del sistema.">
    {{-- Accesos rápidos por rol y a las cuentas inactivas (usan los mismos filtros de abajo). --}}
    @php
        $rolActual = $filters['rol'] ?? null;
        $estadoActual = $filters['estado'] ?? null;
        $pestanas = [
            ['Todos', null, null],
            ['Administradores', 'administrador', null],
            ['Voluntarios', 'voluntario', null],
            ['Ciudadanos', 'ciudadano', null],
            ['Inactivos', null, 'inactivo'],
        ];
    @endphp
    <nav aria-label="Filtrar por rol" class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
        @foreach ($pestanas as [$texto, $rol, $estado])
            @php $activa = $rolActual === $rol && $estadoActual === $estado; @endphp
            <a href="{{ route('admin.usuarios.index', array_filter(['buscar' => $filters['buscar'] ?? null, 'rol' => $rol, 'estado' => $estado])) }}"
               @if ($activa) aria-current="page" @endif
               class="inline-flex min-h-10 items-center whitespace-nowrap rounded-full border px-4 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 {{ $activa ? 'border-blue-950 bg-blue-950 text-white' : 'border-slate-300 bg-white text-blue-950 hover:border-blue-900 hover:bg-blue-50' }}">{{ $texto }}</a>
        @endforeach
    </nav>

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
        @if ($users->isEmpty())
            <x-admin.vacio titulo="No se encontraron usuarios." texto="Prueba con otra búsqueda o con otros filtros." />
        @else
            @php
                // Color del círculo según el rol.
                $circulos = ['administrador' => 'bg-indigo-800 text-white', 'voluntario' => 'bg-amber-400 text-blue-950', 'ciudadano' => 'bg-slate-300 text-slate-800'];
                // Botón de ícono: redondo, con su nombre al pasar el mouse (y en texto oculto para lectores de pantalla).
                $icono = 'group relative inline-flex h-10 w-10 items-center justify-center rounded-full border bg-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2';
                $globo = 'pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-blue-950 px-2 py-1 text-xs font-medium text-white shadow group-hover:block group-focus-visible:block';
            @endphp
            <ul class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    @php
                        $nombre = $user->nombres.' '.$user->apellidos;
                        $pregunta = $user->estado
                            ? '¿Desactivar la cuenta de '.$nombre.'? No podrá iniciar sesión. Sus datos se conservan y puedes volver a activarla después.'
                            : '¿Activar la cuenta de '.$nombre.'? Podrá volver a iniciar sesión.';
                    @endphp
                    <li class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:px-6 {{ $user->estado ? '' : 'bg-slate-50' }}">
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $user->estado ? ($circulos[$user->rol] ?? 'bg-slate-300 text-slate-800') : 'bg-slate-200 text-slate-400' }}">{{ mb_strtoupper(mb_substr($user->nombres, 0, 1)) }}</span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <a href="{{ route('admin.usuarios.show', $user) }}" class="font-semibold hover:underline {{ $user->estado ? 'text-blue-950' : 'text-slate-500' }}">{{ $nombre }}</a>
                                    @if ($user->es_principal)
                                        <x-admin.insignia tono="indigo" :punto="false">Administrador principal</x-admin.insignia>
                                    @else
                                        <x-admin.insignia :tono="$tonosRol[$user->rol] ?? 'gris'" :punto="false">{{ ucfirst($user->rol) }}</x-admin.insignia>
                                    @endif
                                    @unless ($user->estado)<x-admin.insignia tono="rojo" :punto="false">Inactivo</x-admin.insignia>@endunless
                                </div>
                                <p class="mt-0.5 break-all text-[13px] text-slate-500">{{ $user->email }}@if ($user->telefono) · {{ $user->telefono }}@endif · desde {{ $user->created_at->format('d/m/Y') }}</p>
                            </div>
                        </div>

                        {{-- Acciones con íconos: ver, editar y activar/desactivar (desactivar no borra nada y pide confirmación). --}}
                        <div class="flex items-center gap-2 sm:justify-end">
                            <a href="{{ route('admin.usuarios.show', $user) }}" class="{{ $icono }} border-slate-300 text-blue-950 hover:bg-blue-50">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
                                <span class="sr-only">Ver ficha de {{ $nombre }}</span><span class="{{ $globo }}" aria-hidden="true">Ver ficha</span>
                            </a>
                            @can('update', $user)
                                <a href="{{ route('admin.usuarios.edit', $user) }}" class="{{ $icono }} border-slate-300 text-blue-950 hover:bg-blue-50">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3Z" /><path d="m13.5 6.5 3 3" /></svg>
                                    <span class="sr-only">Editar a {{ $nombre }}</span><span class="{{ $globo }}" aria-hidden="true">Editar</span>
                                </a>
                            @endcan
                            @if ($user->hasRole('voluntario'))
                                <a href="{{ route('admin.voluntarios.index') }}" class="{{ $icono }} border-amber-300 text-amber-700 hover:bg-amber-50">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5" /><path d="M2.5 20a6.5 6.5 0 0 1 13 0" /><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6" /></svg>
                                    <span class="sr-only">El acceso de {{ $nombre }} se gestiona en Voluntarios</span><span class="{{ $globo }}" aria-hidden="true">Su acceso se gestiona en Voluntarios</span>
                                </a>
                            @elseif (auth()->user()->can('updateStatus', $user))
                                <form method="POST" action="{{ route('admin.usuarios.estado.update', $user) }}" x-data x-on:submit="if (! confirm({{ Js::from($pregunta) }})) $event.preventDefault()">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="estado" value="{{ $user->estado ? 0 : 1 }}">
                                    @if ($user->estado)
                                        <button type="submit" class="{{ $icono }} border-red-300 text-red-700 hover:bg-red-50">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="8" r="3.5" /><path d="M3.5 20a6.5 6.5 0 0 1 10.6-5" /><path d="m16 15 5 5m0-5-5 5" /></svg>
                                            <span class="sr-only">Desactivar la cuenta de {{ $nombre }}</span><span class="{{ $globo }}" aria-hidden="true">Desactivar cuenta</span>
                                        </button>
                                    @else
                                        <button type="submit" class="{{ $icono }} border-emerald-300 text-emerald-700 hover:bg-emerald-50">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="8" r="3.5" /><path d="M3.5 20a6.5 6.5 0 0 1 10.6-5" /><path d="m15.5 17.5 2 2 4-4" /></svg>
                                            <span class="sr-only">Activar la cuenta de {{ $nombre }}</span><span class="{{ $globo }}" aria-hidden="true">Activar cuenta</span>
                                        </button>
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
