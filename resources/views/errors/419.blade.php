@extends('errors.plantilla')

@section('codigo', 'ERROR 419')
@section('titulo', 'Sesión expirada')
@section('mensaje', 'Tu sesión expiró por seguridad. Vuelve a intentarlo.')

@section('accion')
    <a href="{{ route('login') }}" class="boton">Volver a iniciar sesión</a>
@endsection
