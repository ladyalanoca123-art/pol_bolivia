@php
    $tonosRol = ['administrador' => 'indigo', 'voluntario' => 'ambar', 'ciudadano' => 'verde'];
    $hayFiltros = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();
@endphp
<x-admin.pagina titulo="Gestión de usuarios" :contador="$users->total().($users->total() === 1 ? ' usuario' : ' usuarios')" seccion="Usuarios" descripcion="Consulta, edita y activa o desactiva las cuentas del sistema.">
    <x-admin.tarjeta>
        {{-- Solo la búsqueda: el rol y las cuentas inactivas se eligen con los botones de abajo (se conservan al buscar). --}}
        <form method="GET" action="{{ route('admin.usuarios.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            @if (filled($filters['rol'] ?? null))<input type="hidden" name="rol" value="{{ $filters['rol'] }}">@endif
            @if (filled($filters['estado'] ?? null))<input type="hidden" name="estado" value="{{ $filters['estado'] }}">@endif
            <div class="min-w-0 flex-1">
                <label for="buscar" class="admin-etiqueta">Buscar por nombre, apellido o correo</label>
                <input id="buscar" name="buscar" type="search" class="admin-campo" value="{{ $filters['buscar'] ?? '' }}" placeholder="Nombre, apellido o correo">
            </div>
            <div class="flex items-center gap-3">
                <x-admin.boton>Buscar</x-admin.boton>
                @if ($hayFiltros)<x-admin.boton variante="secundario" :href="route('admin.usuarios.index')">Limpiar</x-admin.boton>@endif
            </div>
        </form>
    </x-admin.tarjeta>

    {{-- Accesos rápidos por rol y a las cuentas inactivas (usan los mismos filtros): debajo de los filtros. --}}
    @php
        $rolActual = $filters['rol'] ?? null;
        $estadoActual = $filters['estado'] ?? null;
        $opcionesRol = collect([
            ['Todos', null, null, \App\Support\IconosPestanas::EQUIPO],
            ['Administradores', 'administrador', null, \App\Support\IconosPestanas::ESCUDO],
            ['Voluntarios', 'voluntario', null, \App\Support\IconosPestanas::CORAZON],
            ['Ciudadanos', 'ciudadano', null, \App\Support\IconosPestanas::PERSONA],
            ['Inactivos', null, 'inactivo', \App\Support\IconosPestanas::PERSONA_TACHADA],
        ])->map(fn ($p) => [$p[0], route('admin.usuarios.index', array_filter(['buscar' => $filters['buscar'] ?? null, 'rol' => $p[1], 'estado' => $p[2]])), $rolActual === $p[1] && $estadoActual === $p[2], $p[3]])->all();
    @endphp
    <x-admin.pestanas etiqueta="Filtrar por rol" :opciones="$opcionesRol" />

    <x-admin.tarjeta :relleno="false">
        @if ($users->isEmpty())
            <x-admin.vacio titulo="No se encontraron usuarios." texto="Prueba con otra búsqueda o con otros filtros." />
        @else
            @php
                // Color del círculo según el rol.
                $circulos = ['administrador' => 'bg-indigo-800 text-white', 'voluntario' => 'bg-amber-400 text-blue-950', 'ciudadano' => 'bg-emerald-600 text-white'];
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
                            {{-- Más opciones (⋮): activar o desactivar queda dentro de un menú, para no apretarlo sin querer. --}}
                            @if ($user->hasRole('voluntario') || auth()->user()->can('updateStatus', $user))
                                <div class="relative" x-data="{ abierto: false }" x-on:click.outside="abierto = false" x-on:keydown.escape.window="abierto = false">
                                    <button type="button" x-on:click="abierto = ! abierto" x-bind:aria-expanded="abierto.toString()" aria-haspopup="true"
                                            class="{{ $icono }} border-slate-300 text-blue-950 hover:bg-blue-50">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="12" cy="19" r="1.8" /></svg>
                                        <span class="sr-only">Más opciones para {{ $nombre }}</span><span class="{{ $globo }}" aria-hidden="true">Más opciones</span>
                                    </button>
                                    <div x-show="abierto" x-cloak x-transition.opacity
                                         class="absolute end-0 top-full z-20 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-1.5 text-start shadow-lg">
                                        @if ($user->hasRole('voluntario'))
                                            <a href="{{ route('admin.voluntarios.index') }}" class="flex items-start gap-3 rounded-lg px-3 py-2.5 text-sm hover:bg-amber-50">
                                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ \App\Support\IconosPestanas::EQUIPO }}" /></svg>
                                                <span><span class="block font-semibold text-blue-950">Gestionar en Voluntarios</span><span class="block text-[13px] text-slate-500">El acceso de un voluntario se gestiona desde Voluntarios.</span></span>
                                            </a>
                                        @else
                                            <form method="POST" action="{{ route('admin.usuarios.estado.update', $user) }}" x-on:submit="if (! confirm({{ Js::from($pregunta) }})) $event.preventDefault()">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="estado" value="{{ $user->estado ? 0 : 1 }}">
                                                <button type="submit" class="flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-start text-sm {{ $user->estado ? 'hover:bg-red-50' : 'hover:bg-emerald-50' }}">
                                                    @if ($user->estado)
                                                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ \App\Support\IconosPestanas::PERSONA_TACHADA }}" /></svg>
                                                        <span><span class="block font-semibold text-red-700">Desactivar cuenta</span><span class="block text-[13px] text-slate-500">No podrá iniciar sesión. Sus datos se conservan.</span></span>
                                                    @else
                                                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 8a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0ZM3.5 20a6.5 6.5 0 0 1 10.6-5M15.5 17.5l2 2 4-4" /></svg>
                                                        <span><span class="block font-semibold text-emerald-700">Activar cuenta</span><span class="block text-[13px] text-slate-500">Podrá volver a iniciar sesión.</span></span>
                                                    @endif
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($users->hasPages())<div class="border-t border-slate-100 px-4 py-3 sm:px-6">{{ $users->links() }}</div>@endif
        @endif
    </x-admin.tarjeta>
</x-admin.pagina>
