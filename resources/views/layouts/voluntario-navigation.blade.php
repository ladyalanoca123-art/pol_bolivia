{{--
    Menú lateral del voluntario en computadora: degradado azul claro (sin blanco, para que no encandile), de la misma familia que el azul oscuro del administrador pero sin confundirse con él.
    En pantallas chicas el voluntario usa la barra de arriba (layouts/navigation).
--}}
@php
    $usuario = auth()->user();
    $iniciales = mb_strtoupper(mb_substr($usuario->nombres, 0, 1).mb_substr($usuario->apellidos ?? '', 0, 1));
    // El voluntario ya fue aprobado: «Mi postulación» abre directo esa postulación (sin pasar por la lista).
    $aprobada = $usuario->postulacionesVoluntariado()->where('estado', 'aprobada')->latest()->value('id');
    $enlacePostulacion = $aprobada ? route('postulaciones-voluntariado.show', $aprobada) : route('postulaciones-voluntariado.index');
    $opciones = [
        [route('inicio'), 'Mi espacio', request()->routeIs('inicio'), 'M3 11.5 12 4l9 7.5M5.5 10v9.5a.5.5 0 0 0 .5.5h4v-6h4v6h4a.5.5 0 0 0 .5-.5V10'],
        [route('seguimientos.index'), 'Mis seguimientos', request()->routeIs('seguimientos.*'), \App\Support\IconosPestanas::SEGUIMIENTOS],
        [$enlacePostulacion, 'Mi postulación', request()->routeIs('postulaciones-voluntariado.index', 'postulaciones-voluntariado.show'), \App\Support\IconosPestanas::CORAZON],
        [route('solicitudes-adopcion.index'), 'Mis solicitudes', request()->routeIs('solicitudes-adopcion.index', 'solicitudes-adopcion.show'), \App\Support\IconosPestanas::SOLICITUDES],
    ];
@endphp
<aside class="fixed inset-y-0 start-0 z-30 hidden w-64 flex-col border-e border-blue-200 bg-gradient-to-b from-blue-200 via-blue-100 to-sky-200 lg:flex" aria-label="Menú del voluntario">
    {{-- Logo para fondo claro y el nombre de la sección --}}
    <a href="{{ route('inicio') }}" class="block shrink-0 px-6 pt-6" aria-label="POL-Bolivia, ir a Mi espacio">
        <img src="{{ asset('images/pol-bolivia/logos/POL_02_horizontal_web.png') }}" alt="POL-Bolivia, Hacedores de Sueños" class="h-11 w-auto">
        <span class="mt-1 block ps-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-blue-900/80">Voluntariado</span>
    </a>

    <div class="px-4 pt-5">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-400 px-3 py-1 text-[13px] font-semibold text-blue-950">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 21s-7.5-4.6-7.5-10.4A4.4 4.4 0 0 1 12 8a4.4 4.4 0 0 1 7.5 2.6C19.5 16.4 12 21 12 21Z" /></svg>
            Equipo voluntario
        </span>
    </div>

    <nav class="mt-4 flex-1 space-y-1 px-3">
        @foreach ($opciones as [$url, $texto, $activo, $icono])
            <a href="{{ $url }}" @if ($activo) aria-current="page" @endif
               class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-[15px] font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 {{ $activo ? 'bg-blue-950 text-white shadow-sm' : 'text-blue-950 hover:bg-white/70' }}">
                <svg class="h-5 w-5 shrink-0 {{ $activo ? 'text-white' : 'text-blue-900' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icono }}" /></svg>
                {{ $texto }}
            </a>
        @endforeach
    </nav>

    {{-- Cuenta --}}
    <div class="p-4">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-400 text-sm font-bold text-blue-950" aria-hidden="true">{{ $iniciales }}</span>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-blue-950">{{ $usuario->nombres }} {{ $usuario->apellidos }}</p>
                <p class="text-xs text-slate-600">Voluntario/a</p>
            </div>
        </div>
        <div class="mt-3 space-y-2">
            <a href="{{ route('profile.edit') }}" class="flex min-h-10 items-center gap-3 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-blue-950 hover:bg-sky-50">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ \App\Support\IconosPestanas::PERSONA }}" /></svg>
                Mi perfil
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex min-h-10 w-full items-center gap-3 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-blue-950 hover:bg-sky-50">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 16l-4-4 4-4M6 12h10" /></svg>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</aside>
