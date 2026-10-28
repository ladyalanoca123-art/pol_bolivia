@php($correoBloqueado = auth()->user()->cannot('updateEmail', $user))
<x-admin.pagina titulo="Editar usuario" :seccion="$user->nombres.' '.$user->apellidos" descripcion="El rol y el estado se gestionan mediante sus flujos autorizados." ancho="max-w-3xl" :volver="route('admin.usuarios.show', $user)" volver-texto="Volver al detalle">
    <form method="POST" action="{{ route('admin.usuarios.update', $user) }}" class="space-y-6">
        @csrf
        @method('PATCH')
        <x-admin.tarjeta titulo="Datos de la cuenta">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="nombres" class="admin-etiqueta">Nombres</label>
                    <input id="nombres" name="nombres" class="admin-campo" value="{{ old('nombres', $user->nombres) }}" required>
                    <x-input-error :messages="$errors->get('nombres')" class="mt-2" />
                </div>
                <div>
                    <label for="apellidos" class="admin-etiqueta">Apellidos</label>
                    <input id="apellidos" name="apellidos" class="admin-campo" value="{{ old('apellidos', $user->apellidos) }}" required>
                    <x-input-error :messages="$errors->get('apellidos')" class="mt-2" />
                </div>
                <div>
                    <label for="email" class="admin-etiqueta">Correo</label>
                    <input id="email" name="email" type="email" class="admin-campo {{ $correoBloqueado ? 'bg-slate-100 text-slate-500' : '' }}" value="{{ old('email', $user->email) }}" required @if ($correoBloqueado) readonly aria-describedby="email-ayuda" @endif>
                    @if ($correoBloqueado)<p id="email-ayuda" class="admin-ayuda">El correo de otro administrador no se puede cambiar desde aquí.</p>@endif
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <div>
                    <label for="telefono" class="admin-etiqueta">Teléfono</label>
                    <input id="telefono" name="telefono" class="admin-campo" value="{{ old('telefono', $user->telefono) }}" placeholder="Ej. 71234567">
                    <x-input-error :messages="$errors->get('telefono')" class="mt-2" />
                </div>
            </div>
        </x-admin.tarjeta>
        <div class="flex flex-wrap items-center gap-3">
            <x-admin.boton>Guardar cambios</x-admin.boton>
            <x-admin.boton variante="secundario" :href="route('admin.usuarios.show', $user)">Cancelar</x-admin.boton>
        </div>
    </form>
</x-admin.pagina>
