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
                        Barra superior: ruta de navegación (App\Support\MenuAdministracion) y cuenta.
                        En tablets y celulares además abre el menú lateral.
                    --}}
                    @php
                        $migaDePan = \App\Support\MenuAdministracion::migaDePan(request());
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
            {{-- El voluntario tiene además un menú lateral celeste en computadora (layouts/voluntario-navigation). --}}
            @php $esVoluntario = auth()->user()?->hasRole('voluntario'); @endphp
            <div class="min-h-screen bg-slate-50 {{ $esVoluntario ? 'lg:ps-64' : '' }}">
                @if ($esVoluntario)
                    @include('layouts.voluntario-navigation')
                @endif
                @include('layouts.navigation')
                @isset($header)
                    <header class="border-b border-slate-200 bg-white">
                        <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">{{ $header }}</div>
                    </header>
                @endisset
                <main class="vista-personal {{ $esVoluntario ? 'panel-voluntario' : '' }}">{{ $slot }}</main>
            </div>
        @endif
    </body>
</html>
