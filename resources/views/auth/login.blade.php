<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | POL-Bolivia</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $errorCorreo = $errors->first('email');
    $errorClave = $errors->first('password');
    $campoBase = 'mt-1 block h-12 w-full rounded-xl px-4 text-base text-slate-900 shadow-sm focus:outline-none focus:ring-4';
    $campoNormal = 'border-slate-300 bg-white focus:border-blue-700 focus:ring-blue-700/15';
    $campoError = 'border-red-500 bg-red-50 focus:border-red-600 focus:ring-red-600/15';
@endphp
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    {{-- Ancho completo (sin contenedor máximo): formulario 5 partes, fotografía 6 (espejo del registro) --}}
    <main class="grid min-h-screen lg:grid-cols-[5fr_6fr]">
        {{-- Formulario: primero en móvil, a la izquierda en pantallas grandes --}}
        <section class="flex items-center px-4 py-8 sm:px-10 lg:px-12 lg:py-4">
            <div class="mx-auto w-full max-w-md">
                <a href="{{ route('home') }}" class="inline-block rounded-lg focus:outline-none focus:ring-4 focus:ring-blue-700/15">
                    <img src="{{ asset('images/pol-bolivia/logos/POL_02_horizontal_web.png') }}" alt="POL-Bolivia, Hacedores de Sueños" class="h-14 w-auto">
                </a>

                <p class="mt-8 text-sm font-bold tracking-[0.14em] text-amber-700 lg:mt-6">BIENVENIDO DE NUEVO</p>
                <h1 class="mt-2 text-3xl font-extrabold leading-tight text-blue-950 sm:text-4xl">Inicia sesión</h1>
                <p class="mt-2 text-slate-600">Sigue tu solicitud de adopción o tu voluntariado.</p>

                <x-auth-session-status class="mt-5" :status="session('status')" />

                @if ($errors->any())
                    <div role="alert" class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <p class="font-semibold">No pudimos iniciar sesión.</p>
                        <ul class="mt-1 space-y-1">
                            @if ($errorCorreo)<li id="email-error">{{ $errorCorreo }}</li>@endif
                            @if ($errorClave)<li id="password-error">{{ $errorClave }}</li>@endif
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-5 lg:mt-6"
                      x-data="{ mostrar: false, mayus: false, enviando: false }"
                      x-on:submit="enviando = true"
                      x-on:pageshow.window="enviando = false">
                    @csrf

                    <div>
                        <label for="email" class="text-sm font-semibold text-slate-700">Correo electrónico</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                               autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false"
                               class="{{ $campoBase }} {{ $errorCorreo ? $campoError : $campoNormal }}"
                               @if ($errorCorreo) aria-invalid="true" aria-describedby="email-error" @endif>
                    </div>

                    <div>
                        <label for="password" class="text-sm font-semibold text-slate-700">Contraseña</label>
                        <div class="relative">
                            <input id="password" name="password" type="password" required autocomplete="current-password"
                                   x-bind:type="mostrar ? 'text' : 'password'"
                                   x-on:keydown="mayus = $event.getModifierState && $event.getModifierState('CapsLock')"
                                   x-on:keyup="mayus = $event.getModifierState && $event.getModifierState('CapsLock')"
                                   x-on:blur="mayus = false"
                                   class="{{ $campoBase }} pr-14 {{ $errorClave ? $campoError : $campoNormal }}"
                                   @if ($errorClave) aria-invalid="true" @endif
                                   aria-describedby="{{ $errorClave ? 'password-error ' : '' }}aviso-mayus">
                            <button type="button"
                                    class="absolute inset-y-0 right-0 mt-1 flex w-12 items-center justify-center rounded-r-xl text-slate-500 hover:text-blue-950 focus:outline-none focus:ring-4 focus:ring-blue-700/15"
                                    x-on:click="mostrar = ! mostrar"
                                    x-bind:aria-pressed="mostrar.toString()"
                                    x-bind:aria-label="mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                    aria-pressed="false" aria-label="Mostrar contraseña" aria-controls="password">
                                <svg x-show="! mostrar" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1.01 1.01 0 0 1 0-.64C3.42 7.51 7.36 4.5 12 4.5c4.64 0 8.57 3 9.96 7.18.07.2.07.43 0 .64C20.58 16.49 16.64 19.5 12 19.5c-4.64 0-8.57-3-9.96-7.18Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                <svg x-show="mostrar" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.48 10.48 0 0 0 1.93 12C3.23 16.34 7.24 19.5 12 19.5c.99 0 1.95-.14 2.86-.4M6.23 6.23A10.45 10.45 0 0 1 12 4.5c4.76 0 8.77 3.16 10.07 7.5a10.52 10.52 0 0 1-4.29 5.77M6.23 6.23 3 3m3.23 3.23 3.65 3.65m7.89 7.89L21 21m-3.23-3.23-3.65-3.65m0 0a3 3 0 1 0-4.24-4.24m4.24 4.24L9.88 9.88"/></svg>
                            </button>
                        </div>
                        {{-- Región siempre presente para que los lectores de pantalla anuncien el cambio --}}
                        {{-- Sin margen propio: solo ocupa espacio cuando el aviso está visible --}}
                        <p id="aviso-mayus" aria-live="polite" class="text-sm font-semibold text-amber-700"><span x-show="mayus" x-cloak class="mt-2 block">Bloq Mayús está activado.</span></p>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <label for="remember_me" class="inline-flex items-center text-sm text-slate-600">
                            <input id="remember_me" type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-blue-950 shadow-sm focus:ring-blue-700/30">
                            <span class="ms-2">Recordarme</span>
                        </label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm font-semibold text-blue-950 underline-offset-4 hover:text-blue-700 hover:underline">¿Olvidaste tu contraseña?</a>
                        @endif
                    </div>

                    <button type="submit"
                            class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-amber-400 px-6 text-base font-extrabold text-blue-950 shadow-sm hover:bg-amber-300 focus:outline-none focus:ring-4 focus:ring-amber-400/40 disabled:cursor-wait disabled:opacity-80"
                            x-bind:disabled="enviando" x-bind:aria-busy="enviando.toString()" aria-busy="false">
                        <svg x-show="enviando" x-cloak class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/></svg>
                        <span x-text="enviando ? 'Ingresando…' : 'Iniciar sesión'">Iniciar sesión</span>
                    </button>

                    <p class="flex items-center justify-center gap-1.5 text-center text-xs text-slate-500">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd"/></svg>
                        POL-Bolivia nunca te pedirá tu contraseña por WhatsApp, correo o llamada.
                    </p>
                </form>

                {{-- Pregunta y botón en una sola fila (en pantallas muy estrechas pasan a dos) --}}
                <div class="mt-6 flex flex-wrap items-center justify-center gap-x-4 gap-y-3 border-t border-slate-200 pt-5 text-center">
                    <p class="text-sm text-slate-600">¿Aún no tienes una cuenta?</p>
                    <a href="{{ route('register') }}" class="inline-flex h-11 items-center justify-center rounded-full border-2 border-blue-950 px-7 text-sm font-extrabold text-blue-950 hover:bg-blue-950 hover:text-white focus:outline-none focus:ring-4 focus:ring-blue-700/15">CREAR CUENTA</a>
                </div>
            </div>
        </section>

        {{-- Fotografía: debajo del formulario en móvil (nunca oculta); en pantallas grandes a la derecha,
             hasta el borde y fija a la altura de la pantalla --}}
        <aside class="relative h-60 overflow-hidden bg-blue-950 lg:sticky lg:top-0 lg:h-screen">
            <img src="{{ asset('images/pol-bolivia/grupo-voluntarios.jpg') }}" alt="Grupo de voluntarios de POL-Bolivia" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-b from-blue-950/35 via-blue-950/55 to-blue-950/90"></div>
            <div class="relative flex h-full items-end p-6 text-white sm:p-10 lg:p-16">
                <div>
                    <p class="text-xs font-bold tracking-[0.2em] text-amber-300 sm:text-sm">COMUNIDAD POL-BOLIVIA</p>
                    <p class="mt-3 max-w-md text-xl font-extrabold leading-tight text-white lg:mt-4 lg:text-4xl">Cada adopción responsable y cada voluntariado hacen una diferencia.</p>
                    <div class="mt-5 h-1 w-16 rounded-full bg-amber-400" aria-hidden="true"></div>
                </div>
            </div>
        </aside>
    </main>
</body>
</html>
