{{--
    «Mi espacio»: inicio del ciudadano y del voluntario (App\Http\Controllers\MiEspacioController).
    La vista solo presenta; los datos llegan preparados y son siempre de la propia persona.
--}}
@php
    $usuario = auth()->user();
    $ahora = now();
    $saludo = \App\Services\PanelAdministrador::saludo($ahora);
    $nombre = \Illuminate\Support\Str::of((string) $usuario->nombres)->trim()->before(' ');
    $tarjeta = 'rounded-2xl border border-slate-200 bg-white';
    $enlace = 'inline-flex items-center gap-1 text-sm font-semibold text-blue-800 hover:text-blue-950 hover:underline';
    $estadosPostulacion = ['pendiente' => 'En revisión', 'aprobada' => 'Aprobada', 'rechazada' => 'No aprobada', 'cancelada' => 'Cancelada'];
@endphp
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-5 sm:px-6 sm:py-8 lg:px-8">

        {{-- Bienvenida --}}
        <section class="relative overflow-hidden rounded-3xl bg-[#0d2138] text-white">
            <img src="{{ asset($esVoluntario ? 'images/pol-bolivia/grupo-voluntarios.jpg' : 'images/pol-bolivia/mi-espacio-ciudadano.jpg') }}" alt=""
                 class="absolute inset-y-0 right-0 h-full w-full object-cover {{ $esVoluntario ? 'object-[center_35%]' : 'object-[center_25%] sm:w-[58%]' }}">
            @if ($esVoluntario)
                <div class="absolute inset-0 bg-gradient-to-r from-[#0d2138] via-[#0d2138]/90 to-[#0d2138]/30" aria-hidden="true"></div>
            @else
                {{-- Ciudadano: en celular la foto ocupa todo el fondo; desde tablet ocupa la derecha y su borde se funde con el azul. --}}
                <div class="absolute inset-0 bg-gradient-to-r from-[#0d2138] from-30% via-[#0d2138]/70 via-50% to-transparent to-80% sm:hidden" aria-hidden="true"></div>
                <div class="absolute inset-y-0 left-[42%] hidden w-[32%] bg-gradient-to-r from-[#0d2138] to-transparent sm:block" aria-hidden="true"></div>
            @endif
            <div class="relative max-w-2xl px-6 py-7 sm:px-10 sm:py-12">
                <p class="text-xs font-medium text-amber-300 sm:text-sm">{{ \Illuminate\Support\Str::ucfirst($ahora->translatedFormat('l j \d\e F')) }}</p>
                <h1 class="mt-2 text-2xl font-bold sm:text-4xl">{{ $saludo }}@if ($nombre->isNotEmpty()), {{ $nombre }}@endif</h1>
                <p class="mt-2 text-sm text-slate-200 sm:mt-3 sm:text-lg">
                    {{ $esVoluntario
                        ? 'Gracias por acompañar a las familias que adoptaron.'
                        : 'Sigue tus solicitudes y conoce a las mascotas que esperan un hogar.' }}
                </p>
                <a href="{{ $esVoluntario ? route('seguimientos.index') : route('mascotas.catalogo') }}"
                   class="mt-5 inline-flex min-h-10 items-center rounded-full bg-amber-400 px-5 py-2 text-sm font-bold text-blue-950 hover:bg-amber-300 sm:mt-6">
                    {{ $esVoluntario ? 'Ver mis seguimientos' : 'Conocer mascotas' }}
                </a>
            </div>
        </section>

        {{-- Aviso: solo si falta el teléfono --}}
        @unless ($usuario->telefono)
            <div class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between" role="status">
                <p class="text-sm text-amber-950"><span class="font-semibold">Falta tu teléfono (WhatsApp).</span> Lo necesitamos para contactarte.</p>
                <x-admin.boton variante="secundario" tamano="chico" :href="route('profile.edit')">Agregar teléfono</x-admin.boton>
            </div>
        @endunless

        {{-- Resumen: una franja con tres datos --}}
        @php
            $resumen = $esVoluntario
                ? [
                    ['Seguimientos activos', $seguimientos->count(), null],
                    ['Visitas por hacer', $proximasVisitas->count(), null],
                    ['Visitas realizadas', $seguimientos->sum('visitas_realizadas'), null],
                ]
                : [
                    ['Solicitudes en trámite', $solicitudesActivas, 'de '.\App\Models\SolicitudAdopcion::MAX_ACTIVAS],
                    ['Mascotas esperando hogar', $mascotasDisponibles, null],
                    ['Voluntariado', $postulacion ? ($estadosPostulacion[$postulacion->estado] ?? ucfirst($postulacion->estado)) : 'Sin postulación', null],
                ];
        @endphp
        <dl class="grid grid-cols-3 divide-x divide-slate-100 {{ $tarjeta }}">
            @foreach ($resumen as [$titulo, $valor, $extra])
                <div class="min-w-0 px-3 py-4 sm:px-6 sm:py-5">
                    <dt class="text-xs text-slate-500 sm:text-sm">{{ $titulo }}</dt>
                    <dd class="mt-1 font-bold text-blue-950 {{ is_numeric($valor) ? 'text-2xl sm:text-3xl' : 'text-sm sm:text-lg' }}">
                        {{ $valor }}@if ($extra) <span class="text-sm font-medium text-slate-400">{{ $extra }}</span>@endif
                    </dd>
                </div>
            @endforeach
        </dl>

        @if ($esVoluntario)
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Seguimientos activos --}}
                <section class="min-w-0 {{ $tarjeta }}" aria-labelledby="titulo-seguimientos">
                    <div class="flex items-center justify-between px-5 py-4 sm:px-6">
                        <h2 id="titulo-seguimientos" class="text-base font-semibold text-blue-950">Mis seguimientos activos</h2>
                        <a href="{{ route('seguimientos.index') }}" class="{{ $enlace }}">Ver todos <span aria-hidden="true">→</span></a>
                    </div>
                    @forelse ($seguimientos as $seguimiento)
                        @php($mascota = $seguimiento->solicitud->mascota)
                        <a href="{{ route('seguimientos.show', $seguimiento) }}" class="flex items-center gap-4 border-t border-slate-100 px-5 py-4 hover:bg-slate-50 sm:px-6">
                            <x-foto-mascota :mascota="$mascota" class="h-12 w-12 rounded-xl" />
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-blue-950">{{ $mascota->nombre }}</p>
                                <p class="truncate text-sm text-slate-500">Familia {{ $seguimiento->solicitud->solicitante->apellidos }} · {{ $seguimiento->zona_aproximada }}</p>
                            </div>
                            <p class="shrink-0 text-sm text-slate-500"><span class="font-semibold text-blue-950">{{ $seguimiento->visitas_realizadas }} de 2</span> visitas</p>
                        </a>
                    @empty
                        <x-admin.vacio class="border-t border-slate-100" titulo="Todavía no tienes seguimientos asignados." texto="Cuando la fundación te asigne una adopción, aparecerá aquí." />
                    @endforelse
                </section>

                {{-- Próximas visitas --}}
                <section class="min-w-0 {{ $tarjeta }}" aria-labelledby="titulo-visitas">
                    <h2 id="titulo-visitas" class="px-5 py-4 text-base font-semibold text-blue-950 sm:px-6">Próximas visitas</h2>
                    @forelse ($proximasVisitas as $visita)
                        @php($atrasada = $visita->fecha_programada->lt($ahora->copy()->startOfDay()))
                        <a href="{{ route('seguimientos.show', $visita->seguimiento) }}" class="flex items-center gap-4 border-t border-slate-100 px-5 py-4 hover:bg-slate-50 sm:px-6">
                            <span class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl border {{ $atrasada ? 'border-red-200 text-red-700' : 'border-slate-200 text-blue-950' }}">
                                <span class="text-base font-bold leading-none">{{ $visita->fecha_programada->format('d') }}</span>
                                <span class="mt-0.5 text-[11px] text-slate-500">{{ $visita->fecha_programada->translatedFormat('M') }}</span>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-blue-950">{{ $visita->seguimiento->solicitud->mascota->nombre }}</p>
                                <p class="text-sm {{ $atrasada ? 'font-medium text-red-700' : 'text-slate-500' }}">{{ $atrasada ? 'Atrasada: regístrala o reprográmala' : 'Programada' }}</p>
                            </div>
                        </a>
                    @empty
                        <p class="border-t border-slate-100 px-5 py-6 text-sm text-slate-500 sm:px-6">No tienes visitas programadas.</p>
                    @endforelse
                </section>
            </div>

            @if ($solicitudes->isNotEmpty())
                <section class="{{ $tarjeta }} px-5 py-4 sm:px-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-semibold text-blue-950">Mis solicitudes de adopción</h2>
                        <a href="{{ route('solicitudes-adopcion.index') }}" class="{{ $enlace }}">Ver todas <span aria-hidden="true">→</span></a>
                    </div>
                    <ul class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                        @foreach ($solicitudes as $solicitud)
                            <li class="flex items-center gap-2">
                                <a href="{{ route('solicitudes-adopcion.show', $solicitud) }}" class="font-medium text-blue-900 hover:underline">{{ $solicitud->mascota->nombre }}</a>
                                <x-admin.estado de="solicitud" :valor="$solicitud->estado" />
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @else
            @php($invitarVoluntariado = ! $postulacion || $postulacion->estado === 'rechazada')
            <div class="grid grid-cols-1 gap-6 {{ $invitarVoluntariado ? 'lg:grid-cols-3' : '' }}">
                {{-- Mis solicitudes, cada una con «qué sigue» --}}
                <section class="min-w-0 {{ $tarjeta }} {{ $invitarVoluntariado ? 'lg:col-span-2' : '' }}" aria-labelledby="titulo-solicitudes">
                    <div class="flex items-center justify-between px-5 py-4 sm:px-6">
                        <h2 id="titulo-solicitudes" class="text-base font-semibold text-blue-950">Mis solicitudes de adopción</h2>
                        @if ($solicitudes->isNotEmpty())<a href="{{ route('solicitudes-adopcion.index') }}" class="{{ $enlace }}">Ver todas <span aria-hidden="true">→</span></a>@endif
                    </div>
                    @forelse ($solicitudes as $solicitud)
                        <a href="{{ route('solicitudes-adopcion.show', $solicitud) }}" class="flex items-start gap-4 border-t border-slate-100 px-5 py-4 hover:bg-slate-50 sm:px-6">
                            <x-foto-mascota :mascota="$solicitud->mascota" class="h-12 w-12 rounded-xl" />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <p class="font-semibold text-blue-950">{{ $solicitud->mascota->nombre }}</p>
                                    <x-admin.estado de="solicitud" :valor="$solicitud->estado" />
                                </div>
                                <p class="mt-1 text-sm text-slate-600">{{ $solicitud->queSigue() }}</p>
                            </div>
                            <span class="mt-3 shrink-0 text-slate-300" aria-hidden="true">›</span>
                        </a>
                    @empty
                        <x-admin.vacio class="border-t border-slate-100" titulo="Todavía no tienes solicitudes de adopción." texto="Elige una mascota del catálogo y completa su solicitud. Te contactaremos por WhatsApp.">
                            <x-admin.boton variante="destacado" :href="route('mascotas.catalogo')">Ver mascotas disponibles</x-admin.boton>
                        </x-admin.vacio>
                    @endforelse
                </section>

                @if ($invitarVoluntariado)
                    <section class="flex flex-col justify-between rounded-2xl bg-[#0d2138] p-5 text-white sm:p-6">
                        <div>
                            <h2 class="text-base font-semibold">¿Quieres ayudar más?</h2>
                            <p class="mt-1 text-sm text-slate-300">Súmate como voluntario/a y acompaña a las familias que adoptan.</p>
                        </div>
                        <a href="{{ route('postulaciones-voluntariado.index') }}" class="mt-5 inline-flex self-start rounded-full bg-amber-400 px-5 py-2 text-sm font-bold text-blue-950 hover:bg-amber-300">Conocer el voluntariado</a>
                    </section>
                @endif
            </div>

            @unless ($invitarVoluntariado)
                <p class="text-sm text-slate-500">Voluntariado: <a href="{{ route('postulaciones-voluntariado.index') }}" class="{{ $enlace }}">Ver mi postulación <span aria-hidden="true">→</span></a></p>
            @endunless

            {{-- Mascotas que esperan hogar --}}
            @if ($mascotas->isNotEmpty())
                <section aria-labelledby="titulo-mascotas">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 id="titulo-mascotas" class="text-base font-semibold text-blue-950">Esperan un hogar</h2>
                        <a href="{{ route('mascotas.catalogo') }}" class="{{ $enlace }}">Ver todas <span aria-hidden="true">→</span></a>
                    </div>
                    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                        @foreach ($mascotas as $mascota)
                            <li>
                                <a href="{{ route('mascotas.public.show', $mascota) }}" class="group block">
                                    <x-foto-mascota :mascota="$mascota" class="aspect-square w-full rounded-2xl" />
                                    <p class="mt-2 truncate text-sm font-semibold text-blue-950 group-hover:underline">{{ $mascota->nombre }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ ucfirst($mascota->especie) }} · {{ $mascota->edad_formateada }}</p>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endif
    </div>
</x-app-layout>
