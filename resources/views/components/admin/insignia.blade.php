@props(['tono' => 'gris', 'punto' => true])
{{-- Etiqueta de estado. tono: verde, ambar, azul, indigo, rojo, violeta o gris. --}}
@php
    [$fondo, $color] = [
        'verde' => ['bg-emerald-50 text-emerald-800 ring-emerald-200', 'bg-emerald-500'],
        'ambar' => ['bg-amber-50 text-amber-800 ring-amber-200', 'bg-amber-500'],
        'azul' => ['bg-sky-50 text-sky-800 ring-sky-200', 'bg-sky-500'],
        'indigo' => ['bg-indigo-50 text-indigo-800 ring-indigo-200', 'bg-indigo-500'],
        'rojo' => ['bg-red-50 text-red-700 ring-red-200', 'bg-red-500'],
        'violeta' => ['bg-violet-50 text-violet-800 ring-violet-200', 'bg-violet-500'],
        'gris' => ['bg-slate-100 text-slate-700 ring-slate-200', 'bg-slate-400'],
    ][$tono] ?? ['bg-slate-100 text-slate-700 ring-slate-200', 'bg-slate-400'];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {$fondo}"]) }}>
    @if ($punto)<span aria-hidden="true" class="h-1.5 w-1.5 rounded-full {{ $color }}"></span>@endif{{ $slot }}
</span>
