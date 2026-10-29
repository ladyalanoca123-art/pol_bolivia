@props(['tono' => 'claro', 'whatsapp' => false])
{{--
    Íconos de las redes oficiales de POL-Bolivia (config('pol.redes')); se abren en una pestaña nueva.
    tono: claro (sobre fondo blanco) u oscuro (sobre el azul del pie de página). whatsapp: agrega el de WhatsApp.
--}}
@php
    $iconos = [
        'facebook' => ['Facebook', 'M13.5 21.9v-7.4h2.5l.4-2.9h-2.9V9.8c0-.8.3-1.4 1.5-1.4h1.5V5.8c-.3 0-1.2-.1-2.2-.1-2.2 0-3.7 1.3-3.7 3.8v2.1H8.1v2.9h2.5v7.4a10 10 0 1 1 2.9 0Z'],
        'instagram' => ['Instagram', 'M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2Zm0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm5.3-3.3a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4Z'],
        'tiktok' => ['TikTok', 'M16.6 2c.4 2.3 1.8 3.8 4 4v3.1c-1.5 0-2.9-.4-4-1.2v6.6a6.2 6.2 0 1 1-6.2-6.2c.3 0 .7 0 1 .1v3.2a3.1 3.1 0 1 0 2.1 2.9V2h3.1Z'],
    ];
    if ($whatsapp) {
        $iconos['whatsapp'] = ['WhatsApp', 'M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-3.9-4.7-4.1-.1-.2-1.1-1.5-1.1-2.8s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6-.3.4c-.1.1-.3.3-.1.6.2.3.7 1.2 1.6 2 1.1 1 2 1.3 2.3 1.4.3.1.4.1.6-.1l.8-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.6-.2 1.2z'];
    }
    $clase = $tono === 'oscuro'
        ? 'bg-blue-700/70 text-white ring-1 ring-white/10 hover:bg-amber-400 hover:text-blue-950 focus:ring-amber-400/40'
        : 'bg-slate-100 text-blue-950 hover:bg-blue-950 hover:text-white focus:ring-blue-700/15';
@endphp
<ul {{ $attributes->merge(['class' => 'flex items-center gap-2']) }} aria-label="Redes sociales de POL-Bolivia">
    @foreach ($iconos as $clave => [$nombre, $icono])
        @continue(blank(config("pol.redes.$clave")))
        <li>
            <a href="{{ config("pol.redes.$clave") }}" target="_blank" rel="noopener noreferrer"
               aria-label="{{ $nombre }} de POL-Bolivia (se abre en una pestaña nueva)"
               class="flex h-10 w-10 items-center justify-center rounded-full transition focus:outline-none focus:ring-4 {{ $clase }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" d="{{ $icono }}"/></svg>
            </a>
        </li>
    @endforeach
</ul>
