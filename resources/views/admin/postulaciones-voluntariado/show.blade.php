<x-admin.pagina :titulo="$postulacion->usuario->nombres.' '.$postulacion->usuario->apellidos" seccion="Revisión de postulación" :volver="route('admin.postulaciones-voluntariado.index')" volver-texto="Volver a postulaciones">
    <x-slot name="subtitulo">
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <x-admin.estado de="postulacion" :valor="$postulacion->estado" />
            <span class="text-sm text-slate-500">Enviada el {{ $postulacion->created_at->format('d/m/Y H:i') }}</span>
        </div>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 lg:col-span-2">
            <x-admin.tarjeta titulo="Respuestas del cuestionario">
                <x-postulacion-respuestas :postulacion="$postulacion" />
            </x-admin.tarjeta>
        </div>

        <div class="min-w-0 space-y-6">
            <x-admin.tarjeta titulo="Postulante">
                <dl class="space-y-3">
                    <x-admin.dato etiqueta="Ciudadano">{{ $postulacion->usuario->nombres }} {{ $postulacion->usuario->apellidos }}</x-admin.dato>
                    <x-admin.dato etiqueta="Correo electrónico">{{ $postulacion->usuario->email }}</x-admin.dato>
                    <x-admin.dato etiqueta="Teléfono">{{ $postulacion->usuario->telefono ?: '—' }}</x-admin.dato>
                    <x-admin.dato etiqueta="Fecha">{{ $postulacion->created_at->format('d/m/Y H:i') }}</x-admin.dato>
                    @if ($postulacion->revisadoPor)<x-admin.dato etiqueta="Revisada por">{{ $postulacion->revisadoPor->nombres }} {{ $postulacion->revisadoPor->apellidos }}</x-admin.dato>@endif
                </dl>
                @if ($postulacion->comentarios_admin)
                    <p class="mt-4 rounded-xl bg-slate-50 p-3 text-sm"><span class="font-semibold text-slate-600">Comentarios administrativos:</span> {{ $postulacion->comentarios_admin }}</p>
                @endif
            </x-admin.tarjeta>

            @can('approve', $postulacion)
                <x-admin.tarjeta titulo="Decisión">
                    <form method="POST" action="{{ route('admin.postulaciones-voluntariado.approve', $postulacion) }}">@csrf @method('PATCH')
                        <label class="admin-etiqueta" for="comentarios_admin_aprobar">Comentarios administrativos</label>
                        <textarea id="comentarios_admin_aprobar" name="comentarios_admin" rows="2" class="admin-campo">{{ old('comentarios_admin') }}</textarea>
                        @error('comentarios_admin')<p class="admin-error">{{ $message }}</p>@enderror
                        <x-admin.boton variante="exito" class="mt-3 w-full">Aprobar</x-admin.boton>
                    </form>
                    @can('reject', $postulacion)
                        <details class="group mt-4 rounded-xl border border-red-200">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 text-sm font-semibold text-red-800">Rechazar postulación <span aria-hidden="true" class="text-red-400 group-open:rotate-180">▾</span></summary>
                            <form method="POST" action="{{ route('admin.postulaciones-voluntariado.reject', $postulacion) }}" class="border-t border-red-100 px-4 pb-4 pt-3">@csrf @method('PATCH')
                                <label class="admin-etiqueta" for="comentarios_admin_rechazar">Comentarios administrativos</label>
                                <textarea id="comentarios_admin_rechazar" name="comentarios_admin" rows="2" class="admin-campo">{{ old('comentarios_admin') }}</textarea>
                                <x-admin.boton variante="peligro" class="mt-3">Rechazar</x-admin.boton>
                            </form>
                        </details>
                    @endcan
                </x-admin.tarjeta>
            @elsecan('reject', $postulacion)
                <x-admin.tarjeta titulo="Decisión">
                    <form method="POST" action="{{ route('admin.postulaciones-voluntariado.reject', $postulacion) }}">@csrf @method('PATCH')
                        <label class="admin-etiqueta" for="comentarios_admin_rechazar">Comentarios administrativos</label>
                        <textarea id="comentarios_admin_rechazar" name="comentarios_admin" rows="2" class="admin-campo">{{ old('comentarios_admin') }}</textarea>
                        <x-admin.boton variante="peligro" class="mt-3">Rechazar</x-admin.boton>
                    </form>
                </x-admin.tarjeta>
            @endcan
        </div>
    </div>
</x-admin.pagina>
