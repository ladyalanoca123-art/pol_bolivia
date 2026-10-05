<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | POL-Bolivia</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-800 antialiased">
    <main class="mx-auto grid min-h-screen max-w-7xl lg:grid-cols-2">
        <section class="flex items-center px-5 py-10 sm:px-10 lg:px-16">
            <div class="mx-auto w-full max-w-md">
                <a href="{{ route('home') }}" class="flex items-center gap-3 text-xl font-extrabold tracking-tight text-emerald-800">
                    <img src="{{ asset('images/pol-bolivia/logo.png') }}" alt="Logotipo de POL-Bolivia" class="h-12 w-12 object-contain">
                    <span>POL-BOLIVIA</span>
                </a>
                <p class="mt-8 text-sm font-bold tracking-wider text-emerald-700">BIENVENIDO DE NUEVO</p>
                <h1 class="mt-2 text-3xl font-extrabold">Continúa tu camino para adoptar o ayudar.</h1>
                <p class="mt-3 text-stone-600">Inicia sesión para continuar con tu solicitud de adopción o formar parte de nuestro equipo de voluntariado.</p>

                <x-auth-session-status class="mt-6" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="text-sm font-semibold text-stone-700">Correo electrónico</label>
                        <input id="email" class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-emerald-700 focus:ring-emerald-700" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                    <div>
                        <label for="password" class="text-sm font-semibold text-stone-700">Contraseña</label>
                        <input id="password" class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-emerald-700 focus:ring-emerald-700" type="password" name="password" required autocomplete="current-password">
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <label for="remember_me" class="inline-flex items-center text-sm text-stone-600"><input id="remember_me" type="checkbox" class="rounded border-stone-300 text-emerald-700 shadow-sm focus:ring-emerald-700" name="remember"><span class="ms-2">Recordarme</span></label>
                        @if (Route::has('password.request'))<a href="{{ route('password.request') }}" class="text-sm font-semibold text-emerald-800 hover:text-emerald-600">¿Olvidaste tu contraseña?</a>@endif
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-emerald-700 px-4 py-3 text-sm font-extrabold text-white hover:bg-emerald-800">INICIAR SESIÓN</button>
                </form>

                <div class="mt-7 border-t border-stone-200 pt-6 text-center"><p class="text-sm text-stone-600">¿Aún no tienes una cuenta?</p><a href="{{ route('register') }}" class="mt-3 inline-block rounded-lg border border-emerald-700 px-5 py-2 text-sm font-extrabold text-emerald-800 hover:bg-emerald-50">CREAR CUENTA</a></div>
            </div>
        </section>
        <aside class="relative hidden overflow-hidden bg-emerald-900 lg:block">
            <img src="{{ asset('images/pol-bolivia/grupo-voluntarios.jpg') }}" alt="Grupo de voluntarios de POL-Bolivia" class="absolute inset-0 h-full w-full object-cover opacity-65">
            <div class="absolute inset-0 bg-emerald-950/45"></div>
            <div class="relative flex h-full items-end p-16 text-white"><div><p class="text-sm font-bold tracking-[0.2em] text-emerald-200">COMUNIDAD POL-BOLIVIA</p><p class="mt-4 max-w-md text-3xl font-extrabold leading-tight">Cada adopción responsable y cada voluntariado hacen una diferencia.</p></div></div>
        </aside>
    </main>
</body>
</html>
