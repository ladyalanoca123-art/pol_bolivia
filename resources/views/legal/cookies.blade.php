@extends('layouts.public')

@section('title', 'Política de cookies | POL-Bolivia')

@section('content')
    @php
        $minutos = (int) config('session.lifetime');
        $cookies = [
            [config('session.cookie'), 'Mantiene tu sesión iniciada mientras navegas.', 'Se borra tras '.intdiv($minutos, 60).' horas sin actividad.'],
            ['XSRF-TOKEN', 'Protege los formularios para que nadie pueda enviarlos en tu nombre desde otra página.', 'La misma duración que la sesión.'],
            ['remember_web_…', 'Solo si marcas «Recordarme» al iniciar sesión: evita que tengas que volver a entrar cada vez.', 'Hasta que cierres sesión.'],
        ];
    @endphp
    <x-documento-legal titulo="Política de cookies" resumen="Las cookies son pequeños archivos que el sitio guarda en tu navegador. Usamos solo las necesarias para que el sistema funcione.">
        <h2>1. Cookies que usamos</h2>
        <p>Todas son <strong>necesarias</strong>: sin ellas no podrías iniciar sesión ni enviar formularios de forma segura.</p>
        <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200">
            <table class="w-full min-w-[32rem] text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr><th class="px-4 py-3 font-semibold">Cookie</th><th class="px-4 py-3 font-semibold">Para qué sirve</th><th class="px-4 py-3 font-semibold">Duración</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($cookies as [$nombre, $uso, $duracion])
                        <tr><td class="px-4 py-3 font-mono text-xs text-blue-950">{{ $nombre }}</td><td class="px-4 py-3">{{ $uso }}</td><td class="px-4 py-3">{{ $duracion }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <h2>2. Lo que no usamos</h2>
        <ul>
            <li>No usamos cookies de publicidad ni de redes sociales.</li>
            <li>No usamos herramientas de estadísticas que sigan lo que haces.</li>
            <li>Los tipos de letra se cargan desde Bunny Fonts, un servicio que no usa cookies ni rastrea a los visitantes.</li>
        </ul>

        <h2>3. ¿Por qué no te pedimos aceptar las cookies?</h2>
        <p>El aviso de «aceptar cookies» se pide para las cookies que no son necesarias, como las de publicidad o seguimiento. Como solo usamos las indispensables para que el sistema funcione y sea seguro, no hace falta pedir tu consentimiento; basta con informarte, como lo hacemos aquí.</p>

        <h2>4. Enlaces a otros sitios</h2>
        <p>Los botones de WhatsApp, Facebook, Instagram y TikTok te llevan a esos sitios, que tienen sus propias políticas de cookies.</p>

        <h2>5. Cómo borrarlas</h2>
        <p>Puedes borrar las cookies desde la configuración de tu navegador. Si lo haces, se cerrará tu sesión y tendrás que volver a iniciarla.</p>
    </x-documento-legal>
@endsection
