@props([
    'titulo',
    'seccion' => null,      // texto pequeño sobre el título (p. ej. «Adopciones»)
    'descripcion' => null,
    'ancho' => 'max-w-7xl', // max-w-7xl, max-w-5xl o max-w-3xl
    'volver' => null,       // URL de «Volver»
    'volverTexto' => 'Volver',
    'avisos' => true,       // false: la pantalla muestra sus propios avisos (p. ej. «Mi perfil» usa códigos en session('status'))
])
{{--
    Estructura común de las pantallas del panel de administración y de las del ciudadano y el voluntario:
    encabezado, acciones, avisos de la sesión y contenido.
    Mismo estilo que el panel principal, Reportes y Auditoría. Uso: <x-admin.pagina titulo="…"> … </x-admin.pagina>
--}}
<x-app-layout>
    <div class="min-h-[calc(100vh-4rem)] bg-slate-50">
        <div class="mx-auto {{ $ancho }} space-y-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
            @if ($volver)
                <a href="{{ $volver }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-blue-950">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.7 15.3a1 1 0 0 1-1.4 0l-4.6-4.6a1 1 0 0 1 0-1.4l4.6-4.6a1 1 0 1 1 1.4 1.4L8.8 10l3.9 3.9a1 1 0 0 1 0 1.4Z" clip-rule="evenodd"/></svg>
                    {{ $volverTexto }}
                </a>
            @endif

            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    @if ($seccion)<p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">{{ $seccion }}</p>@endif
                    <h1 class="mt-1 text-2xl font-bold text-blue-950 sm:text-3xl">{{ $titulo }}</h1>
                    @if ($descripcion)<p class="mt-1 max-w-3xl text-sm text-slate-600 sm:text-base">{{ $descripcion }}</p>@endif
                    {{ $subtitulo ?? '' }}
                </div>
                @isset($acciones)
                    <div class="flex flex-wrap items-center gap-2 sm:shrink-0">{{ $acciones }}</div>
                @endisset
            </header>

            @if ($avisos && session('status'))
                <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif
            @if (session('warning'))
                <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status">{{ session('warning') }}</div>
            @endif

            {{ $slot }}
        </div>
    </div>
</x-app-layout>
