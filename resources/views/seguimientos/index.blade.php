<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Mis seguimientos</h2></x-slot>
    <div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8"><div class="p-6 bg-white shadow-sm sm:rounded-lg">
        @forelse ($seguimientos as $seguimiento)
            <div class="py-3 border-b last:border-b-0"><a class="text-indigo-600 hover:text-indigo-900" href="{{ route('seguimientos.show', $seguimiento) }}">{{ $seguimiento->solicitud->mascota->nombre }}</a><span class="ml-2 text-gray-600">— {{ ucfirst(str_replace('_', ' ', $seguimiento->estado)) }}</span></div>
        @empty
            <p class="text-gray-500">No tienes seguimientos asignados.</p>
        @endforelse
        <div class="pt-4">{{ $seguimientos->links() }}</div>
    </div></div></div>
</x-app-layout>
