@props([
    'name',               // nombre del campo enviado (p. ej. "tenencia" o "mascotas_actuales[vacunas]")
    'catalogo' => null,   // clave en App\Support\CuestionarioAdopcion::OPCIONES
    'opciones' => null,   // o directamente un arreglo clave => etiqueta (p. ej. del cuestionario de voluntariado)
    'model' => null,      // variable de Alpine.js que se actualiza al elegir (para preguntas condicionales)
    'multiple' => false,  // checkboxes en lugar de radios
    'columna' => false,   // opciones una debajo de otra (textos largos)
    'tema' => null,       // solo visual: 'pol' usa la paleta azul/ámbar de POL-Bolivia; sin valor, el aspecto original
])

@php
    $opciones = $opciones ?? \App\Support\CuestionarioAdopcion::OPCIONES[$catalogo];
    $colores = $tema === 'pol'
        ? ['etiqueta' => 'hover:border-blue-400 has-[:checked]:border-blue-800 has-[:checked]:bg-blue-50', 'control' => 'text-blue-800 focus:ring-blue-700']
        : ['etiqueta' => 'hover:border-indigo-400 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50', 'control' => 'text-indigo-600 focus:ring-indigo-500'];
    $oldKey = str_replace(['[', ']'], ['.', ''], $name);
    $anterior = old($oldKey);
    $inputName = $multiple ? $name . '[]' : $name;
    $id = str_replace(['[', ']'], ['-', ''], $name);
@endphp

<div {{ $attributes->merge(['class' => $columna ? 'mt-2 grid gap-2' : 'mt-2 flex flex-wrap gap-2']) }} role="{{ $multiple ? 'group' : 'radiogroup' }}">
    @foreach ($opciones as $clave => $etiqueta)
        @php $marcado = $multiple ? in_array($clave, (array) $anterior, true) : $anterior === $clave; @endphp
        <label for="{{ $id }}-{{ $clave }}" class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-800 {{ $colores['etiqueta'] }} has-[:checked]:font-semibold">
            <input
                type="{{ $multiple ? 'checkbox' : 'radio' }}"
                id="{{ $id }}-{{ $clave }}"
                name="{{ $inputName }}"
                value="{{ $clave }}"
                class="{{ $multiple ? 'rounded' : '' }} border-gray-300 {{ $colores['control'] }}"
                @checked($marcado)
                @if ($model) x-model="{{ $model }}" @endif
            >
            <span>{{ $etiqueta }}</span>
        </label>
    @endforeach
</div>
