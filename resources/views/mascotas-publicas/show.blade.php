@php
    $fotos = $mascota->fotos->where('tipo', 'foto');
    $videos = $mascota->fotos->where('tipo', 'video');
    $fotoPrincipal = $fotos->firstWhere('es_principal', true) ?? $fotos->first();
@endphp

@extends('layouts.public')

@section('title', $mascota->nombre . ' | POL-Bolivia')
@section('consulta_mascota', $mascota->nombre)

@section('content')
    <section class="px-4 py-12 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl">
            <a href="{{ route('mascotas.catalogo') }}" class="text-sm font-bold text-emerald-800 hover:text-emerald-600">← Volver a mascotas</a>
            <div class="mt-6 grid gap-10 lg:grid-cols-2">
                <div class="space-y-4">
                    @if ($fotoPrincipal)
                        @php
                            // La principal primero y después por orden (CatalogoMascotaController@show ya las ordena así).
                            $galeria = $fotos->sortByDesc(fn ($foto) => $foto->id === $fotoPrincipal->id)->values();
                            $total = $galeria->count();
                            $lista = $galeria->map(fn ($foto, $i) => [
                                'url' => Storage::disk('public')->url($foto->url),
                                'alt' => 'Foto '.($i + 1)." de {$total} de {$mascota->nombre}",
                            ])->all();
                        @endphp

                        {{-- Sin JavaScript se ve la primera foto; con Alpine, las miniaturas y las flechas la cambian. --}}
                        <div x-data="galeriaFotos(@js($lista))" class="space-y-3"
                             @if ($total > 1) x-on:keydown.arrow-left.prevent="anterior()" x-on:keydown.arrow-right.prevent="siguiente()" @endif>
                            <div class="relative flex h-80 items-center justify-center overflow-hidden rounded-2xl bg-stone-100 sm:h-[28rem]"
                                 @if ($total > 1) tabindex="0" role="region" aria-label="Galería de fotos de {{ $mascota->nombre }}. Usa las flechas del teclado para cambiar de foto." @endif>
                                <img src="{{ $lista[0]['url'] }}" x-bind:src="fotos[actual].url" alt="{{ $lista[0]['alt'] }}" x-bind:alt="fotos[actual].alt"
                                     class="h-full w-full object-contain" data-galeria="principal">

                                @if ($total > 1)
                                    <button type="button" x-on:click="anterior()" aria-label="Foto anterior" data-galeria="anterior"
                                            class="absolute left-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-xl font-bold text-blue-950 shadow hover:bg-white focus:outline-none focus:ring-4 focus:ring-amber-400">←</button>
                                    <button type="button" x-on:click="siguiente()" aria-label="Foto siguiente" data-galeria="siguiente"
                                            class="absolute right-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-xl font-bold text-blue-950 shadow hover:bg-white focus:outline-none focus:ring-4 focus:ring-amber-400">→</button>
                                    <p class="absolute bottom-3 right-3 rounded-full bg-blue-950/80 px-3 py-1 text-xs font-bold text-white" aria-live="polite"
                                       x-text="`${actual + 1} / ${fotos.length}`">1 / {{ $total }}</p>
                                @endif
                            </div>

                            @if ($total > 1)
                                <div class="grid grid-cols-4 gap-3">
                                    @foreach ($galeria as $i => $foto)
                                        <button type="button" x-on:click="ir({{ $i }})" aria-label="Ver foto {{ $i + 1 }} de {{ $total }}"
                                                x-bind:aria-current="actual === {{ $i }} ? 'true' : 'false'" aria-current="{{ $i === 0 ? 'true' : 'false' }}"
                                                class="overflow-hidden rounded-lg border-4 hover:opacity-100 focus:outline-none focus:ring-4 focus:ring-blue-300 {{ $i === 0 ? 'border-amber-400' : 'border-transparent opacity-80' }}"
                                                x-bind:class="{ 'border-amber-400': actual === {{ $i }}, 'border-transparent opacity-80': actual !== {{ $i }} }" data-galeria="miniatura">
                                            <img src="{{ $foto->url_miniatura }}" alt="" class="aspect-square w-full object-cover">
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="flex aspect-square items-center justify-center rounded-2xl bg-stone-200 text-stone-500">Sin fotografía disponible</div>
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
                    <x-boton-solicitar-adopcion :mascota="$mascota" class="mt-8 inline-block rounded-full bg-emerald-700 px-6 py-3 font-extrabold text-white hover:bg-emerald-800">ENVIAR SOLICITUD</x-boton-solicitar-adopcion>
                </div>
            </div>
        </div>
    </section>
@endsection
