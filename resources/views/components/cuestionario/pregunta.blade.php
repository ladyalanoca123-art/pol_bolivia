@props([
    'num' => null,        // número visible de la pregunta (1 a 26)
    'label',
    'campo' => null,      // campo para mostrar su error de validación
    'ayuda' => null,
    'nota' => null,
    'condicional' => false,
    'tema' => null,       // solo visual: 'pol' usa la paleta azul/ámbar de POL-Bolivia; sin valor, el aspecto original
])

@php
    $bordeCondicional = $tema === 'pol' ? 'border-amber-300' : 'border-indigo-300';
    $colorNumero = $tema === 'pol' ? 'text-blue-800' : 'text-indigo-700';
@endphp

{{-- Contenedor de una pregunta del cuestionario. data-num lo usa el indicador de progreso. --}}
<div {{ $attributes->merge(['class' => 'py-4 ' . ($condicional ? 'rounded-lg bg-gray-50 px-4 border-l-4 ' . $bordeCondicional : 'border-t border-gray-100 first:border-t-0')]) }} @if ($num) data-num="{{ $num }}" @endif>
    <p class="text-sm font-semibold text-gray-900">
        @if ($num)<span class="{{ $colorNumero }}">{{ $num }}.</span>@endif
        {{ $label }}
    </p>
    @if ($ayuda)<p class="mt-1 text-xs text-gray-500">{{ $ayuda }}</p>@endif
    {{ $slot }}
    @if ($nota)<p class="mt-2 border-l-2 border-amber-300 pl-2 text-xs text-gray-600">{{ $nota }}</p>@endif
    @if ($campo)<x-input-error :messages="$errors->get($campo)" class="mt-2" />@endif
</div>
