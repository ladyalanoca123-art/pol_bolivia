@php($maximoAdicionales = \App\Models\FotoMascota::MAXIMO_POR_MASCOTA - 1)
<x-admin.pagina titulo="Registrar mascota" seccion="Mascotas" descripcion="Completa los datos y sube al menos la foto principal. La mascota aparecerá en el catálogo como disponible." ancho="max-w-4xl" :volver="route('admin.mascotas.index')" volver-texto="Volver a mascotas">
    <form method="POST" action="{{ route('admin.mascotas.store') }}" enctype="multipart/form-data" class="space-y-6"
          x-data="optimizadorFotos({ maximoBytes: {{ config('pol.mascotas.foto_max_kb') * 1024 }}, textoEnviando: 'Registrando mascota…' })" x-on:submit="enviar($event)" x-on:pageshow.window="enviando = false">
        @csrf

        <x-admin.tarjeta titulo="Datos de la mascota">
            @include('admin.mascotas.partials.campos')
        </x-admin.tarjeta>

        <x-admin.tarjeta titulo="Fotos" descripcion="La foto principal es la que se ve primero en el catálogo.">
            <div class="space-y-6">
                <div>
                    <label for="foto_principal" class="admin-etiqueta">Foto principal</label>
                    <input id="foto_principal" name="foto_principal" type="file" accept="image/jpeg,image/png,image/webp" required data-maximo="1"
                           x-on:change="optimizar($event.target)" class="admin-archivo">
                    <p class="admin-ayuda">Esta imagen se mostrará como foto principal de la mascota.</p>
                    @include('admin.mascotas.partials.ayuda-fotos', ['campo' => 'foto_principal'])
                    <x-input-error :messages="$errors->get('foto_principal')" class="mt-2" />
                </div>
                <div>
                    <label for="fotos_adicionales" class="admin-etiqueta">Fotos adicionales (opcional)</label>
                    <input id="fotos_adicionales" name="fotos_adicionales[]" type="file" accept="image/jpeg,image/png,image/webp" multiple
                           data-maximo="{{ $maximoAdicionales }}" data-aviso="Puedes subir como máximo {{ $maximoAdicionales }} fotos adicionales (4 en total con la principal)."
                           x-on:change="optimizar($event.target)" class="admin-archivo">
                    <p class="admin-ayuda">Hasta {{ $maximoAdicionales }} fotos más; se mostrarán después de la principal.</p>
                    @include('admin.mascotas.partials.ayuda-fotos', ['campo' => 'fotos_adicionales[]', 'ayuda' => false])
                    <x-input-error :messages="[...$errors->get('fotos_adicionales'), ...\Illuminate\Support\Arr::flatten($errors->get('fotos_adicionales.*'))]" class="mt-2" />
                </div>
            </div>
        </x-admin.tarjeta>

        <div class="flex flex-wrap items-center gap-3">
            <x-admin.boton variante="destacado" x-bind:disabled="bloqueado || enviando">
                <span x-text="enviando ? textoEnviando : (procesando ? 'Optimizando fotos…' : 'Registrar mascota')">Registrar mascota</span>
            </x-admin.boton>
            <x-admin.boton variante="secundario" :href="route('admin.mascotas.index')">Cancelar</x-admin.boton>
        </div>
    </form>
</x-admin.pagina>
