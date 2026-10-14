<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Guarda imágenes subidas por usuarios de forma segura.
 *
 * La imagen se vuelve a generar desde cero con GD. Así:
 * - se eliminan los metadatos EXIF (ubicación GPS, modelo de celular, fecha);
 * - se descarta cualquier contenido oculto dentro del archivo (código PHP, scripts);
 * - el nombre del archivo es aleatorio y la extensión la decide el servidor.
 */
class ImagenSegura
{
    private const CALIDAD_JPEG = 85;

    public function guardar(UploadedFile $archivo, string $carpeta, string $disco = 'public'): string
    {
        $imagen = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));

        if ($imagen === false) {
            throw new RuntimeException('El archivo no es una imagen válida.');
        }

        $imagen = $this->corregirOrientacion($imagen, $archivo);
        [$contenido, $extension] = $this->codificar($imagen, (string) $archivo->getMimeType());
        imagedestroy($imagen);

        $ruta = trim($carpeta, '/').'/'.Str::random(40).'.'.$extension;
        Storage::disk($disco)->put($ruta, $contenido);

        return $ruta;
    }

    /**
     * Las fotos de celular guardan la rotación en EXIF; como EXIF se elimina,
     * se aplica la rotación a la imagen antes.
     */
    private function corregirOrientacion(\GdImage $imagen, UploadedFile $archivo): \GdImage
    {
        if (! function_exists('exif_read_data') || $archivo->getMimeType() !== 'image/jpeg') {
            return $imagen;
        }

        $orientacion = @exif_read_data($archivo->getRealPath())['Orientation'] ?? 1;
        $grados = [3 => 180, 6 => -90, 8 => 90][$orientacion] ?? 0;

        return $grados ? imagerotate($imagen, $grados, 0) : $imagen;
    }

    /**
     * @return array{0: string, 1: string} contenido binario y extensión
     */
    private function codificar(\GdImage $imagen, string $mime): array
    {
        ob_start();

        if (in_array($mime, ['image/png', 'image/gif', 'image/webp'], true)) {
            // Formatos con transparencia se guardan como PNG.
            imagesavealpha($imagen, true);
            imagepng($imagen);
            $extension = 'png';
        } else {
            imagejpeg($imagen, null, self::CALIDAD_JPEG);
            $extension = 'jpg';
        }

        return [(string) ob_get_clean(), $extension];
    }
}
