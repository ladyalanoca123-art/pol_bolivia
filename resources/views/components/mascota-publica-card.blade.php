@props(['mascota', 'promocionar' => false])
@php
    $fotoPrincipal = $mascota->fotos->firstWhere('es_principal', true) ?? $mascota->fotos->first();
    // Solo en la fila «Te esperan hace más tiempo»: días en el sistema y enlace para compartir por WhatsApp.
    if ($promocionar) {
        $diasEsperando = (int) $mascota->created_at->timezone('America/La_Paz')->startOfDay()
            ->diffInDays(now('America/La_Paz')->startOfDay());
        $emoji = $mascota->especie === 'gato' ? '🐱' : '🐶';
        $textoCompartir = "{$emoji} {$mascota->nombre} busca un hogar hace {$diasEsperando} días. "
            .'¿Lo adoptas o lo compartes? Conócelo en POL-Bolivia: '.route('mascotas.public.show', $mascota);
    }
@endphp
{{-- Tarjeta con vuelta: al frente la foto (ocupa todo el alto libre, sin huecos) y al reverso los datos rápidos. --}}
<article x-data="{ reverso: false }" class="group [perspective:1200px]">
    <div class="relative min-h-[25rem] transition-transform duration-500 [transform-style:preserve-3d] sm:min-h-[24rem]" :class="reverso && '[transform:rotateY(180deg)]'">
        <section class="absolute inset-0 flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 [backface-visibility:hidden]">
            <div class="relative min-h-0 flex-1 bg-slate-100">
                @if ($fotoPrincipal)<img src="{{ $fotoPrincipal->url_miniatura }}" alt="{{ $mascota->nombre }}" class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105">@else<div class="flex h-full items-center justify-center text-sm text-slate-500">Sin fotografía disponible</div>@endif
                @if ($promocionar)
                    <span class="absolute start-3 top-3 rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900 shadow-sm ring-1 ring-amber-300">Espera hace {{ $diasEsperando }} días</span>
                @endif
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-4 pb-3 pt-12 sm:px-5 sm:pb-4">
                    <h3 class="text-xl font-bold text-white sm:text-2xl">{{ $mascota->nombre }}</h3>
                    <p class="text-sm text-white/85">{{ ucfirst($mascota->edad_formateada) }} · {{ ucfirst($mascota->sexo) }}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 p-3 sm:p-4">
                <a href="{{ route('mascotas.public.show', $mascota) }}" class="inline-flex min-w-0 items-center justify-center whitespace-nowrap rounded-lg border border-blue-800 px-1.5 py-2 text-center text-[11px] font-bold leading-none text-blue-900 hover:bg-sky-50 sm:px-3 sm:text-sm">VER PERFIL</a>
                <button type="button" @click="reverso = true" class="inline-flex min-w-0 items-center justify-center gap-1 whitespace-nowrap rounded-lg bg-blue-800 px-1 py-2 text-[10px] font-bold leading-none text-white hover:bg-blue-900 sm:px-2.5 sm:text-xs">↻ DATOS<span class="hidden sm:inline"> RÁPIDOS</span></button>
                @if ($promocionar)
                    <a href="https://wa.me/?text={{ rawurlencode($textoCompartir) }}" target="_blank" rel="noopener noreferrer"
                       class="col-span-2 inline-flex items-center justify-center gap-1.5 rounded-lg bg-green-700 px-2 py-2 text-xs font-bold text-white hover:bg-green-800 sm:text-sm">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.2c0-.1-.2-.2-.5-.3Z"/></svg>
                        COMPARTIR<span class="sr-only"> a {{ $mascota->nombre }} por WhatsApp</span>
                    </a>
                @endif
            </div>
        </section>
        <section class="absolute inset-0 flex flex-col rounded-2xl bg-blue-950 p-4 text-white shadow-sm ring-1 ring-blue-900 [backface-visibility:hidden] [transform:rotateY(180deg)] sm:p-6">
            <h3 class="text-base font-bold sm:text-lg">DATOS RÁPIDOS SOBRE {{ Str::upper($mascota->nombre) }}</h3>
            <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-3 gap-y-2 text-sm"><dt class="font-semibold text-sky-200">Especie:</dt><dd>{{ ucfirst($mascota->especie) }}</dd><dt class="font-semibold text-sky-200">Sexo:</dt><dd>{{ ucfirst($mascota->sexo) }}</dd><dt class="font-semibold text-sky-200">Edad:</dt><dd>{{ $mascota->edad_formateada }}</dd><dt class="font-semibold text-sky-200">Tamaño:</dt><dd>{{ ucfirst($mascota->tamano) }}</dd><dt class="font-semibold text-sky-200">Raza:</dt><dd>{{ $mascota->raza }}</dd><dt class="font-semibold text-sky-200">Color:</dt><dd>{{ $mascota->color }}</dd></dl>
            <div class="mt-auto space-y-2 pt-4"><div class="grid grid-cols-2 gap-2"><a href="{{ route('mascotas.public.show', $mascota) }}" class="inline-flex items-center justify-center whitespace-nowrap rounded-lg border border-white/80 px-1.5 py-2 text-center text-[11px] font-bold leading-none hover:bg-white/10 sm:px-3 sm:text-sm">VER PERFIL</a><button type="button" @click="reverso = false" class="whitespace-nowrap rounded-lg border border-white/80 px-1.5 py-2 text-[11px] font-bold leading-none hover:bg-white/10 sm:px-3 sm:text-sm">↩ VOLVER</button></div><x-boton-solicitar-adopcion :mascota="$mascota" class="block w-full rounded-lg bg-white px-4 py-3 text-center text-sm font-extrabold text-blue-950 hover:bg-sky-50">ENVIAR SOLICITUD</x-boton-solicitar-adopcion></div>
        </section>
    </div>
</article>
