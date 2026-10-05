<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'POL-Bolivia')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-50 font-sans text-stone-800 antialiased">
    <header class="sticky top-0 z-30 border-b border-stone-200 bg-stone-50/95 backdrop-blur">
        <nav class="mx-auto flex max-w-7xl items-center justify-between gap-5 px-4 py-4 sm:px-6 lg:px-8" aria-label="Navegación principal">
            <a href="{{ route('home') }}" class="flex items-center gap-3 text-xl font-extrabold tracking-tight text-blue-900">
                <img src="{{ asset('images/pol-bolivia/logo.png') }}" alt="Logotipo de POL-Bolivia" class="h-10 w-10 object-contain">
                <span>POL-Bolivia</span>
            </a>
            <div class="hidden items-center gap-5 text-sm font-medium md:flex">
                <a href="{{ route('home') }}" class="hover:text-blue-700">Inicio</a>
                <a href="{{ route('mascotas.catalogo') }}" class="hover:text-blue-700">Mascotas</a>
                <a href="{{ route('home') }}#como-adoptar" class="hover:text-blue-700">¿Cómo adoptar?</a>
                <a href="{{ route('home') }}#voluntariado" class="hover:text-blue-700">Ser voluntario</a>
            </div>
            <div class="flex items-center gap-3">
                <details class="relative md:hidden">
                    <summary class="cursor-pointer rounded-full border border-blue-700 px-3 py-2 text-sm font-semibold text-blue-800">Menú</summary>
                    <div class="absolute right-0 mt-2 grid w-52 gap-1 rounded-xl bg-white p-2 shadow-lg ring-1 ring-stone-200">
                        <a href="{{ route('home') }}" class="rounded-lg px-3 py-2 text-sm hover:bg-sky-50">Inicio</a>
                        <a href="{{ route('mascotas.catalogo') }}" class="rounded-lg px-3 py-2 text-sm hover:bg-sky-50">Mascotas</a>
                        <a href="{{ route('home') }}#como-adoptar" class="rounded-lg px-3 py-2 text-sm hover:bg-sky-50">¿Cómo adoptar?</a>
                        <a href="{{ route('home') }}#voluntariado" class="rounded-lg px-3 py-2 text-sm hover:bg-sky-50">Ser voluntario</a>
                    </div>
                </details>
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-full bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Mi panel</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-full bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Iniciar sesión</a>
                @endauth
            </div>
        </nav>
    </header>
    <main>@yield('content')</main>
    <footer class="border-t border-stone-200 bg-white"><div class="mx-auto max-w-7xl px-4 py-8 text-sm text-stone-600 sm:px-6 lg:px-8"><p class="font-bold text-emerald-800">POL-BOLIVIA</p><p class="mt-1">Cochabamba, Bolivia</p></div></footer>
</body>
</html>
