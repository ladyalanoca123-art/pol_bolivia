@props(['mascota'])
{{--
    Foto principal de una mascota (miniatura). Si no tiene foto, o el archivo no carga, se ve un fondo gris
    claro con una huella. Las fotos deben venir cargadas en $mascota->fotos.
    Tamaño y bordes por clase: <x-foto-mascota :mascota="$m" class="h-14 w-14 rounded-xl" />
--}}
@php
    $fotos = $mascota?->fotos ?? collect();
    $foto = $fotos->firstWhere('es_principal', true) ?? $fotos->first();
@endphp
<span {{ $attributes->merge(['class' => 'relative flex shrink-0 items-center justify-center overflow-hidden bg-slate-100 text-slate-300']) }}>
    <svg class="h-1/3 w-1/3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5.5" cy="9" r="2.2"/><circle cx="9.5" cy="5" r="2.2"/><circle cx="14.5" cy="5" r="2.2"/><circle cx="18.5" cy="9" r="2.2"/><path d="M12 10.5c-3.2 0-6 3.6-6 6.3 0 1.9 1.5 2.7 3 2.7 1.2 0 2-.6 3-.6s1.8.6 3 .6c1.5 0 3-.8 3-2.7 0-2.7-2.8-6.3-6-6.3z"/></svg>
    {{-- alt vacío: el nombre ya está escrito junto a la foto; si el archivo falla, app.js quita la imagen (data-respaldo) y queda la huella. --}}
    @if ($foto)<img src="{{ $foto->url_miniatura }}" alt="" loading="lazy" data-respaldo class="absolute inset-0 h-full w-full object-cover">@endif
</span>
