@props(['titulo', 'resumen'])
{{--
    Marco común de los documentos legales (Términos, Privacidad y Cookies): título, resumen, fecha de la versión
    vigente (config pol.legal.version) y enlaces entre los tres documentos.
--}}
@php
    $version = \Illuminate\Support\Carbon::parse(config('pol.legal.version'))->translatedFormat('j \d\e F \d\e Y');
    $documentos = [
        ['legal.terminos', 'Términos y condiciones'],
        ['legal.privacidad', 'Política de privacidad'],
        ['legal.cookies', 'Política de cookies'],
    ];
@endphp
<section class="px-4 py-10 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <nav class="flex flex-wrap gap-2 text-sm" aria-label="Documentos legales">
            @foreach ($documentos as [$ruta, $texto])
                <a href="{{ route($ruta) }}" @class(['rounded-full px-4 py-1.5 font-semibold transition', 'bg-blue-950 text-white' => request()->routeIs($ruta), 'border border-slate-200 bg-white text-slate-700 hover:text-blue-950' => ! request()->routeIs($ruta)]) @if (request()->routeIs($ruta)) aria-current="page" @endif>{{ $texto }}</a>
            @endforeach
        </nav>

        <header class="mt-8 border-b border-slate-200 pb-6">
            <h1 class="text-3xl font-extrabold text-blue-950 sm:text-4xl">{{ $titulo }}</h1>
            <p class="mt-3 text-lg leading-relaxed text-slate-600">{{ $resumen }}</p>
            <p class="mt-3 text-sm text-slate-500">Vigente desde el {{ $version }}.</p>
        </header>

        <div class="legal mt-2 text-slate-700">
            {{ $slot }}
        </div>

        <p class="mt-10 rounded-2xl bg-slate-100 p-5 text-sm text-slate-600">
            ¿Dudas sobre este documento? Escríbenos a
            <a href="mailto:{{ config('pol.contacto.correo') }}" class="font-semibold text-blue-800 underline">{{ config('pol.contacto.correo') }}</a>.
        </p>
    </div>
</section>
