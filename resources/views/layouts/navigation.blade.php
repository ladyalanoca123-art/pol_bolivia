{{--
    Menú del ciudadano y del voluntario (el administrador usa layouts/admin-navigation.blade.php).
    Barra con el color del logo para fondo oscuro (#0d2138), así el logo se integra sin recuadro.
--}}
@php
    $usuario = auth()->user();
    $esVoluntario = $usuario->hasRole('voluntario');
    $enlaces = [[route('inicio'), 'Mi espacio', request()->routeIs('inicio')]];
    if ($esVoluntario) {
        $enlaces[] = [route('seguimientos.index'), 'Mis seguimientos', request()->routeIs('seguimientos.*')];
        $enlaces[] = [route('postulaciones-voluntariado.index'), 'Mi postulación', request()->routeIs('postulaciones-voluntariado.index', 'postulaciones-voluntariado.show')];
        $enlaces[] = [route('solicitudes-adopcion.index'), 'Mis solicitudes', request()->routeIs('solicitudes-adopcion.index', 'solicitudes-adopcion.show')];
    } else {
        $enlaces[] = [route('mascotas.catalogo'), 'Mascotas', request()->routeIs('mascotas.catalogo', 'mascotas.public.show', 'solicitudes-adopcion.create')];
        $enlaces[] = [route('solicitudes-adopcion.index'), 'Mis solicitudes', request()->routeIs('solicitudes-adopcion.*') && ! request()->routeIs('solicitudes-adopcion.create')];
        $enlaces[] = [route('postulaciones-voluntariado.index'), 'Voluntariado', request()->routeIs('postulaciones-voluntariado.*')];
    }
    $iniciales = mb_strtoupper(mb_substr($usuario->nombres, 0, 1).mb_substr($usuario->apellidos ?? '', 0, 1));
    $rol = $esVoluntario ? 'Voluntario/a' : 'Ciudadano/a';
    // Color del rol, el mismo de la lista de Usuarios: ámbar el voluntario y verde el ciudadano.
    $acento = $esVoluntario ? 'bg-amber-400 text-blue-950' : 'bg-emerald-400 text-blue-950';
@endphp
<nav x-data="{ open: false }" class="border-b-4 {{ $esVoluntario ? 'border-amber-400' : 'border-emerald-400' }} bg-[#0d2138] text-white shadow-sm">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-8">
            {{-- Ciudadano: portada pública. Voluntario: su espacio. --}}
            <a href="{{ $esVoluntario ? route('inicio') : route('home') }}" class="shrink-0" aria-label="POL-Bolivia, ir al inicio">
                <img src="{{ asset('images/pol-bolivia/logos/POL_04_fondo_oscuro.png') }}" alt="POL-Bolivia, Hacedores de Sueños" class="h-10 w-auto">
            </a>
            <div class="hidden items-center gap-1 md:flex">
                @foreach ($enlaces as [$url, $texto, $activo])
                    <a href="{{ $url }}" @if ($activo) aria-current="page" @endif
                       class="rounded-full px-4 py-2 text-sm font-semibold transition {{ $activo ? $acento : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">{{ $texto }}</a>
                @endforeach
            </div>
        </div>

        {{-- Cuenta --}}
        <div class="hidden md:block">
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button class="flex items-center gap-3 rounded-full py-1 pe-3 ps-1 text-left transition hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full {{ $acento }} text-sm font-bold" aria-hidden="true">{{ $iniciales }}</span>
                        <span class="leading-tight">
                            <span class="block whitespace-nowrap text-sm font-semibold">{{ $usuario->nombres }} {{ $usuario->apellidos }}</span>
                            <span class="flex items-center gap-1 text-xs {{ $esVoluntario ? 'font-semibold text-amber-300' : 'text-slate-300' }}">@if ($esVoluntario)<svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 21s-7.5-4.6-7.5-10.4A4.4 4.4 0 0 1 12 8a4.4 4.4 0 0 1 7.5 2.6C19.5 16.4 12 21 12 21Z" /></svg>@endif{{ $rol }}</span>
                        </span>
                        <svg class="h-4 w-4 text-slate-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4Z" clip-rule="evenodd"/></svg>
                    </button>
                </x-slot>
                <x-slot name="content">
                    <x-dropdown-link :href="route('profile.edit')">Mi perfil</x-dropdown-link>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-dropdown-link :href="route('logout')" x-on:click.prevent="$el.closest('form').submit()">Cerrar sesión</x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>

        {{-- Botón del menú en celular --}}
        <button @click="open = ! open" :aria-expanded="open ? 'true' : 'false'" aria-label="Abrir o cerrar el menú"
                class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-200 hover:bg-white/10 md:hidden">
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Menú en celular --}}
    <div :class="{'block': open, 'hidden': ! open}" class="hidden border-t border-white/10 md:hidden">
        <div class="space-y-1 px-4 py-3">
            @foreach ($enlaces as [$url, $texto, $activo])
                <a href="{{ $url }}" @if ($activo) aria-current="page" @endif
                   class="block rounded-xl px-4 py-2.5 text-sm font-semibold {{ $activo ? $acento : 'text-slate-200 hover:bg-white/10' }}">{{ $texto }}</a>
            @endforeach
        </div>
        <div class="border-t border-white/10 px-4 py-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-full {{ $acento }} text-sm font-bold" aria-hidden="true">{{ $iniciales }}</span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ $usuario->nombres }} {{ $usuario->apellidos }}</p>
                    <p class="truncate text-xs text-slate-300">{{ $usuario->email }}</p>
                </div>
            </div>
            <div class="mt-3 space-y-1">
                <a href="{{ route('profile.edit') }}" class="block rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-white/10">Mi perfil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full rounded-xl px-4 py-2.5 text-left text-sm font-semibold text-slate-200 hover:bg-white/10">Cerrar sesión</button>
                </form>
            </div>
        </div>
    </div>
</nav>
