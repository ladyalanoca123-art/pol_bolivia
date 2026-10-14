@extends('errors.plantilla')

{{-- Mensaje genérico: nunca se muestran detalles técnicos del error. --}}
@section('codigo', 'ERROR 500')
@section('titulo', 'Algo salió mal')
@section('mensaje', 'Tuvimos un problema inesperado. Ya estamos trabajando para solucionarlo. Por favor, inténtalo de nuevo en unos minutos.')

@section('accion')
    <a href="{{ url('/') }}" class="boton">Ir al inicio</a>
@endsection
