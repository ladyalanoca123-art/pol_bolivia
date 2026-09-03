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
                    <h3 class="text-xl font-semibold">
                        Bienvenida, {{ auth()->user()->nombres }}
                    </h3>

                    <p class="mt-2">
                        Has iniciado sesión correctamente.
                    </p>

                    @if (auth()->user()->hasRole('administrador'))
                        <a
                            href="{{ route('admin.dashboard') }}"
                            class="inline-block mt-5 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                        >
                            Ir al panel de administración
                        </a>
                    @else
                        <p class="mt-4">
                            Bienvenido al sistema de adopciones.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
