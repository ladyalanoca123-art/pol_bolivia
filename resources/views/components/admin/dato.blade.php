@props(['etiqueta'])
{{-- Un dato de una ficha de detalle (dentro de un <dl>). --}}
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <dt class="dato-etiqueta text-[13px] font-medium text-slate-500">{{ $etiqueta }}</dt>
    <dd class="mt-1 break-words text-[15px] text-slate-900">{{ $slot }}</dd>
</div>
