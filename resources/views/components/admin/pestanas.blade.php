@props(['opciones', 'etiqueta', 'actual' => 'page'])
{{--
    Barra de botones de filtro rápido del panel (Reportes, Solicitudes, Usuarios, Auditoría): botones cuadrados que
    llenan el ancho, con un ícono de línea fina. El elegido va en azul oscuro con el ícono en ámbar.
    opciones: lista de [texto, url, activa (bool), icono (atributo d de un trazo de 24 × 24)].
    actual: valor de aria-current del elegido («page», o «true» en los períodos de Reportes).
    En el slot puede ir un botón más que ocupe una celda (p. ej. «Personalizado» en Reportes).
--}}
@php
    $total = count($opciones) + ($slot->isEmpty() ? 0 : 1);
    $columnas = [2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5', 6 => 'lg:grid-cols-6'][$total] ?? 'lg:grid-cols-6';
@endphp
<nav aria-label="{{ $etiqueta }}" {{ $attributes->merge(['class' => "grid grid-cols-2 gap-2 sm:grid-cols-3 {$columnas}"]) }}>
    @foreach ($opciones as [$texto, $url, $activa, $icono])
        <a href="{{ $url }}" @if ($activa) aria-current="{{ $actual }}" @endif
           class="admin-pestana {{ $activa ? 'admin-pestana-activa' : '' }}">
            <svg class="h-[18px] w-[18px] shrink-0 {{ $activa ? 'text-amber-300' : 'text-blue-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icono }}" /></svg>
            <span class="truncate">{{ $texto }}</span>
        </a>
    @endforeach
    {{ $slot }}
</nav>
