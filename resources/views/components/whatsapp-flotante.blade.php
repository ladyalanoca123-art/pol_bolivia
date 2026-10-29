@props(['mascota' => null])
{{--
    Botón flotante de WhatsApp en las páginas públicas (layouts/public): cualquier persona escribe directo a POL-Bolivia.
    Número en config('pol.whatsapp.consultas'); si falta o no es válido, no se muestra.
    En la ficha de una mascota el mensaje ya menciona su nombre.
--}}
@php
    $numero = \App\Support\ContactoTelefonico::numeroInternacional((string) config('pol.whatsapp.consultas'));
    $mensaje = filled($mascota)
        ? \App\Support\ContactoTelefonico::mensaje('consulta_mascota', ['mascota' => $mascota])
        : \App\Support\ContactoTelefonico::mensaje('consulta', []);
@endphp
@if ($numero)
    <a href="https://wa.me/{{ $numero }}?text={{ rawurlencode($mensaje) }}" target="_blank" rel="noopener"
       class="group fixed bottom-4 right-4 z-40 inline-flex items-center gap-2 rounded-full bg-[#25D366] p-3.5 text-white shadow-lg shadow-black/15 transition hover:bg-[#1ebe5b] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#25D366]/40 sm:bottom-6 sm:right-6 sm:px-5"
       aria-label="Escribir a POL-Bolivia por WhatsApp (se abre en otra pestaña)">
        <svg class="h-7 w-7 shrink-0 sm:h-6 sm:w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-3.9-4.7-4.1-.1-.2-1.1-1.5-1.1-2.8s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6-.3.4c-.1.1-.3.3-.1.6.2.3.7 1.2 1.6 2 1.1 1 2 1.3 2.3 1.4.3.1.4.1.6-.1l.8-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.6-.2 1.2z"/></svg>
        <span class="hidden text-sm font-semibold sm:inline">¿Tienes dudas? Escríbenos</span>
    </a>
@endif
