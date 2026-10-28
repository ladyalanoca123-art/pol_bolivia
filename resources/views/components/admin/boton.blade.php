@props(['variante' => 'primario', 'href' => null, 'tamano' => 'normal'])
{{--
    Botón del panel. variante: primario (azul), destacado (ámbar), secundario (borde), exito (verde) o peligro (rojo).
    Con href se dibuja como enlace; sin href, como <button> (type="submit" por defecto).
--}}
@php
    $estilos = [
        'primario' => 'bg-blue-950 text-white hover:bg-blue-900',
        'destacado' => 'bg-amber-400 text-blue-950 hover:bg-amber-300',
        'secundario' => 'border border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50',
        'exito' => 'bg-emerald-700 text-white hover:bg-emerald-800',
        'peligro' => 'bg-red-700 text-white hover:bg-red-800',
        'peligro-suave' => 'border border-red-300 bg-white text-red-700 hover:bg-red-50',
    ][$variante] ?? 'bg-blue-950 text-white hover:bg-blue-900';
    $medidas = $tamano === 'chico' ? 'min-h-8 px-3 py-1 text-xs' : 'min-h-10 px-5 py-2 text-sm';
    $clases = "inline-flex items-center justify-center gap-1.5 rounded-full font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 {$medidas} {$estilos}";
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $clases]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $clases]) }}>{{ $slot }}</button>
@endif
