<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Panel principal
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-2xl font-bold">
                        Bienvenida, {{ auth()->user()->nombres }}
                    </h3>

                    <p class="mt-2">Resumen rápido del estado actual del sistema.</p>

                    <div class="grid grid-cols-1 gap-4 mt-6 md:grid-cols-2 xl:grid-cols-4">
                        <a href="{{ route('admin.mascotas.index') }}" class="p-5 text-white bg-blue-600 rounded-lg hover:bg-blue-700"><p class="text-sm">Total de mascotas registradas</p><p class="mt-2 text-3xl font-bold">{{ $indicadores['mascotas'] }}</p></a>
                        <a href="{{ route('admin.solicitudes-adopcion.index') }}" class="p-5 text-white bg-amber-600 rounded-lg hover:bg-amber-700"><p class="text-sm">Solicitudes de adopción pendientes</p><p class="mt-2 text-3xl font-bold">{{ $indicadores['solicitudesPendientes'] }}</p></a>
                        <a href="{{ route('admin.solicitudes-adopcion.index') }}" class="p-5 text-white bg-green-600 rounded-lg hover:bg-green-700"><p class="text-sm">Adopciones aprobadas</p><p class="mt-2 text-3xl font-bold">{{ $indicadores['adopcionesAprobadas'] }}</p></a>
                        <a href="{{ route('admin.usuarios.index') }}" class="p-5 text-white bg-indigo-600 rounded-lg hover:bg-indigo-700"><p class="text-sm">Usuarios registrados</p><p class="mt-2 text-3xl font-bold">{{ $indicadores['usuarios'] }}</p></a>
                        <a href="{{ route('admin.voluntarios.index') }}" class="p-5 text-white bg-purple-600 rounded-lg hover:bg-purple-700"><p class="text-sm">Voluntarios activos</p><p class="mt-2 text-3xl font-bold">{{ $indicadores['voluntariosActivos'] }}</p></a>
                        <a href="{{ route('admin.postulaciones-voluntariado.index') }}" class="p-5 text-white bg-cyan-600 rounded-lg hover:bg-cyan-700"><p class="text-sm">Postulaciones pendientes</p><p class="mt-2 text-3xl font-bold">{{ $indicadores['postulacionesPendientes'] }}</p></a>
                        <a href="{{ route('admin.seguimientos.index') }}" class="p-5 text-white bg-red-600 rounded-lg hover:bg-red-700"><p class="text-sm">Incidencias pendientes</p><p class="mt-2 text-3xl font-bold">{{ $indicadores['incidenciasPendientes'] }}</p></a>
                    </div>

                    <div class="pt-6 mt-6 border-t">
                        <a href="{{ route('admin.reportes.index') }}" class="text-indigo-600 hover:text-indigo-900">Ver Reportes y estadísticas</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
