import Alpine from 'alpinejs';

window.Alpine = Alpine;

/*
 * Fotos de mascotas: se reducen en el navegador antes de subirlas para que las de celular suban rápido.
 * Solo es una ayuda: el servidor siempre las valida y las vuelve a procesar con GD (App\Services\ImagenSegura).
 */
const LADO_OPTIMIZADO = 2000;
const PESO_SIN_CAMBIOS = 2 * 1024 * 1024;
const CALIDAD_JPEG = 0.85;

// "10 MB", "1,4 MB", "11 KB": sin decimales de sobra, como en las ayudas del servidor.
const megas = (bytes, decimales = 1) => (bytes / 1024 / 1024).toLocaleString('es-BO', { minimumFractionDigits: 0, maximumFractionDigits: decimales });
const tamano = (bytes, decimales = 1) => (bytes < 100 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${megas(bytes, decimales)} MB`);

/** Si el lienzo tiene píxeles transparentes. Revisa una muestra (y siempre las esquinas) para no ralentizar. */
function tieneTransparencia(contexto, ancho, alto) {
    const { data } = contexto.getImageData(0, 0, ancho, alto);
    const total = ancho * alto;
    const paso = Math.max(1, Math.floor(total / 250000));
    const esquinas = [0, ancho - 1, (alto - 1) * ancho, total - 1];

    if (esquinas.some((pixel) => data[pixel * 4 + 3] < 255)) {
        return true;
    }

    for (let pixel = 0; pixel < total; pixel += paso) {
        if (data[pixel * 4 + 3] < 255) {
            return true;
        }
    }

    return false;
}

async function leerImagen(archivo) {
    try {
        return await createImageBitmap(archivo, { imageOrientation: 'from-image' });
    } catch {
        return await createImageBitmap(archivo);
    }
}

/**
 * Devuelve la foto reducida a LADO_OPTIMIZADO de lado mayor (JPEG, o PNG si tiene transparencia), la misma
 * foto si ya es pequeña, o null si el navegador no puede procesarla (p. ej. HEIC): entonces se envía la original.
 */
async function optimizarFoto(archivo) {
    if (!archivo.type.startsWith('image/') || typeof createImageBitmap !== 'function') {
        return null;
    }

    const imagen = await leerImagen(archivo);
    const { width, height } = imagen;

    if (Math.max(width, height) <= LADO_OPTIMIZADO && archivo.size <= PESO_SIN_CAMBIOS) {
        imagen.close();

        return archivo;
    }

    const escala = Math.min(1, LADO_OPTIMIZADO / Math.max(width, height));
    const lienzo = document.createElement('canvas');
    lienzo.width = Math.max(1, Math.round(width * escala));
    lienzo.height = Math.max(1, Math.round(height * escala));
    const contexto = lienzo.getContext('2d', { willReadFrequently: true });
    contexto.drawImage(imagen, 0, 0, lienzo.width, lienzo.height);
    imagen.close();

    // Un JPEG nunca tiene transparencia; un PNG o WEBP con transparencia se conserva como PNG.
    const transparente = archivo.type !== 'image/jpeg' && tieneTransparencia(contexto, lienzo.width, lienzo.height);
    const tipo = transparente ? 'image/png' : 'image/jpeg';
    const datos = await new Promise((resolver) => lienzo.toBlob(resolver, tipo, CALIDAD_JPEG));

    if (!datos || (escala === 1 && datos.size >= archivo.size)) {
        return datos ? archivo : null;
    }

    const extension = tipo === 'image/jpeg' ? 'jpg' : 'png';
    const nombre = archivo.type === tipo ? archivo.name : archivo.name.replace(/\.[^.]*$/, '') + '.' + extension;

    return new File([datos], nombre, { type: tipo, lastModified: archivo.lastModified });
}

/**
 * Formulario con selectores de fotos (input[type=file] con data-maximo). Al elegir fotos las optimiza,
 * muestra el resultado y avisa si son demasiadas o pesan demasiado; mientras tanto no deja enviar.
 */
Alpine.data('optimizadorFotos', ({ maximoBytes, textoEnviando = 'Subiendo fotos…' }) => ({
    procesando: 0,
    enviando: false,
    estado: {},
    selecciones: {},
    textoEnviando,

    get bloqueado() {
        return this.procesando > 0 || Object.values(this.estado).some((campo) => campo.avisos.length > 0);
    },

    lineas(campo) {
        return this.estado[campo]?.lineas ?? [];
    },

    avisos(campo) {
        return this.estado[campo]?.avisos ?? [];
    },

    optimizando(campo) {
        return this.estado[campo]?.optimizando ?? false;
    },

    async optimizar(selector) {
        // Cada selección del campo recibe un número: si se eligen fotos otra vez mientras se optimizan,
        // la optimización anterior se abandona y nunca sobrescribe la selección nueva.
        const seleccion = (this.selecciones[selector.name] ?? 0) + 1;
        this.selecciones[selector.name] = seleccion;
        const vigente = () => this.selecciones[selector.name] === seleccion;

        const originales = [...selector.files];
        const maximo = Number(selector.dataset.maximo || 1);
        // Se trabaja sobre el objeto ya guardado en el estado (reactivo) para que la vista se actualice.
        this.estado[selector.name] = { lineas: [], avisos: [], optimizando: false };
        const campo = this.estado[selector.name];

        if (originales.length === 0) {
            return;
        }

        // data-aviso: el mismo mensaje que da el servidor (p. ej. "Solo puedes agregar 2 fotos más a esta mascota.").
        if (originales.length > maximo) {
            campo.avisos.push(`Seleccionaste ${originales.length} ${originales.length === 1 ? 'foto' : 'fotos'}. ${selector.dataset.aviso ?? ''}`.trim());

            return;
        }

        this.procesando++;
        campo.optimizando = true;

        try {
            const finales = [];

            for (const original of originales) {
                try {
                    finales.push(await optimizarFoto(original));
                } catch {
                    finales.push(null);
                }

                if (!vigente()) {
                    return;
                }
            }

            const reemplazada = this.reemplazar(selector, originales, finales);

            originales.forEach((original, i) => {
                const final = finales[i];
                // Lo que de verdad se enviará: la optimizada solo si se pudo poner en el selector.
                const enviada = reemplazada && final !== null ? final : original;

                if (final === null) {
                    campo.lineas.push(`${original.name}: no se pudo optimizar en el navegador; se enviará la original (${tamano(original.size)}).`);
                } else if (final === original) {
                    campo.lineas.push(`${original.name}: ${tamano(original.size)} (sin cambios).`);
                } else if (reemplazada) {
                    campo.lineas.push(`${original.name}: ${tamano(original.size)} → ${tamano(final.size)}.`);
                } else {
                    campo.lineas.push(`${original.name}: este navegador no permite reemplazar la foto; se enviará la original (${tamano(original.size)}).`);
                }

                if (enviada.size > maximoBytes) {
                    campo.avisos.push(`${original.name} pesa ${tamano(enviada.size, 2)}; el máximo es ${megas(maximoBytes)} MB por foto.`);
                }
            });
        } finally {
            campo.optimizando = false;
            this.procesando--;
        }
    },

    /**
     * Pone en el selector las fotos optimizadas (no dispara otro "change"). Devuelve si se pudo: sin DataTransfer,
     * o si el navegador no deja cambiar los archivos, se enviarán las originales.
     */
    reemplazar(selector, originales, finales) {
        const nuevas = finales.map((final, i) => final ?? originales[i]);

        if (nuevas.every((nueva, i) => nueva === originales[i])) {
            return true;
        }

        try {
            const transferencia = new DataTransfer();
            nuevas.forEach((nueva) => transferencia.items.add(nueva));
            selector.files = transferencia.files;
        } catch {
            return false;
        }

        return selector.files.length === nuevas.length && nuevas.every((nueva, i) => selector.files[i].size === nueva.size && selector.files[i].name === nueva.name);
    },

    enviar(evento) {
        if (this.bloqueado || this.enviando) {
            evento.preventDefault();

            return;
        }

        this.enviando = true;
    },
}));

/** Galería de la ficha pública: foto grande, miniaturas, flechas (circulares) y teclado. */
Alpine.data('galeriaFotos', (fotos) => ({
    fotos,
    actual: 0,

    ir(indice) {
        this.actual = (indice + this.fotos.length) % this.fotos.length;
    },

    anterior() {
        this.ir(this.actual - 1);
    },

    siguiente() {
        this.ir(this.actual + 1);
    },
}));

/**
 * Fotos con respaldo (componente x-foto-mascota): si el archivo no carga, se quita la imagen y queda visible
 * el fondo con la huella, en lugar del ícono de imagen rota.
 */
const quitarSiFalla = (imagen) => imagen.remove();
document.addEventListener('error', (evento) => {
    if (evento.target instanceof HTMLImageElement && evento.target.hasAttribute('data-respaldo')) {
        quitarSiFalla(evento.target);
    }
}, true);
document.querySelectorAll('img[data-respaldo]').forEach((imagen) => {
    if (imagen.complete && imagen.naturalWidth === 0) {
        quitarSiFalla(imagen);
    }
});

Alpine.start();
