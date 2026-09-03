<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Panel de administración
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-2xl font-bold">
                        Bienvenida, {{ auth()->user()->nombres }}
                    </h3>

                    <p class="mt-2">
                        Desde este panel administrarás usuarios, mascotas,
                        adopciones, seguimientos y voluntarios.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                        <a href="#" class="p-5 bg-blue-600 text-white rounded-lg">
                            Gestionar mascotas
                        </a>

                        <a href="#" class="p-5 bg-green-600 text-white rounded-lg">
                            Solicitudes de adopción
                        </a>

                        <a href="#" class="p-5 bg-purple-600 text-white rounded-lg">
                            Voluntarios
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>