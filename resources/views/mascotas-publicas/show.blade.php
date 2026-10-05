@php($fotos = $mascota->fotos->where('tipo', 'foto'))
@php($videos = $mascota->fotos->where('tipo', 'video'))
@php($fotoPrincipal = $fotos->firstWhere('es_principal', true) ?? $fotos->first())

@extends('layouts.public')

@section('title', $mascota->nombre . ' | POL-Bolivia')

@section('content')
    <section class="px-4 py-12 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl">
            <a href="{{ route('mascotas.catalogo') }}" class="text-sm font-bold text-emerald-800 hover:text-emerald-600">← Volver a mascotas</a>
            <div class="mt-6 grid gap-10 lg:grid-cols-2">
                <div class="space-y-4">
                    @if ($fotoPrincipal)
                        <img src="{{ Storage::disk('public')->url($fotoPrincipal->url) }}" alt="{{ $mascota->nombre }}" class="aspect-square w-full rounded-2xl object-cover">
                    @else
                        <div class="flex aspect-square items-center justify-center rounded-2xl bg-stone-200 text-stone-500">Sin fotografía disponible</div>
                    @endif

                    @if ($fotos->count() > 1)
                        <div class="grid grid-cols-3 gap-3">
                            @foreach ($fotos->reject(fn ($foto) => $foto->id === $fotoPrincipal?->id) as $foto)
                                <img src="{{ Storage::disk('public')->url($foto->url) }}" alt="Foto de {{ $mascota->nombre }}" class="aspect-square rounded-lg object-cover">
                            @endforeach
                        </div>
                    @endif

                    @foreach ($videos as $video)
                        <video controls class="w-full rounded-xl"><source src="{{ Storage::disk('public')->url($video->url) }}"></video>
                    @endforeach
                </div>

                <div>
                    <p class="text-sm font-bold tracking-wider text-emerald-700">MASCOTA DISPONIBLE</p>
                    <h1 class="mt-2 text-4xl font-extrabold">{{ $mascota->nombre }}</h1>
                    <dl class="mt-7 grid grid-cols-2 gap-x-6 gap-y-5 border-y border-stone-200 py-6 text-sm">
                        <div><dt class="font-semibold text-stone-500">Especie</dt><dd class="mt-1">{{ ucfirst($mascota->especie) }}</dd></div>
                        <div><dt class="font-semibold text-stone-500">Sexo</dt><dd class="mt-1">{{ ucfirst($mascota->sexo) }}</dd></div>
                        <div><dt class="font-semibold text-stone-500">Edad</dt><dd class="mt-1">{{ $mascota->edad_formateada }}</dd></div>
                        <div><dt class="font-semibold text-stone-500">Tamaño</dt><dd class="mt-1">{{ ucfirst($mascota->tamano) }}</dd></div>
                        <div><dt class="font-semibold text-stone-500">Raza</dt><dd class="mt-1">{{ $mascota->raza }}</dd></div>
                        <div><dt class="font-semibold text-stone-500">Color</dt><dd class="mt-1">{{ $mascota->color }}</dd></div>
                    </dl>
                    <h2 class="mt-7 text-lg font-bold">Sobre {{ $mascota->nombre }}</h2>
                    <p class="mt-2 whitespace-pre-line text-stone-700">{{ $mascota->descripcion }}</p>
                    <a href="{{ route('solicitudes-adopcion.create', $mascota) }}" class="mt-8 inline-block rounded-full bg-emerald-700 px-6 py-3 font-extrabold text-white hover:bg-emerald-800">ENVIAR SOLICITUD</a>
                </div>
            </div>
        </div>
    </section>
@endsection
