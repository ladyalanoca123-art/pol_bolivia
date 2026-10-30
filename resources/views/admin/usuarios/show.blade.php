<x-admin.pagina :titulo="$user->nombres.' '.$user->apellidos" seccion="Detalle de usuario" ancho="max-w-4xl" :volver="route('admin.usuarios.index')" volver-texto="Volver al listado">
    <x-slot name="subtitulo">
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <x-admin.insignia :tono="['administrador' => 'indigo', 'voluntario' => 'ambar'][$user->rol] ?? 'gris'" :punto="false">{{ ucfirst($user->rol) }}</x-admin.insignia>
            @if ($user->es_principal)<x-admin.insignia tono="ambar" :punto="false">Administrador principal</x-admin.insignia>@endif
            <x-admin.estado de="usuario" :valor="(bool) $user->estado" />
        </div>
    </x-slot>
    @can('update', $user)
        <x-slot name="acciones">
            <x-admin.boton variante="secundario" :href="route('admin.usuarios.edit', $user)">Editar usuario</x-admin.boton>
        </x-slot>
    @endcan

    <x-admin.tarjeta titulo="Datos de la cuenta">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-admin.dato etiqueta="Nombres">{{ $user->nombres }}</x-admin.dato>
            <x-admin.dato etiqueta="Apellidos">{{ $user->apellidos }}</x-admin.dato>
            <x-admin.dato etiqueta="Correo">{{ $user->email }}</x-admin.dato>
            <x-admin.dato etiqueta="Teléfono">{{ $user->telefono ?? 'No registrado' }}</x-admin.dato>
            <x-admin.dato etiqueta="Rol">{{ ucfirst($user->rol) }}</x-admin.dato>
            <x-admin.dato etiqueta="Estado">{{ $user->estado ? 'Activo' : 'Inactivo' }}</x-admin.dato>
            <x-admin.dato etiqueta="Fecha de registro">{{ $user->created_at->format('d/m/Y H:i') }}</x-admin.dato>
        </dl>
        @cannot('update', $user)
            <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Al administrador principal solo lo edita él mismo.</p>
        @endcannot
    </x-admin.tarjeta>

    {{-- Constancia de consentimiento (tabla aceptaciones_legales): no se puede modificar. --}}
    @php
        $documentos = ['terminos' => 'Términos y condiciones', 'privacidad' => 'Política de privacidad'];
        $origenes = ['registro' => 'Al crear la cuenta', 'actualizacion' => 'Al iniciar sesión'];
        $alDia = ! \App\Support\AceptacionDocumentosLegales::pendiente($user);
    @endphp
    <x-admin.tarjeta titulo="Aceptaciones legales" descripcion="Cuándo y cómo aceptó los Términos y condiciones y la Política de privacidad. Esta constancia no se puede modificar." :relleno="false">
        <x-slot name="acciones">
            @if ($alDia)
                <x-admin.insignia tono="verde">Versión vigente aceptada</x-admin.insignia>
            @else
                <x-admin.insignia tono="ambar">Pendiente: se le pedirá al iniciar sesión</x-admin.insignia>
            @endif
        </x-slot>
        @if ($aceptaciones->isEmpty())
            <x-admin.vacio titulo="Todavía no aceptó ningún documento." texto="La cuenta es anterior a los documentos legales: se le pedirá aceptarlos la próxima vez que inicie sesión." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[44rem] text-left text-sm">
                    <thead class="border-b border-slate-100 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-medium sm:px-6">Documento</th>
                            <th class="px-3 py-3 font-medium">Versión</th>
                            <th class="px-3 py-3 font-medium">Fecha y hora</th>
                            <th class="px-3 py-3 font-medium">Cómo</th>
                            <th class="px-3 py-3 font-medium">IP</th>
                            <th class="px-5 py-3 font-medium sm:px-6">Navegador</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($aceptaciones as $aceptacion)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3 font-medium text-blue-950 sm:px-6">{{ $documentos[$aceptacion->documento] ?? $aceptacion->documento }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-slate-700">{{ $aceptacion->version }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-slate-700">{{ $aceptacion->aceptado_at->format('d/m/Y H:i') }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-slate-700">{{ $origenes[$aceptacion->origen] ?? $aceptacion->origen }}</td>
                                <td class="px-3 py-3 font-mono text-xs text-slate-600">{{ $aceptacion->ip ?? '—' }}</td>
                                <td class="px-5 py-3 text-xs text-slate-500 sm:px-6"><span class="block max-w-[9rem] truncate" title="{{ $aceptacion->user_agent }}">{{ $aceptacion->user_agent ?? '—' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.tarjeta>
</x-admin.pagina>
