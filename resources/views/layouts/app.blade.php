<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        @include('partials.favicon')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        @if (auth()->user()?->hasRole('administrador'))
            <div class="min-h-screen bg-slate-50 md:ps-64">
                @include('layouts.admin-navigation')

                <div class="min-h-screen min-w-0">
                    <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 backdrop-blur">
                        <div class="flex min-h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                            <div class="flex min-w-0 items-center gap-3">
                                <button type="button" @click="$dispatch('admin-menu-toggle')" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 md:hidden" aria-label="Abrir menú">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                                </button>
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold uppercase tracking-[.16em] text-slate-500">POL-Bolivia · Administración</p>
                                    <p class="truncate text-sm font-semibold text-blue-950">{{ trim((auth()->user()->nombres ?? '').' '.(auth()->user()->apellidos ?? '')) }}</p>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <a href="{{ route('profile.edit') }}" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-blue-950 sm:inline-flex">Mi perfil</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">Salir</button>
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
            <div class="min-h-screen bg-gray-100">
                @include('layouts.navigation')
                @isset($header)
                    <header class="bg-white shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">{{ $header }}</div>
                    </header>
                @endisset
                <main>{{ $slot }}</main>
            </div>
        @endif
    </body>
</html>
