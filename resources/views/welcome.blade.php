@extends('layouts.public')

@section('title', 'POL-Bolivia | Adopción responsable')

@section('content')
    <section class="bg-sky-50 px-4 py-10 sm:px-6 sm:py-14 lg:px-8 lg:py-16">
        <div class="mx-auto grid max-w-7xl overflow-hidden rounded-3xl bg-blue-950 shadow-xl lg:grid-cols-[0.9fr_1.1fr]">
            <div class="flex flex-col justify-center px-6 py-12 text-white sm:px-10 lg:px-14 lg:py-16">
                <p class="text-sm font-bold tracking-[0.2em] text-amber-300">ADOPCIÓN RESPONSABLE EN COCHABAMBA</p>
                <h1 class="mt-5 text-4xl font-extrabold tracking-tight sm:text-5xl">Encuentra un compañero que necesita un hogar</h1>
                <p class="mt-5 max-w-xl text-lg leading-relaxed text-sky-100">Adopta, ayuda y forma parte de una comunidad que trabaja por el bienestar animal.</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="#mascotas" class="rounded-full bg-amber-400 px-6 py-3 text-center font-extrabold text-blue-950 transition hover:bg-amber-300">VER MASCOTAS DISPONIBLES</a>
                    <a href="{{ route('postulaciones-voluntariado.create') }}" class="rounded-full border border-sky-200 px-6 py-3 text-center font-extrabold text-white transition hover:bg-white/10">QUIERO SER VOLUNTARIO</a>
                </div>
            </div>

            <div x-data="{ activo: 0, total: 4 }" x-init="setInterval(() => activo = (activo + 1) % total, 6500)" class="relative min-h-[21rem] overflow-hidden bg-blue-900 sm:min-h-[28rem] lg:min-h-full">
                <div x-show="activo === 0" x-transition.opacity.duration.700ms class="absolute inset-0"><img src="{{ asset('images/pol-bolivia/hero-animales.jpg') }}" alt="Animales que necesitan un hogar" class="h-full w-full object-cover"></div>
                <div x-show="activo === 1" x-transition.opacity.duration.700ms class="absolute inset-0"><img src="{{ asset('images/pol-bolivia/hero-adopcion.jpg') }}" alt="Adopción responsable" class="h-full w-full object-cover"></div>
                <div x-show="activo === 2" x-transition.opacity.duration.700ms class="absolute inset-0"><img src="{{ asset('images/pol-bolivia/hero-rescate.jpg') }}" alt="Trabajo de rescate animal" class="h-full w-full object-cover"></div>
                <div x-show="activo === 3" x-transition.opacity.duration.700ms class="absolute inset-0"><img src="{{ asset('images/pol-bolivia/hero-actividad.jpg') }}" alt="Actividades de POL-Bolivia" class="h-full w-full object-cover"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-blue-950/60 via-transparent to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-4 p-5 sm:p-7">
                    <div class="flex gap-2" aria-label="Indicadores del carrusel">
                        <button type="button" @click="activo = 0" :aria-current="activo === 0" class="h-2.5 rounded-full transition-all" :class="activo === 0 ? 'w-8 bg-amber-300' : 'w-2.5 bg-white/70'" aria-label="Mostrar animales"></button>
                        <button type="button" @click="activo = 1" :aria-current="activo === 1" class="h-2.5 rounded-full transition-all" :class="activo === 1 ? 'w-8 bg-amber-300' : 'w-2.5 bg-white/70'" aria-label="Mostrar adopción"></button>
                        <button type="button" @click="activo = 2" :aria-current="activo === 2" class="h-2.5 rounded-full transition-all" :class="activo === 2 ? 'w-8 bg-amber-300' : 'w-2.5 bg-white/70'" aria-label="Mostrar rescate"></button>
                        <button type="button" @click="activo = 3" :aria-current="activo === 3" class="h-2.5 rounded-full transition-all" :class="activo === 3 ? 'w-8 bg-amber-300' : 'w-2.5 bg-white/70'" aria-label="Mostrar actividades"></button>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="activo = (activo + total - 1) % total" class="rounded-full bg-white/90 px-3 py-2 text-sm font-bold text-blue-950 hover:bg-white" aria-label="Diapositiva anterior">←</button>
                        <button type="button" @click="activo = (activo + 1) % total" class="rounded-full bg-white/90 px-3 py-2 text-sm font-bold text-blue-950 hover:bg-white" aria-label="Diapositiva siguiente">→</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="mascotas" class="scroll-mt-24 bg-white px-4 py-16 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <nav class="flex flex-wrap justify-center gap-3" aria-label="Categorías de mascotas">
                <a href="{{ route('mascotas.catalogo') }}" class="rounded-full bg-blue-800 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-900">🐾 Todas</a>
                <a href="{{ route('mascotas.catalogo', ['especie' => 'perro']) }}" class="rounded-full border border-blue-200 bg-sky-50 px-5 py-2.5 text-sm font-bold text-blue-900 transition hover:bg-sky-100">🐶 Perros</a>
                <a href="{{ route('mascotas.catalogo', ['especie' => 'gato']) }}" class="rounded-full border border-blue-200 bg-sky-50 px-5 py-2.5 text-sm font-bold text-blue-900 transition hover:bg-sky-100">🐱 Gatos</a>
            </nav>
            @if ($mascotas->isNotEmpty())
                <div class="mt-8 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
                    @foreach ($mascotas as $mascota)<x-mascota-publica-card :mascota="$mascota" />@endforeach
                    <a href="{{ route('mascotas.catalogo') }}" class="group flex min-h-[31rem] flex-col items-center justify-center rounded-2xl bg-blue-950 p-6 text-center text-white shadow-sm transition hover:-translate-y-1 hover:bg-blue-900 hover:shadow-lg">
                        <span class="text-5xl" aria-hidden="true">🐾</span>
                        <h2 class="mt-5 text-2xl font-extrabold">Más mascotas esperan un hogar</h2>
                        <span class="mt-6 rounded-full bg-amber-400 px-5 py-3 text-sm font-extrabold text-blue-950">EXPLORAR CATÁLOGO</span>
                    </a>
                </div>
            @else
                <p class="mt-8 rounded-xl bg-white p-6 text-stone-600 ring-1 ring-stone-200">Actualmente no hay mascotas disponibles para adopción.</p>
            @endif
        </div>
    </section>

    <section id="como-adoptar" class="scroll-mt-24 bg-sky-50 px-4 py-16 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-4xl text-center"><p class="text-sm font-bold tracking-wider text-blue-800">¿CÓMO ADOPTAR?</p><h2 class="mt-2 text-3xl font-extrabold text-blue-950">Conoce, solicita y acompaña</h2><p class="mt-4 text-stone-600">Revisa el perfil de la mascota, completa la solicitud correspondiente y sigue el proceso de adopción responsable.</p></div>
    </section>

    <section id="voluntariado" class="scroll-mt-24 bg-blue-950 px-4 py-16 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-4xl text-center"><p class="text-4xl" aria-hidden="true">🤝</p><h2 class="mt-3 text-3xl font-extrabold text-white">¿Quieres ayudar de otra forma?</h2><p class="mt-3 text-sky-100">Sé parte de nuestro equipo y ayuda a POL-Bolivia como voluntario.</p><a href="{{ route('postulaciones-voluntariado.create') }}" class="mt-6 inline-block rounded-full bg-emerald-600 px-6 py-3 font-extrabold text-white hover:bg-emerald-700">QUIERO SER VOLUNTARIO</a></div>
    </section>
@endsection
