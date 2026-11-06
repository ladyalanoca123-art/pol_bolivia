<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        @include('partials.favicon')

        {{-- La letra Figtree está en el proyecto (public/fonts/figtree, declarada en resources/css/app.css). --}}

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        @if (auth()->user()?->hasRole('administrador'))
            {{-- panel-admin: escala de letra del panel (resources/css/app.css). x-data: el botón «Abrir menú» de la barra superior necesita Alpine. --}}
            <div x-data class="panel-admin min-h-screen bg-slate-50 lg:ps-72">
                @include('layouts.admin-navigation')

                <div class="min-h-screen min-w-0">
                    {{--
                        Barra superior: ruta de navegación (App\Support\MenuAdministracion), campana de avisos y cuenta.
                        $avisosCampana: avisos con casos (App\Services\PanelAdministrador::avisosActivos, AppServiceProvider).
                        En tablets y celulares además abre el menú lateral.
                    --}}
                    @php
                        $migaDePan = \App\Support\MenuAdministracion::migaDePan(request());
                        $avisosCampana ??= collect();
                        $totalAvisos = $avisosCampana->count();
                        $puntoPrioridad = ['alta' => 'bg-red-500', 'media' => 'bg-orange-400', 'baja' => 'bg-sky-400'];
                    @endphp
                    <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 backdrop-blur">
                        <div class="flex min-h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-10">
                            <div class="flex min-w-0 items-center gap-3">
                                <button type="button" @click="$dispatch('admin-menu-toggle')" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 lg:hidden" aria-label="Abrir menú">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                                </button>
                                <p class="truncate text-base font-semibold text-blue-950 sm:hidden">POL-Bolivia</p>
                                <nav aria-label="Ruta de navegación" class="hidden min-w-0 sm:block">
                                    <ol class="flex items-center gap-2 truncate text-[13px] font-semibold uppercase tracking-[.08em] text-slate-500">
                                        @foreach ($migaDePan as $paso)
                                            <li class="flex items-center gap-2 {{ $loop->last ? 'text-blue-950' : '' }}" @if ($loop->last) aria-current="page" @endif>
                                                @unless ($loop->first)<span aria-hidden="true" class="text-slate-300">›</span>@endunless
                                                {{ $paso }}
                                            </li>
                                        @endforeach
                                    </ol>
                                </nav>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                {{-- Campana de avisos: el punto rojo solo aparece si hay situaciones que atender. --}}
                                <div x-data="{ abierta: false }" class="relative" @keydown.escape.window="abierta = false" @click.outside="abierta = false">
                                    <button type="button" @click="abierta = !abierta" :aria-expanded="abierta.toString()" aria-expanded="false" aria-haspopup="true"
                                            aria-label="{{ $totalAvisos > 0 ? 'Avisos: '.$totalAvisos.' '.($totalAvisos === 1 ? 'pendiente' : 'pendientes') : 'Avisos: no hay pendientes' }}"
                                            class="relative inline-flex h-11 w-11 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 hover:text-blue-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.86 17.08a23.85 23.85 0 0 0 5.45-1.31A8.97 8.97 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.97 8.97 0 0 1-2.31 6.02c1.73.64 3.56 1.09 5.45 1.31m5.72 0a24.26 24.26 0 0 1-5.72 0m5.72 0a3 3 0 1 1-5.72 0" /></svg>
                                        @if ($totalAvisos > 0)
                                            <span class="absolute right-1.5 top-1.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-600 px-1 text-[11px] font-bold leading-none text-white ring-2 ring-white" aria-hidden="true">{{ $totalAvisos }}</span>
                                        @endif
                                    </button>

                                    <div x-show="abierta" x-cloak x-transition.origin.top.right
                                         class="absolute right-0 z-40 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                                        <div class="border-b border-slate-100 px-4 py-3">
                                            <p class="text-base font-semibold text-blue-950">Avisos</p>
                                            <p class="text-sm text-slate-500">{{ $totalAvisos > 0 ? 'Situaciones que necesitan una acción.' : 'No hay situaciones pendientes.' }}</p>
                                        </div>
                                        @if ($totalAvisos > 0)
                                            <ul class="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                                                @foreach ($avisosCampana as $aviso)
                                                    <li>
                                                        <a href="{{ $aviso['url'] }}" class="flex gap-3 px-4 py-3 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none">
                                                            <span class="mt-2 h-2 w-2 shrink-0 rounded-full {{ $puntoPrioridad[$aviso['prioridad']] }}" aria-hidden="true"></span>
                                                            <span class="min-w-0">
                                                                <span class="block text-[15px] font-semibold text-blue-950">{{ $aviso['titulo'] }} ({{ $aviso['total'] }})</span>
                                                                <span class="block text-sm text-slate-600">{{ $aviso['descripcion'] }}</span>
                                                                <span class="mt-0.5 block text-sm font-semibold text-blue-800">{{ $aviso['accion'] }} →</span>
                                                            </span>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <p class="px-4 py-6 text-center text-sm text-slate-600">Todo está al día.</p>
                                        @endif
                                        <a href="{{ route('admin.dashboard') }}" class="block border-t border-slate-100 px-4 py-3 text-center text-sm font-semibold text-blue-800 hover:bg-slate-50">Ir al panel principal</a>
                                    </div>
                                </div>

                                <a href="{{ route('profile.edit') }}" class="hidden min-h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[15px] font-semibold text-blue-950 hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 sm:inline-flex">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" /></svg>
                                    Mi perfil
                                </a>
                                <form method="POST" action="{{ route('logout') }}" class="lg:hidden">
                                    @csrf
                                    <button type="submit" class="min-h-11 rounded-xl border border-slate-200 px-4 text-[15px] font-semibold text-slate-700 hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">Salir</button>
                                </form>
                            </div>
                        </div>
                    </header>

                    @isset($header)
                        <div class="border-b border-slate-200 bg-white">
                            <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">{{ $header }}</div>
                        </div>
                    @endisset

                    <main class="min-w-0">{{ $slot }}</main>
                </div>
            </div>
        @else
            <div class="min-h-screen bg-slate-50">
                @include('layouts.navigation')
                @isset($header)
                    <header class="border-b border-slate-200 bg-white">
                        <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">{{ $header }}</div>
                    </header>
                @endisset
                <main class="vista-personal">{{ $slot }}</main>
            </div>
        @endif
    </body>
</html>
