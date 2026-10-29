@props(['etiqueta'])
{{-- Un dato de una ficha de detalle (dentro de un <dl>). --}}
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <dt class="dato-etiqueta text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $etiqueta }}</dt>
    <dd class="mt-1 break-words text-sm text-slate-900">{{ $slot }}</dd>
</div>
