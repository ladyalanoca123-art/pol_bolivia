@props(['telefono', 'mensaje', 'datos' => [], 'aviso' => \App\Support\ContactoTelefonico::SIN_TELEFONO])
{{--
    «Escribir por WhatsApp» (wa.me con el mensaje de config('pol.whatsapp.mensajes.<mensaje>')).
    Sin un teléfono válido no hay enlace: se muestra el aviso. Quien lo use debe autorizarlo antes (política).
--}}
@php
    $whatsapp = \App\Support\ContactoTelefonico::whatsapp($telefono, $mensaje, $datos);
@endphp
@if ($whatsapp)
    <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
       class="inline-flex min-h-10 items-center gap-2 rounded-full bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-600 focus-visible:ring-offset-2">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-3.9-4.7-4.1-.1-.2-1.1-1.5-1.1-2.8s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6-.3.4c-.1.1-.3.3-.1.6.2.3.7 1.2 1.6 2 1.1 1 2 1.3 2.3 1.4.3.1.4.1.6-.1l.8-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.6-.2 1.2z"/></svg>
        Escribir por WhatsApp<span class="sr-only"> (se abre en una pestaña nueva)</span>
    </a>
@else
    <p class="text-sm text-gray-600">{{ $aviso }}</p>
@endif
