{{--
    Pantallas de cuenta (recuperar y restablecer la contraseña, confirmar la contraseña, verificar el correo)
    con la identidad de POL-Bolivia, coherentes con el inicio de sesión: logo, tarjeta blanca y títulos azul marino.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $titulo ? "{$titulo} | POL-Bolivia" : 'POL-Bolivia' }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
        <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6">
            <div class="w-full max-w-md">
                <a href="{{ route('home') }}" class="inline-block rounded-lg focus:outline-none focus:ring-4 focus:ring-blue-700/15">
                    <img src="{{ asset('images/pol-bolivia/logos/POL_02_horizontal_web.png') }}" alt="POL-Bolivia, Hacedores de Sueños" class="h-14 w-auto">
                </a>

                <div class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                    {{ $slot }}
                </div>
            </div>
        </main>
    </body>
</html>
