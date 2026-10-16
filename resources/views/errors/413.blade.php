@extends('errors.plantilla')

{{--
    Petición mayor que post_max_size (Illuminate\Http\Exceptions\PostTooLargeException, HTTP 413).
    Ocurre antes de iniciar la sesión: no hay errores de validación que mostrar en el formulario.
    "Volver" regresa a la página del formulario (Referer del mismo sitio) sin usar JavaScript.
--}}
@php
    $anterior = request()->headers->get('referer');
    $volver = $anterior && parse_url($anterior, PHP_URL_HOST) === request()->getHost() ? $anterior : url('/');
@endphp

@section('codigo', 'ERROR 413')
@section('titulo', 'Archivos demasiado grandes')
@section('mensaje', 'Las fotos seleccionadas superan el tamaño total permitido. Sube menos fotos a la vez o imágenes más livianas (máximo '.\App\Http\Requests\Admin\RegisterMascotaRequest::megasMaximos().' MB cada una).')

@section('accion')
    <a href="{{ $volver }}" class="boton">Volver</a>
@endsection
