<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta | POL-Bolivia</title>
    @include('partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    // Logo con fondo transparente y letras claras: se integra al azul del panel (sin recuadro).
    $logoVertical = asset('images/pol-bolivia/logos/POL_10_vertical_oscuro_transparente.png');
    $etiqueta = 'text-sm font-semibold text-slate-700';

    // Campo normal o con error (borde y fondo rojos). Solo se marca el error; nunca un "válido".
    $claseCampo = fn (string $nombre) => 'mt-1 block h-12 w-full rounded-xl px-4 text-base text-slate-900 shadow-sm placeholder:text-slate-400 focus:outline-none focus:ring-4 '
        .($errors->has($nombre)
            ? 'border-red-500 bg-red-50 focus:border-red-600 focus:ring-red-600/15'
            : 'border-slate-300 bg-white focus:border-blue-700 focus:ring-blue-700/15');

    // Orden de los campos en el formulario: el resumen sigue este orden.
    $camposConError = array_values(array_filter(
        ['nombres', 'apellidos', 'email', 'telefono', 'password', 'password_confirmation'],
        fn (string $nombre) => $errors->has($nombre)
    ));
    $etiquetas = [
        'nombres' => 'Nombres', 'apellidos' => 'Apellidos', 'email' => 'Correo electrónico',
        'telefono' => 'Teléfono', 'password' => 'Contraseña', 'password_confirmation' => 'Confirmar contraseña',
    ];
    // Trazos de los iconos de mostrar / ocultar contraseña (los mismos del login).
    $iconoOjo = 'M2.04 12.32a1.01 1.01 0 0 1 0-.64C3.42 7.51 7.36 4.5 12 4.5c4.64 0 8.57 3 9.96 7.18.07.2.07.43 0 .64C20.58 16.49 16.64 19.5 12 19.5c-4.64 0-8.57-3-9.96-7.18Z';
    $iconoOjoTachado = 'M3.98 8.22A10.48 10.48 0 0 0 1.93 12C3.23 16.34 7.24 19.5 12 19.5c.99 0 1.95-.14 2.86-.4M6.23 6.23A10.45 10.45 0 0 1 12 4.5c4.76 0 8.77 3.16 10.07 7.5a10.52 10.52 0 0 1-4.29 5.77M6.23 6.23 3 3m3.23 3.23 3.65 3.65m7.89 7.89L21 21m-3.23-3.23-3.65-3.65m0 0a3 3 0 1 0-4.24-4.24m4.24 4.24L9.88 9.88';
    $pasos = [
        'Crea tu cuenta con tu correo.',
        'Elige a la mascota que quieres adoptar.',
        'Completa la solicitud y sigue su estado.',
    ];
@endphp
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <div class="grid min-h-screen lg:grid-cols-[5fr_6fr]">
        {{-- Panel lateral (solo escritorio, 1024 px o más) --}}
        <aside class="relative hidden overflow-hidden bg-blue-950 text-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col">
            {{-- Decoraciones: no interactivas y ocultas a tecnologías de asistencia --}}
            {{-- La foto ya trae el degradado azul marino incorporado (más oscuro a la izquierda, donde va el texto) --}}
            <img src="{{ asset('images/pol-bolivia/registro-fondo.jpg') }}" alt="" class="pointer-events-none absolute inset-0 h-full w-full object-cover object-center" aria-hidden="true">
            {{-- Tinte azul uniforme (más intenso a la izquierda, donde va el texto) --}}
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-blue-950/75 via-blue-950/60 to-blue-950/45" aria-hidden="true"></div>
            {{-- Arco decorativo dorado --}}
            <div class="pointer-events-none absolute -bottom-28 -right-20 h-80 w-80 rounded-full border border-amber-400/40" aria-hidden="true"></div>

            <div class="relative flex flex-1 flex-col justify-between px-12 py-9 xl:px-16">
                <a href="{{ route('home') }}" class="inline-flex w-fit items-center gap-2 rounded-lg text-sm font-semibold text-blue-100 hover:text-white focus:outline-none focus:ring-4 focus:ring-amber-400/40">
                    <span aria-hidden="true">←</span> Volver al inicio
                </a>

                <div class="py-6">
                    <img src="{{ $logoVertical }}" alt="POL-Bolivia, Hacedores de Sueños" width="128" height="146" class="block h-auto w-32 max-w-full xl:w-36">

                    <p class="mt-8 text-xs font-bold tracking-[0.2em] text-amber-300">ADOPCIÓN RESPONSABLE</p>
                    <h1 class="mt-2 text-3xl font-extrabold leading-tight xl:text-4xl">Dales una segunda oportunidad</h1>
                    <p class="mt-3 max-w-md text-base leading-relaxed text-blue-100">Crea tu cuenta para solicitar una adopción, seguir tus solicitudes y sumarte como voluntario.</p>

                    <ol class="relative mt-7 space-y-4 before:absolute before:bottom-3 before:left-[13px] before:top-3 before:w-0.5 before:bg-amber-400/50">
                        @foreach ($pasos as $paso)
                            <li class="relative flex items-center gap-4">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-400 text-xs font-extrabold text-blue-950 ring-4 ring-blue-950">{{ $loop->iteration }}</span>
                                <span class="text-sm text-white">{{ $paso }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <p class="text-sm text-blue-200">POL-Bolivia · Cochabamba</p>
            </div>
        </aside>

        <div class="flex min-w-0 flex-col">
            {{-- Cabecera compacta (celular y tableta, menos de 1024 px) --}}
            <header class="relative overflow-hidden bg-blue-950 px-4 pb-8 pt-5 text-white sm:px-8 lg:hidden">
                <img src="{{ asset('images/pol-bolivia/registro-fondo.jpg') }}" alt="" class="pointer-events-none absolute inset-0 h-full w-full object-cover object-center" aria-hidden="true">
                <div class="pointer-events-none absolute inset-0 bg-blue-950/65" aria-hidden="true"></div>
                <div class="relative">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-2 rounded-lg text-sm font-semibold text-blue-100 hover:text-white focus:outline-none focus:ring-4 focus:ring-amber-400/40">
                        <span aria-hidden="true">←</span> Inicio
                    </a>
                    <div class="mt-5 flex items-center gap-4">
                        <img src="{{ $logoVertical }}" alt="POL-Bolivia, Hacedores de Sueños" class="h-16 w-auto shrink-0">
                        <div class="min-w-0">
                            <p class="text-xl font-extrabold leading-tight sm:text-2xl">Dales una segunda oportunidad</p>
                            <p class="mt-1 text-sm text-blue-100">Adopta, sigue tus solicitudes y súmate como voluntario.</p>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Formulario --}}
            <main class="flex flex-1 items-center justify-center px-4 py-7 sm:px-8 lg:px-10 lg:py-6">
                <div class="w-full max-w-[480px]">
                    <p class="text-sm font-bold tracking-[0.14em] text-amber-700">ÚNETE A LA COMUNIDAD</p>
                    <h2 class="mt-1.5 text-3xl font-extrabold text-blue-950">Crea tu cuenta</h2>
                    <p class="mt-1 text-slate-600">Solo te tomará un minuto.</p>

                    {{-- Resumen de errores: se anuncia al cargar la página; cada enlace lleva a su campo --}}
                    @if ($errors->any())
                        <div role="alert" class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                            <p class="font-semibold">No pudimos crear tu cuenta. Revisa estos datos:</p>
                            <ul class="mt-2 list-disc space-y-1 ps-5">
                                @foreach ($camposConError as $nombre)
                                    <li><a href="#{{ $nombre }}" class="font-semibold underline underline-offset-2 hover:text-red-900">{{ $etiquetas[$nombre] }}</a>: {{ $errors->first($nombre) }}</li>
                                @endforeach
                                {{-- Errores que no pertenecen a un campo concreto --}}
                                @foreach ($errors->keys() as $clave)
                                    @unless (array_key_exists($clave, $etiquetas))
                                        <li>{{ $errors->first($clave) }}</li>
                                    @endunless
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- enviando: evita envíos dobles; al volver con "Atrás" (pageshow) se reactiva el botón.
                         Si hay errores, el foco va al primer campo inválido. --}}
                    <form method="POST" action="{{ route('register') }}" class="mt-5 space-y-3.5"
                          x-data="{ clave: '', mostrarClave: false, mostrarConfirmacion: false, enviando: false }"
                          x-init="$nextTick(() => { const campo = $el.querySelector('[aria-invalid=true]'); if (campo) campo.focus(); })"
                          x-on:submit="enviando = true"
                          x-on:pageshow.window="enviando = false">
                        @csrf

                        {{-- Honeypot contra bots: fuera de la pantalla, del teclado (tabindex -1) y de los
                             lectores de pantalla (aria-hidden). Una persona nunca lo completa. --}}
                        <div class="absolute -left-[10000px] top-auto h-px w-px overflow-hidden" aria-hidden="true">
                            <label for="sitio_web">No completes este campo</label>
                            <input id="sitio_web" name="sitio_web" type="text" value="" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="grid grid-cols-1 gap-x-4 gap-y-3.5 sm:grid-cols-2">
                            <div>
                                <label for="nombres" class="{{ $etiqueta }}">Nombres</label>
                                <input id="nombres" name="nombres" type="text" value="{{ old('nombres') }}" required @unless ($errors->any()) autofocus @endunless
                                       autocomplete="given-name" placeholder="Ej. María José" class="{{ $claseCampo('nombres') }}"
                                       @error('nombres') aria-invalid="true" aria-describedby="nombres-error" @enderror>
                                @error('nombres')
                                    <ul id="nombres-error" class="mt-2 space-y-1 text-sm font-medium text-red-700">@foreach ($errors->get('nombres') as $mensaje)<li>{{ $mensaje }}</li>@endforeach</ul>
                                @enderror
                            </div>
                            <div>
                                <label for="apellidos" class="{{ $etiqueta }}">Apellidos</label>
                                <input id="apellidos" name="apellidos" type="text" value="{{ old('apellidos') }}" required
                                       autocomplete="family-name" placeholder="Ej. Pérez Rojas" class="{{ $claseCampo('apellidos') }}"
                                       @error('apellidos') aria-invalid="true" aria-describedby="apellidos-error" @enderror>
                                @error('apellidos')
                                    <ul id="apellidos-error" class="mt-2 space-y-1 text-sm font-medium text-red-700">@foreach ($errors->get('apellidos') as $mensaje)<li>{{ $mensaje }}</li>@endforeach</ul>
                                @enderror
                            </div>
                        </div>

                        {{-- Correo y teléfono comparten fila desde 640 px; en celular van uno debajo del otro --}}
                        <div class="grid grid-cols-1 gap-x-4 gap-y-3.5 sm:grid-cols-2">
                        <div>
                            <label for="email" class="{{ $etiqueta }}">Correo electrónico</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required
                                   autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false"
                                   placeholder="nombre@correo.com" class="{{ $claseCampo('email') }}"
                                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            @error('email')
                                <ul id="email-error" class="mt-2 space-y-1 text-sm font-medium text-red-700">@foreach ($errors->get('email') as $mensaje)<li>{{ $mensaje }}</li>@endforeach</ul>
                                @if (in_array(\App\Http\Requests\Auth\RegisterRequest::CORREO_REGISTRADO, $errors->get('email'), true))
                                    <p class="mt-1 text-sm text-slate-700">
                                        ¿Es tu cuenta?
                                        <a href="{{ route('login') }}" class="font-bold text-blue-950 underline underline-offset-2 hover:text-blue-700">Inicia sesión</a>
                                        o
                                        <a href="{{ route('password.request') }}" class="font-bold text-blue-950 underline underline-offset-2 hover:text-blue-700">recupera tu contraseña</a>.
                                    </p>
                                @endif
                            @enderror
                        </div>

                        <div>
                            <label for="telefono" class="{{ $etiqueta }}">Teléfono <span class="font-normal text-slate-500">(opcional)</span></label>
                            <input id="telefono" name="telefono" type="tel" value="{{ old('telefono') }}"
                                   autocomplete="tel" inputmode="tel" placeholder="Ej. 71234567" class="{{ $claseCampo('telefono') }}"
                                   @error('telefono') aria-invalid="true" aria-describedby="telefono-error" @enderror>
                            @error('telefono')
                                <ul id="telefono-error" class="mt-2 space-y-1 text-sm font-medium text-red-700">@foreach ($errors->get('telefono') as $mensaje)<li>{{ $mensaje }}</li>@endforeach</ul>
                            @enderror
                        </div>
                        </div>

                        <div class="grid grid-cols-1 gap-x-4 gap-y-3.5 sm:grid-cols-2">
                            <div>
                                <label for="password" class="{{ $etiqueta }}">Contraseña</label>
                                <div class="relative">
                                    <input id="password" name="password" type="password" required autocomplete="new-password"
                                           x-model="clave" x-bind:type="mostrarClave ? 'text' : 'password'"
                                           placeholder="Mínimo 8 caracteres"
                                           aria-describedby="{{ $errors->has('password') ? 'password-error ' : '' }}requisitos-clave"
                                           @error('password') aria-invalid="true" @enderror
                                           class="{{ $claseCampo('password') }} pr-12">
                                    <button type="button"
                                            class="absolute inset-y-0 right-0 mt-1 flex w-12 items-center justify-center rounded-r-xl text-slate-500 hover:text-blue-950 focus:outline-none focus:ring-4 focus:ring-blue-700/15"
                                            x-on:click="mostrarClave = ! mostrarClave"
                                            x-bind:aria-pressed="mostrarClave.toString()"
                                            x-bind:aria-label="mostrarClave ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                            aria-pressed="false" aria-label="Mostrar contraseña" aria-controls="password">
                                        <svg x-show="! mostrarClave" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconoOjo }}"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                        <svg x-show="mostrarClave" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconoOjoTachado }}"/></svg>
                                    </button>
                                </div>
                                @error('password')
                                    <ul id="password-error" class="mt-2 space-y-1 text-sm font-medium text-red-700">@foreach ($errors->get('password') as $mensaje)<li>{{ $mensaje }}</li>@endforeach</ul>
                                @enderror
                            </div>
                            <div>
                                <label for="password_confirmation" class="{{ $etiqueta }}">Confirmar contraseña</label>
                                <div class="relative">
                                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                                           x-bind:type="mostrarConfirmacion ? 'text' : 'password'"
                                           placeholder="Repite tu contraseña" class="{{ $claseCampo('password_confirmation') }} pr-12"
                                           @error('password_confirmation') aria-invalid="true" aria-describedby="password_confirmation-error" @enderror>
                                    <button type="button"
                                            class="absolute inset-y-0 right-0 mt-1 flex w-12 items-center justify-center rounded-r-xl text-slate-500 hover:text-blue-950 focus:outline-none focus:ring-4 focus:ring-blue-700/15"
                                            x-on:click="mostrarConfirmacion = ! mostrarConfirmacion"
                                            x-bind:aria-pressed="mostrarConfirmacion.toString()"
                                            x-bind:aria-label="mostrarConfirmacion ? 'Ocultar confirmación de contraseña' : 'Mostrar confirmación de contraseña'"
                                            aria-pressed="false" aria-label="Mostrar confirmación de contraseña" aria-controls="password_confirmation">
                                        <svg x-show="! mostrarConfirmacion" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconoOjo }}"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                        <svg x-show="mostrarConfirmacion" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconoOjoTachado }}"/></svg>
                                    </button>
                                </div>
                                @error('password_confirmation')
                                    <ul id="password_confirmation-error" class="mt-2 space-y-1 text-sm font-medium text-red-700">@foreach ($errors->get('password_confirmation') as $mensaje)<li>{{ $mensaje }}</li>@endforeach</ul>
                                @enderror
                            </div>
                        </div>

                        {{-- Requisitos de Password::defaults() (en producción, además, no debe aparecer en filtraciones) --}}
                        <div id="requisitos-clave" class="-mt-1 text-xs">
                            <p class="mb-1 font-semibold text-slate-600">Tu contraseña necesita:</p>
                            <ul class="grid grid-cols-2 gap-x-4 gap-y-1">
                                @foreach ([
                                    ['clave.length >= 8', '8 caracteres o más'],
                                    ['/[A-ZÁÉÍÓÚÑÜ]/.test(clave)', 'Una mayúscula'],
                                    ['/[a-záéíóúñü]/.test(clave)', 'Una minúscula'],
                                    ['/[0-9]/.test(clave)', 'Un número'],
                                ] as [$condicion, $texto])
                                    <li class="flex items-center gap-1.5" x-bind:class="({{ $condicion }}) ? 'font-semibold text-emerald-700' : 'text-slate-500'">
                                        <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-[10px] font-bold" x-bind:class="({{ $condicion }}) ? 'bg-emerald-600 text-white' : ''" aria-hidden="true">
                                            <span x-show="{{ $condicion }}" x-cloak>✓</span>
                                            <span x-show="! ({{ $condicion }})" class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                        </span>
                                        <span>{{ $texto }}</span>
                                        <span class="sr-only" x-text="({{ $condicion }}) ? '(cumplido)' : '(pendiente)'"></span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <button type="submit"
                                class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-amber-400 px-6 text-base font-extrabold text-blue-950 shadow-sm hover:bg-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-400/40 disabled:cursor-wait disabled:opacity-80"
                                x-bind:disabled="enviando" x-bind:aria-busy="enviando.toString()" aria-busy="false">
                            <svg x-show="enviando" x-cloak class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/></svg>
                            <span x-text="enviando ? 'Creando tu cuenta…' : 'Crear cuenta'">Crear cuenta</span>
                        </button>

                        <p class="text-center text-xs leading-relaxed text-slate-500"><svg class="-mt-0.5 mr-1 inline h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 8V7a3 3 0 1 1 6 0v3H9Z"/></svg>Tus datos están protegidos y solo se usan para gestionar adopciones y voluntariado.</p>
                    </form>

                    <div class="mt-5 border-t border-slate-200 pt-4 text-center text-sm text-slate-600">
                        ¿Ya tienes una cuenta?
                        <a href="{{ route('login') }}" class="font-bold text-blue-950 underline-offset-4 hover:text-blue-700 hover:underline">Inicia sesión</a>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
