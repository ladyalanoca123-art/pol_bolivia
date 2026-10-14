@extends('errors.plantilla')

@section('codigo', 'ERROR 429')
@section('titulo', 'Demasiadas solicitudes')
{{-- Texto genérico: esta página la usan todos los límites (por minuto y por hora), así que no promete un tiempo fijo. --}}
@section('mensaje', 'Has realizado demasiadas solicitudes. Espera un momento antes de volver a intentarlo.')

@section('accion')
    <a href="{{ url('/') }}" class="boton">Ir al inicio</a>
@endsection
