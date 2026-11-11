@extends('layouts.public')

@section('title', 'Mascotas disponibles | POL-Bolivia')

@section('content')
    <section class="px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <div class="mx-auto max-w-7xl">
            @php($categoria = match ($especie) { 'perro' => ['tipo' => 'perro', 'icono' => 'icon-perros.svg', 'titulo' => 'Perritos en adopción', 'descripcion' => 'Encuentra a tu compañero fiel en Cochabamba.'], 'gato' => ['tipo' => 'gato', 'icono' => 'icon-gatos.svg', 'titulo' => 'Gatitos en adopción', 'descripcion' => 'Pequeñas patitas, grandes historias. ¡Conoce a tu nuevo compañero!'], default => ['tipo' => 'todos', 'icono' => 'icon-todas.svg', 'titulo' => 'Mascotas que esperan tu cariño', 'descripcion' => 'Conoce a quienes buscan un hogar en Cochabamba.'], })
            <header class="border-b border-sky-100 pb-4">
                <div class="flex items-center gap-3 sm:gap-4">
                    <span class="inline-flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700 ring-1 ring-blue-100 sm:h-[4.5rem] sm:w-[4.5rem]" aria-hidden="true">
                        <img src="{{ asset('images/pol-bolivia/icons/'.$categoria['icono']) }}" alt="" class="h-10 w-10 object-contain sm:h-11 sm:w-11">
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold tracking-wider text-emerald-700">ADOPTA</p>
                        <h1 class="mt-0.5 text-2xl font-extrabold leading-tight text-blue-950 sm:text-3xl">{{ $categoria['titulo'] }}</h1>
                        <p class="mt-0.5 text-sm leading-snug text-slate-600">{{ $categoria['descripcion'] }}</p>
                    </div>
                </div>
                <nav class="mt-3 flex gap-2" aria-label="Categorías de mascotas">
                    <a href="{{ route('mascotas.catalogo') }}" @class(['inline-flex h-10 min-w-0 flex-1 items-center justify-center gap-1 rounded-full px-2 text-xs font-bold transition sm:max-w-32 sm:flex-none sm:px-4 sm:text-sm', 'bg-blue-800 text-white hover:bg-blue-900' => $especie === null, 'border border-blue-200 bg-white text-blue-900 hover:bg-sky-50' => $especie !== null])><svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><ellipse cx="5.5" cy="10" rx="2.2" ry="2.8"/><ellipse cx="9.5" cy="5.5" rx="2.2" ry="2.8"/><ellipse cx="14.5" cy="5.5" rx="2.2" ry="2.8"/><ellipse cx="18.5" cy="10" rx="2.2" ry="2.8"/><path d="M12 11.5c-3 0-6 3.6-6 6.3 0 1.7 1.3 2.7 3 2.7 1.2 0 2-.6 3-.6s1.8.6 3 .6c1.7 0 3-1 3-2.7 0-2.7-3-6.3-6-6.3Z"/></svg> Todos</a>
                    <a href="{{ route('mascotas.catalogo', ['especie' => 'perro']) }}" @class(['inline-flex h-10 min-w-0 flex-1 items-center justify-center gap-1 rounded-full px-2 text-xs font-bold transition sm:max-w-32 sm:flex-none sm:px-4 sm:text-sm', 'bg-blue-800 text-white hover:bg-blue-900' => $especie === 'perro', 'border border-blue-200 bg-white text-blue-900 hover:bg-sky-50' => $especie !== 'perro'])>🐶 Perros</a>
                    <a href="{{ route('mascotas.catalogo', ['especie' => 'gato']) }}" @class(['inline-flex h-10 min-w-0 flex-1 items-center justify-center gap-1 rounded-full px-2 text-xs font-bold transition sm:max-w-32 sm:flex-none sm:px-4 sm:text-sm', 'bg-blue-800 text-white hover:bg-blue-900' => $especie === 'gato', 'border border-blue-200 bg-white text-blue-900 hover:bg-sky-50' => $especie !== 'gato'])>🐱 Gatos</a>
                </nav>
            </header>

            @if ($mascotas->isNotEmpty())
                <div class="mt-5 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
                    @foreach ($mascotas as $mascota)
                        <x-mascota-publica-card :mascota="$mascota" />
                    @endforeach
                </div>
                <div class="mt-10">{{ $mascotas->links() }}</div>
            @else
                <p class="mt-8 rounded-xl bg-white p-6 text-stone-600 ring-1 ring-stone-200">Actualmente no hay mascotas disponibles para adopción.</p>
            @endif
        </div>
    </section>
@endsection
