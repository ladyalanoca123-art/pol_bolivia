<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'POL-Bolivia')</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=caveat:600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-stone-50 font-sans text-stone-800 antialiased">
    <header class="sticky top-0 z-30 border-b border-stone-200 bg-stone-50/95 backdrop-blur">
        <nav class="mx-auto flex max-w-7xl items-center justify-between gap-5 px-4 py-4 sm:px-6 lg:px-8" aria-label="Navegación principal">
            <a href="{{ route('home') }}" class="flex items-center gap-3 text-xl font-extrabold tracking-tight text-blue-900">
                <img src="{{ asset('images/pol-bolivia/logo.png') }}" alt="Logotipo de POL-Bolivia" class="h-10 w-10 object-contain">
                <span class="hidden whitespace-nowrap sm:inline">POL-Bolivia</span>
            </a>
            <div class="hidden items-center gap-5 text-sm font-medium md:flex">
                <a href="{{ route('home') }}" class="hover:text-blue-700">Inicio</a>
                <a href="{{ route('mascotas.catalogo') }}" class="hover:text-blue-700">Mascotas</a>
                <a href="{{ route('home') }}#como-adoptar" class="hover:text-blue-700">¿Cómo adoptar?</a>
                <a href="{{ route('home') }}#voluntariado" class="hover:text-blue-700">Ser voluntario</a>
            </div>
            <div class="flex items-center gap-3">
                <details class="relative md:hidden">
                    <summary class="cursor-pointer list-none rounded-full border border-blue-700 px-4 py-2 text-sm font-semibold text-blue-800 [&::-webkit-details-marker]:hidden">Menú</summary>
                    <div class="absolute right-0 mt-2 grid w-52 gap-1 rounded-xl bg-white p-2 shadow-lg ring-1 ring-stone-200">
                        <a href="{{ route('home') }}" class="rounded-lg px-3 py-2 text-sm hover:bg-sky-50">Inicio</a>
                        <a href="{{ route('mascotas.catalogo') }}" class="rounded-lg px-3 py-2 text-sm hover:bg-sky-50">Mascotas</a>
                        <a href="{{ route('home') }}#como-adoptar" class="rounded-lg px-3 py-2 text-sm hover:bg-sky-50">¿Cómo adoptar?</a>
                        <a href="{{ route('home') }}#voluntariado" class="rounded-lg px-3 py-2 text-sm hover:bg-sky-50">Ser voluntario</a>
                    </div>
                </details>
                @auth
                    @php
                        $usuario = auth()->user();
                        $enlaceCuenta = match (true) {
                            $usuario->hasRole('ciudadano', 'voluntario') => ['texto' => 'Mi espacio', 'ruta' => route('inicio')],
                            $usuario->hasRole('administrador') => ['texto' => 'Panel de administración', 'ruta' => route('dashboard')],
                        };
                    @endphp
                    <a href="{{ $enlaceCuenta['ruta'] }}" class="whitespace-nowrap rounded-full bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">{{ $enlaceCuenta['texto'] }}</a>
                @else
                    <a href="{{ route('login') }}" class="whitespace-nowrap rounded-full bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Iniciar sesión</a>
                @endauth
            </div>
        </nav>
    </header>
    <main class="flex-1">@yield('content')</main>
    @php
        // Contacto del pie: el teléfono es el mismo del botón flotante (config('pol.whatsapp.consultas')).
        $telefonoPie = \App\Support\ContactoTelefonico::numeroInternacional((string) config('pol.whatsapp.consultas'));
        $correoPie = config('pol.contacto.correo');
        $huellaPie = 'M8.35 3c1.18-.17 2.43 1.12 2.79 2.9.36 1.77-.29 3.35-1.47 3.53-1.17.18-2.43-1.11-2.8-2.89C6.5 4.77 7.17 3.19 8.35 3m7.15 0c1.19.19 1.85 1.77 1.5 3.54-.38 1.78-1.63 3.07-2.81 2.89-1.19-.18-1.84-1.76-1.47-3.53.36-1.78 1.61-3.07 2.78-2.9M3 7.6c1.14-.49 2.69.4 3.5 1.95.76 1.58.5 3.24-.63 3.73-1.13.49-2.67-.39-3.46-1.96C1.62 9.75 1.9 8.08 3 7.6m18 0c1.1.48 1.38 2.15.59 3.72-.79 1.57-2.33 2.45-3.46 1.96-1.13-.49-1.39-2.15-.63-3.73C18.31 8 19.86 7.11 21 7.6m-1.67 10.78c.04.94-.68 1.98-1.54 2.37-1.79.82-3.91-.88-5.9-.88-1.99 0-4.13 1.77-5.89.88-1-.49-1.69-1.79-1.56-2.87.18-1.49 1.97-2.29 3.03-3.38 1.41-1.41 2.41-4.06 4.42-4.06 2 0 3.06 2.61 4.41 4.06 1.11 1.22 2.96 2.25 3.03 3.88Z';
        $corazon = 'M12 21s-7.5-4.6-9.6-9.3C.9 8.2 3.1 4.5 6.7 4.5c2 0 3.6 1.1 4.3 2.6.7-1.5 2.3-2.6 4.3-2.6 3.6 0 5.8 3.7 4.3 7.2C19.5 16.4 12 21 12 21Z';
        $grupo = 'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7.5 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM9 13c-3.3 0-6 1.8-6 4v2h12v-2c0-2.2-2.7-4-6-4Zm7.5 0c-.6 0-1.1.1-1.6.2 1.3.9 2.1 2.2 2.1 3.8v2h4v-2c0-2.2-2-4-4.5-4Z';
        $enlacesPie = [
            [route('home'), 'Inicio', 'M12 3 2 11h3v9h5v-6h4v6h5v-9h3L12 3Z'],
            [route('mascotas.catalogo'), 'Mascotas', $huellaPie],
            [route('home').'#como-adoptar', '¿Cómo adoptar?', 'M6 2h8l6 6v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1Zm7 1.5V9h5.5L13 3.5ZM8 13v1.6h8V13H8Zm0 3.4V18h6v-1.6H8Z'],
            [route('postulaciones-voluntariado.create'), 'Ser voluntario', $grupo],
        ];
        $compromisos = [
            ['Adopción responsable', $corazon],
            ['Seguimiento post-adopción', 'M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3Zm-1.2 14L7 12.2l1.4-1.4 2.4 2.4 4.8-4.8 1.4 1.4-6.2 6.2Z'],
            ['Trabajo con voluntarios', $grupo],
            ['Más segundas oportunidades', 'M12 21c-.3-4.6-2.8-7.7-7.6-9.1C5 6.8 8.6 4 12 2.5c.6 3 .4 6.2-.6 9.3 1.6-2.6 4.6-4.3 8.6-4.6-.6 6-3.4 10.5-8 13.8Z'],
        ];
        $iconoPie = fn (string $d, string $clase = 'h-5 w-5 text-sky-300') => '<svg class="'.$clase.' shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="'.$d.'"/></svg>';
        $tituloPie = 'text-lg font-bold text-white';
        $rayaPie = 'mt-3 h-0.5 w-9 rounded-full bg-sky-400';
    @endphp
    <footer class="relative text-slate-200">
        {{-- Ola de transición entre la página y el pie --}}
        <svg class="block h-12 w-full sm:h-16" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0 52C240 28 480 22 720 36s480 38 720 4V80H0Z" class="fill-sky-300/30"/>
            <path d="M0 60C260 40 520 38 760 50s460 26 680-6V80H0Z" fill="#0d2138"/>
        </svg>

        <div class="relative overflow-hidden bg-[#0d2138]">
            {{-- Huellas decorativas --}}
            <svg class="pointer-events-none absolute -right-2 top-2 h-32 w-32 rotate-12 text-sky-300/10" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $huellaPie }}"/></svg>
            <svg class="pointer-events-none absolute bottom-10 left-[24%] hidden h-14 w-14 -rotate-12 text-sky-300/10 lg:block" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $huellaPie }}"/></svg>

            <div class="relative mx-auto grid max-w-7xl gap-10 px-4 pb-14 pt-8 sm:px-6 md:grid-cols-2 lg:grid-cols-[1.15fr_0.85fr_1fr_1.15fr] lg:px-8">
                <div>
                    <img src="{{ asset('images/pol-bolivia/logos/POL_04_fondo_oscuro.png') }}" alt="POL-Bolivia, Hacedores de Sueños" class="h-12 w-auto">
                    <p class="mt-4 max-w-xs text-base leading-relaxed text-slate-200">Hacemos realidad el vínculo entre las mascotas y las familias.</p>
                    <x-redes-sociales tono="oscuro" whatsapp class="mt-5" />
                    <ul class="mt-5 space-y-2 text-sm text-slate-300">
                        <li class="flex items-center gap-2.5">{!! $iconoPie('M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z') !!} Cochabamba, Bolivia</li>
                        @if ($correoPie)
                            <li><a href="mailto:{{ $correoPie }}" class="flex items-center gap-2.5 break-all hover:text-white">{!! $iconoPie('M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm9 7.2L4.4 7H4v.6l8 5.6 8-5.6V7h-.4L12 12.2Z') !!} {{ $correoPie }}</a></li>
                        @endif
                        @if ($telefonoPie)
                            <li class="flex items-center gap-2.5">{!! $iconoPie('M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.6 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.6 3.6a1 1 0 0 1-.25 1z') !!} +{{ substr($telefonoPie, 0, 3) }} {{ substr($telefonoPie, 3, 4) }} {{ substr($telefonoPie, 7) }}</li>
                        @endif
                    </ul>
                    <p class="mt-6 flex origin-left -rotate-6 items-center gap-2 font-['Caveat'] text-3xl leading-none text-white/90" aria-hidden="true">Adopta, no compres {!! $iconoPie($corazon, 'h-6 w-6 text-sky-300') !!}</p>
                </div>

                <nav aria-label="Enlaces rápidos">
                    <h2 class="{{ $tituloPie }}">Enlaces rápidos</h2>
                    <div class="{{ $rayaPie }}"></div>
                    <ul class="mt-6 space-y-4 text-[15px]">
                        @foreach ($enlacesPie as [$url, $texto, $icono])
                            <li><a href="{{ $url }}" class="flex items-center gap-3.5 hover:text-white">{!! $iconoPie($icono) !!} {{ $texto }}</a></li>
                        @endforeach
                    </ul>
                </nav>

                <div>
                    <h2 class="{{ $tituloPie }}">Nuestro compromiso</h2>
                    <div class="{{ $rayaPie }}"></div>
                    <ul class="mt-6 space-y-4 text-[15px]">
                        @foreach ($compromisos as [$texto, $icono])
                            <li class="flex items-center gap-3.5">{!! $iconoPie($icono) !!} {{ $texto }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="self-start rounded-2xl border border-sky-300/20 bg-white/[0.04] p-6">
                    <h2 class="flex items-center gap-3 text-lg font-bold text-white">{!! $iconoPie($huellaPie, 'h-6 w-6 text-white') !!} ¿Quieres ser voluntario?</h2>
                    <p class="mt-3 text-base leading-relaxed text-slate-200">Tu tiempo y esfuerzo pueden cambiar una vida.</p>
                    <a href="{{ route('postulaciones-voluntariado.create') }}" class="group mt-6 inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-full bg-emerald-500 px-4 py-3.5 text-sm font-extrabold text-white shadow-lg shadow-black/20 transition hover:bg-emerald-400">
                        {!! $iconoPie('M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm0 2c-4.4 0-8 2.2-8 5v2h16v-2c0-2.8-3.6-5-8-5Z', 'h-5 w-5 text-white') !!}
                        QUIERO SER VOLUNTARIO
                        <span class="transition group-hover:translate-x-0.5" aria-hidden="true">›</span>
                    </a>
                </div>
            </div>

            {{-- Barra final; el margen derecho deja libre el botón flotante de WhatsApp --}}
            <div class="relative border-t border-sky-300/15">
                <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 pb-24 pt-5 text-sm text-slate-300 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:pb-5 sm:pr-64 lg:px-8 lg:pr-72">
                    <div class="space-y-2">
                        <p><span class="font-semibold text-white">© {{ now()->year }} POL-Bolivia</span> | Hacedores de Sueños. Todos los derechos reservados.</p>
                        <nav class="flex flex-wrap gap-x-4 gap-y-1 text-xs" aria-label="Documentos legales">
                            <a href="{{ route('legal.terminos') }}" class="hover:text-white hover:underline">Términos y condiciones</a>
                            <a href="{{ route('legal.privacidad') }}" class="hover:text-white hover:underline">Política de privacidad</a>
                            <a href="{{ route('legal.cookies') }}" class="hover:text-white hover:underline">Política de cookies</a>
                        </nav>
                    </div>
                    <p class="flex items-center gap-2.5">{!! $iconoPie($corazon, 'h-5 w-5 text-sky-300') !!} Juntos por un futuro mejor <span class="mx-1 h-5 w-px bg-sky-300/30" aria-hidden="true"></span> {!! $iconoPie($huellaPie, 'h-5 w-5 text-sky-300') !!}</p>
                </div>
            </div>
        </div>
    </footer>
    {{-- Consultas por WhatsApp; en la ficha de una mascota el mensaje lleva su nombre (@section('consulta_mascota')). --}}
    <x-whatsapp-flotante :mascota="html_entity_decode(trim($__env->yieldContent('consulta_mascota')), ENT_QUOTES)" />
    {{-- Aviso de "ENVIAR SOLICITUD" para visitantes y cuentas que no son de ciudadano --}}
    @unless (auth()->user()?->hasRole('ciudadano'))
        <x-aviso-solicitud-adopcion />
    @endunless
</body>
</html>
