<?php

namespace App\Services;

use RuntimeException;

/**
 * Una imagen no se pudo validar, procesar o guardar (App\Services\ImagenSegura).
 * El mensaje está pensado para mostrarse al usuario: no contiene rutas ni detalles internos.
 */
class ImagenNoProcesada extends RuntimeException
{
}
