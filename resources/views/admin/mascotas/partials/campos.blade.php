{{--
    Datos de la mascota para registrar (sin $mascota) y para editar (con $mascota). Mismos nombres, reglas y textos
    en ambos formularios; las fotos se manejan aparte en cada uno.
--}}
@php
    $mascota ??= null;
    $nueva = $mascota === null;
    $valor = fn (string $campo) => old($campo, $mascota?->{$campo});
    $opciones = [
        'especie' => ['Selecciona una especie', ['perro' => 'Perro', 'gato' => 'Gato']],
        'sexo' => ['Selecciona un sexo', ['macho' => 'Macho', 'hembra' => 'Hembra']],
        'tamano' => ['Selecciona un tamaño', ['pequeno' => 'Pequeño', 'mediano' => 'Mediano', 'grande' => 'Grande']],
    ];
@endphp
<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="nombre" class="admin-etiqueta">Nombre</label>
        <input id="nombre" name="nombre" type="text" class="admin-campo" value="{{ $valor('nombre') }}" required autofocus>
        <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
    </div>
    <div>
        <label for="especie" class="admin-etiqueta">Especie</label>
        <select id="especie" name="especie" required class="admin-campo">
            @if ($nueva)<option value="">{{ $opciones['especie'][0] }}</option>@endif
            @foreach ($opciones['especie'][1] as $clave => $texto)<option value="{{ $clave }}" @selected($valor('especie') === $clave)>{{ $texto }}</option>@endforeach
        </select>
        <x-input-error :messages="$errors->get('especie')" class="mt-2" />
    </div>
    <div>
        <label for="raza" class="admin-etiqueta">Raza</label>
        <input id="raza" name="raza" type="text" class="admin-campo" value="{{ $valor('raza') }}">
        <x-input-error :messages="$errors->get('raza')" class="mt-2" />
    </div>
    <div>
        <label for="edad" class="admin-etiqueta">Edad</label>
        <div class="flex gap-2">
            <input id="edad" name="edad" type="number" min="1" step="1" class="admin-campo" value="{{ $valor('edad') }}" required>
            <select id="edad_en" name="edad_en" aria-label="Unidad de edad" required class="admin-campo">
                @if ($nueva)<option value="">Selecciona meses o años</option>@endif
                @foreach (['meses' => 'Meses', 'años' => 'Años'] as $clave => $texto)<option value="{{ $clave }}" @selected($valor('edad_en') === $clave)>{{ $texto }}</option>@endforeach
            </select>
        </div>
        <x-input-error :messages="$errors->get('edad')" class="mt-2" />
        <x-input-error :messages="$errors->get('edad_en')" class="mt-2" />
    </div>
    <div>
        <label for="sexo" class="admin-etiqueta">Sexo</label>
        <select id="sexo" name="sexo" required class="admin-campo">
            @if ($nueva)<option value="">{{ $opciones['sexo'][0] }}</option>@endif
            @foreach ($opciones['sexo'][1] as $clave => $texto)<option value="{{ $clave }}" @selected($valor('sexo') === $clave)>{{ $texto }}</option>@endforeach
        </select>
        <x-input-error :messages="$errors->get('sexo')" class="mt-2" />
    </div>
    <div>
        <label for="tamano" class="admin-etiqueta">Tamaño</label>
        <select id="tamano" name="tamano" required class="admin-campo">
            @if ($nueva)<option value="">{{ $opciones['tamano'][0] }}</option>@endif
            @foreach ($opciones['tamano'][1] as $clave => $texto)<option value="{{ $clave }}" @selected($valor('tamano') === $clave)>{{ $texto }}</option>@endforeach
        </select>
        <x-input-error :messages="$errors->get('tamano')" class="mt-2" />
    </div>
    <div>
        <label for="color" class="admin-etiqueta">Color</label>
        <input id="color" name="color" type="text" class="admin-campo" value="{{ $valor('color') }}">
        <x-input-error :messages="$errors->get('color')" class="mt-2" />
    </div>
    <div class="md:col-span-2">
        <label for="descripcion" class="admin-etiqueta">Descripción</label>
        <textarea id="descripcion" name="descripcion" rows="4" class="admin-campo">{{ $valor('descripcion') }}</textarea>
        <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
    </div>
</div>
