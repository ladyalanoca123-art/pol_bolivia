<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">Auditoría</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" action="{{ route('admin.auditoria.index') }}" class="flex flex-wrap gap-3 items-end bg-white p-4 shadow-sm sm:rounded-lg">
                <div>
                    <label for="modelo" class="block text-sm text-gray-600">Módulo</label>
                    <select id="modelo" name="modelo" class="border-gray-300 rounded-md">
                        <option value="">Todos</option>
                        @foreach ($modelos as $modelo)
                            <option value="{{ $modelo }}" @selected(($filtros['modelo'] ?? null) === $modelo)>{{ $modelo }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="accion" class="block text-sm text-gray-600">Acción</label>
                    <select id="accion" name="accion" class="border-gray-300 rounded-md">
                        <option value="">Todas</option>
                        @foreach (['creado', 'actualizado', 'eliminado'] as $accion)
                            <option value="{{ $accion }}" @selected(($filtros['accion'] ?? null) === $accion)>{{ ucfirst($accion) }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button>Filtrar</x-primary-button>
            </form>

            <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left">
                            <th class="p-3">Fecha</th>
                            <th class="p-3">Usuario</th>
                            <th class="p-3">Acción</th>
                            <th class="p-3">Módulo</th>
                            <th class="p-3">Cambios</th>
                            <th class="p-3">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($auditorias as $auditoria)
                            <tr class="border-t align-top">
                                <td class="p-3 whitespace-nowrap">{{ $auditoria->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="p-3">{{ $auditoria->usuario ? $auditoria->usuario->nombres.' '.$auditoria->usuario->apellidos : 'Sistema' }}</td>
                                <td class="p-3">{{ ucfirst($auditoria->accion) }}</td>
                                <td class="p-3">{{ $auditoria->modelo }} #{{ $auditoria->modelo_id }}</td>
                                <td class="p-3">
                                    @foreach (($auditoria->valores_nuevos ?? $auditoria->valores_anteriores ?? []) as $campo => $valor)
                                        <div>
                                            <span class="font-medium">{{ $campo }}:</span>
                                            @if ($auditoria->accion === 'actualizado')
                                                {{ json_encode($auditoria->valores_anteriores[$campo] ?? null, JSON_UNESCAPED_UNICODE) }} →
                                            @endif
                                            {{ json_encode($valor, JSON_UNESCAPED_UNICODE) }}
                                        </div>
                                    @endforeach
                                </td>
                                <td class="p-3">{{ $auditoria->ip }}</td>
                            </tr>
                        @empty
                            <tr><td class="p-4" colspan="6">No hay registros de auditoría.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-4">{{ $auditorias->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
