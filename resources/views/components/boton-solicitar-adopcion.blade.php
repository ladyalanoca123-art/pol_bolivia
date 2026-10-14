@props(['mascota'])
{{--
    Botón "ENVIAR SOLICITUD" de una mascota.
    - Ciudadano con sesión: va directo al formulario (sin cambios).
    - Visitante: abre el aviso "Para adoptar necesitas una cuenta". Sin JavaScript, el enlace
      lleva al inicio de sesión y, al entrar, vuelve a la solicitud de esta mascota.
    - Otra cuenta (voluntario, administrador): abre el aviso explicativo en vez de un error 403.
      El servidor sigue impidiendo que estas cuentas envíen solicitudes.
--}}
@php
    $usuario = auth()->user();
    $datosAviso = ['nombre' => $mascota->nombre];

    if (! $usuario) {
        $datosAviso['registro'] = route('mascotas.solicitar-acceso', [$mascota, 'registro']);
        $datosAviso['inicio'] = route('mascotas.solicitar-acceso', [$mascota, 'inicio']);
    }
@endphp
@if ($usuario?->hasRole('ciudadano'))
    <a href="{{ route('solicitudes-adopcion.create', $mascota) }}" {{ $attributes }}>{{ $slot }}</a>
@elseif (! $usuario)
    <a href="{{ $datosAviso['inicio'] }}" aria-haspopup="dialog"
       x-data x-on:click.prevent="$dispatch('abrir-aviso-adopcion', {{ Js::from($datosAviso) }})" {{ $attributes }}>{{ $slot }}</a>
@else
    <button type="button" aria-haspopup="dialog"
            x-data x-on:click="$dispatch('abrir-aviso-adopcion', {{ Js::from($datosAviso) }})" {{ $attributes }}>{{ $slot }}</button>
@endif
