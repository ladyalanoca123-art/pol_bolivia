{{--
    Aviso que se abre al pulsar "ENVIAR SOLICITUD" sin una cuenta de ciudadano.
    Lo abre el evento "abrir-aviso-adopcion" (componente x-boton-solicitar-adopcion).
    <dialog> nativo: mantiene el foco dentro del aviso, inactiva la página de fondo y se cierra con Esc.
    Imagen ilustrativa (no es una adopción real).
--}}
@php
    $usuario = auth()->user();
    $boton = 'inline-flex h-12 w-full items-center justify-center rounded-full px-6 text-base font-extrabold focus:outline-none focus:ring-4';
    $whatsapp = config('pol.redes.whatsapp');
@endphp
<div x-data="{ nombre: '', registro: '', inicio: '' }"
     x-on:abrir-aviso-adopcion.window="nombre = $event.detail.nombre; registro = $event.detail.registro ?? ''; inicio = $event.detail.inicio ?? ''; $refs.dialogo.showModal()">
    <dialog x-ref="dialogo" aria-labelledby="aviso-adopcion-titulo" aria-describedby="aviso-adopcion-texto"
            class="w-[calc(100%-2rem)] max-w-4xl overflow-hidden rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-blue-950/70 backdrop:backdrop-blur-sm"
            x-on:click="if ($event.target === $el) $el.close()">
        <div class="grid md:grid-cols-[5fr_6fr]">
            {{-- Foto: franja superior en celular, columna izquierda en pantallas medianas y grandes --}}
            <div class="relative h-40 bg-blue-950 sm:h-52 md:h-auto md:min-h-[30rem]">
                <img src="{{ asset('images/pol-bolivia/modal-adopcion.jpg') }}" alt="" width="1086" height="1448"
                     class="absolute inset-0 h-full w-full object-cover object-[center_25%]" aria-hidden="true">
                <div class="absolute inset-0 bg-gradient-to-t from-blue-950/40 to-transparent md:bg-gradient-to-r md:from-transparent md:to-blue-950/10" aria-hidden="true"></div>
            </div>

            <div class="relative flex flex-col justify-center px-6 pb-7 pt-6 sm:px-10 sm:py-9">
                <button type="button" x-on:click="$refs.dialogo.close()" aria-label="Cerrar aviso"
                        class="absolute right-3 top-3 flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-blue-950 focus:outline-none focus:ring-4 focus:ring-blue-700/15">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>

                <img src="{{ asset('images/pol-bolivia/logos/POL_02_horizontal_web.png') }}" alt="POL-Bolivia, Hacedores de Sueños" class="h-12 w-auto self-start">

                @if (! $usuario)
                    <p class="mt-6 text-xs font-bold tracking-[0.14em] text-amber-700">ADOPCIÓN RESPONSABLE</p>
                    <h2 id="aviso-adopcion-titulo" class="mt-1.5 text-2xl font-extrabold leading-tight text-blue-950 sm:text-3xl">
                        Para adoptar a <span x-text="nombre"></span> necesitas una cuenta
                    </h2>
                    <p id="aviso-adopcion-texto" class="mt-3 text-slate-600">Crea tu cuenta en un minuto o inicia sesión. Después volverás directamente a la solicitud de esta mascota.</p>

                    <div class="mt-7 space-y-3">
                        <a x-bind:href="registro" autofocus class="{{ $boton }} bg-amber-400 text-blue-950 shadow-sm hover:bg-amber-500 focus:ring-amber-400/40">Crear cuenta</a>
                        <a x-bind:href="inicio" class="{{ $boton }} border-2 border-blue-950 text-blue-950 hover:bg-blue-950 hover:text-white focus:ring-blue-700/15">Ya tengo cuenta · Iniciar sesión</a>
                    </div>
                @else
                    <p class="mt-6 text-xs font-bold tracking-[0.14em] text-amber-700">SOLICITUDES DE ADOPCIÓN</p>
                    <h2 id="aviso-adopcion-titulo" class="mt-1.5 text-2xl font-extrabold leading-tight text-blue-950 sm:text-3xl">
                        Las solicitudes se envían desde una cuenta de ciudadano
                    </h2>
                    <p id="aviso-adopcion-texto" class="mt-3 text-slate-600">
                        @if ($usuario->hasRole('voluntario'))
                            Tu cuenta es de voluntario, por eso no puedes enviar una solicitud desde aquí. Si quieres adoptar a <span x-text="nombre"></span>, escríbenos y te orientaremos.
                        @else
                            Tu cuenta no es de ciudadano, por eso no puedes enviar una solicitud desde aquí. Si quieres adoptar a <span x-text="nombre"></span>, escríbenos y te orientaremos.
                        @endif
                    </p>

                    <div class="mt-7">
                        <button type="button" x-on:click="$refs.dialogo.close()" autofocus class="{{ $boton }} bg-amber-400 text-blue-950 shadow-sm hover:bg-amber-500 focus:ring-amber-400/40">Entendido</button>
                    </div>
                @endif

                {{-- Contacto y redes oficiales (se abren en una pestaña nueva) --}}
                <div class="mt-7 border-t border-slate-200 pt-5">
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 rounded-lg text-sm font-semibold text-blue-950 hover:text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-700/15">
                        <svg class="h-5 w-5 shrink-0 text-[#25D366]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 2a8 8 0 1 1-4.1 14.9l-.3-.2-2.9.8.8-2.8-.2-.3A8 8 0 0 1 12 4Zm-3.2 3.9c-.2 0-.6.1-.9.4-.3.3-1.1 1.1-1.1 2.7s1.2 3.2 1.3 3.4c.2.2 2.3 3.6 5.7 4.9 2.8 1.1 3.4.9 4 .8.6-.1 2-.8 2.2-1.6.3-.8.3-1.5.2-1.6l-.6-.4-2.2-1.1c-.3-.1-.5-.2-.7.2l-.9 1.1c-.2.2-.4.2-.7.1-.3-.2-1.4-.5-2.6-1.6-1-.9-1.6-1.9-1.8-2.2-.2-.3 0-.5.1-.6l.5-.6.3-.5v-.5l-1-2.4c-.3-.6-.5-.5-.7-.5h-.6Z"/></svg>
                        ¿Tienes dudas? Escríbenos por WhatsApp
                        <span class="sr-only">(se abre en una pestaña nueva)</span>
                    </a>
                    <x-redes-sociales class="mt-4" />
                </div>
            </div>
        </div>
    </dialog>
</div>
