{{--
    Menú lateral del panel de administración: fijo en escritorio (lg) y desplegable en tablets y celulares
    (lo abre el botón de la barra superior con el evento admin-menu-toggle).
    Grupos: Inicio, Adopciones, Voluntariado y Administración. Opción actual: fondo ámbar y texto azul oscuro.
--}}
@php
    // Secciones del menú: App\Support\MenuAdministracion (también las usa la ruta de navegación de la barra superior).
    $grupos = \App\Support\MenuAdministracion::grupos();
    $usuario = auth()->user();
@endphp
<aside x-data="{ open: false }" @admin-menu-toggle.window="open = !open" @keydown.escape.window="open = false" class="z-40">
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" @click="open = false" aria-hidden="true"></div>

    <div :class="open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-blue-950 text-white shadow-2xl transition-transform duration-200 ease-out lg:translate-x-0 lg:shadow-none">
        {{-- Marca --}}
        <div class="flex h-16 shrink-0 items-center justify-between gap-2 px-5 [@media(min-height:820px)]:h-[72px]">
            <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400" aria-label="POL-Bolivia, panel principal">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white p-1.5">
                    <img src="{{ asset('images/pol-bolivia/logo.png') }}" alt="" class="max-h-full max-w-full object-contain">
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-lg font-bold leading-tight tracking-tight">POL-Bolivia</span>
                    <span class="block truncate text-[13px] text-blue-200">Panel de administración</span>
                </span>
            </a>
            <button type="button" @click="open = false" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-blue-100 hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 lg:hidden" aria-label="Cerrar menú">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
            </button>
        </div>

        {{-- Opciones --}}
        <nav class="menu-admin-desplazable min-h-0 flex-1 overflow-y-auto px-4 pb-1" aria-label="Navegación administrativa">
            @foreach ($grupos as $grupo => $opciones)
                <div @class(['mt-2 border-t border-white/10 pt-2 [@media(min-height:820px)]:mt-3 [@media(min-height:820px)]:pt-3' => ! $loop->first])>
                    <p class="px-3 pb-1 text-xs font-semibold uppercase leading-5 tracking-[.14em] text-blue-300">{{ $grupo }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($opciones as [$ruta, $texto, $patron, $icono])
                            @php $actual = request()->routeIs($patron); @endphp
                            <li>
                                <a href="{{ route($ruta) }}" @click="open = false" @if ($actual) aria-current="page" @endif
                                   @class([
                                       'flex min-h-9 items-center gap-3 rounded-xl px-3 text-[15px] [@media(min-height:820px)]:min-h-11 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400',
                                       'bg-amber-400 font-semibold text-blue-950' => $actual,
                                       'font-medium text-blue-100 hover:bg-white/[.08] hover:text-white' => ! $actual,
                                   ])>
                                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icono }}" /></svg>
                                    <span class="truncate">{{ $texto }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        {{-- Perfil del administrador --}}
        <div class="shrink-0 border-t border-white/10 px-4 py-2.5">
            <div class="flex items-center gap-3 rounded-xl px-2">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-400 text-base font-bold text-blue-950" aria-hidden="true">{{ mb_strtoupper(mb_substr($usuario->nombres ?? 'A', 0, 1)) }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[15px] font-semibold">{{ trim(($usuario->nombres ?? '').' '.($usuario->apellidos ?? '')) }}</p>
                    <p class="truncate text-[13px] text-blue-200">{{ $usuario->es_principal ? 'Administración principal' : 'Administración' }}</p>
                </div>
            </div>
            <div class="mt-2.5 grid grid-cols-2 gap-2">
                <a href="{{ route('profile.edit') }}" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-white/[.08] px-3 text-sm font-medium text-blue-50 hover:bg-white/[.14] focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">Mi perfil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-9 w-full items-center justify-center rounded-lg bg-white/[.08] px-3 text-sm font-medium text-blue-50 hover:bg-white/[.14] focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">Cerrar sesión</button>
                </form>
            </div>
        </div>
    </div>
</aside>
