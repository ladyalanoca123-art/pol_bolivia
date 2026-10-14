@props(['nivel' => null, 'cantidad' => null])

{{-- Etiqueta de revisión de una solicitud de adopción (solo para el administrador). --}}
@php
    $estilos = [
        'rojo' => ['🔴', 'Condición incompatible', 'bg-red-100 text-red-800 ring-red-300'],
        'amarillo' => ['🟡', 'Requiere revisión', 'bg-amber-100 text-amber-800 ring-amber-300'],
        'verde' => ['🟢', 'Sin observaciones', 'bg-green-100 text-green-800 ring-green-300'],
    ];
    [$icono, $texto, $clases] = $estilos[$nivel] ?? ['', 'Sin evaluar', 'bg-gray-100 text-gray-600 ring-gray-300'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {$clases}"]) }}>
    @if ($icono)<span aria-hidden="true">{{ $icono }}</span>@endif
    {{ $texto }}@if ($cantidad) ({{ $cantidad }})@endif
</span>
