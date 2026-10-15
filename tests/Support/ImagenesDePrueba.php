<?php

namespace Tests\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Imágenes y discos de prueba para App\Services\ImagenSegura.
 */
final class ImagenesDePrueba
{
    /** JPEG real de $ancho × $alto. */
    public static function jpeg(int $ancho, int $alto): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagefilledrectangle($imagen, 0, 0, intdiv($ancho, 2), $alto, imagecolorallocate($imagen, 200, 120, 40));
        ob_start();
        imagejpeg($imagen, null, 90);
        imagedestroy($imagen);

        return (string) ob_get_clean();
    }

    /** PNG real con el píxel (0, 0) totalmente transparente. */
    public static function pngTransparente(int $ancho, int $alto): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 30, 90, 160));
        imagesetpixel($imagen, 0, 0, imagecolorallocatealpha($imagen, 0, 0, 0, 127));
        ob_start();
        imagepng($imagen);
        imagedestroy($imagen);

        return (string) ob_get_clean();
    }

    /** JPEG de $ancho × $alto con la orientación EXIF indicada (6 = girar 90° en sentido horario). */
    public static function jpegConOrientacion(int $ancho, int $alto, int $orientacion): string
    {
        $jpeg = self::jpeg($ancho, $alto);
        $tiff = "MM\x00\x2A\x00\x00\x00\x08"."\x00\x01"."\x01\x12\x00\x03\x00\x00\x00\x01".pack('n', $orientacion)."\x00\x00"."\x00\x00\x00\x00";
        $datos = "Exif\x00\x00".$tiff;

        return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($datos) + 2).$datos.substr($jpeg, 2);
    }

    /** Solo la cabecera de un PNG de $ancho × $alto: getimagesize() lo lee, pero no se puede decodificar. */
    public static function cabeceraPng(int $ancho, int $alto): string
    {
        $chunk = fn (string $tipo, string $datos) => pack('N', strlen($datos)).$tipo.$datos.pack('N', crc32($tipo.$datos));

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $ancho, $alto, 8, 6, 0, 0, 0)).$chunk('IEND', '');
    }

    /** Dimensiones de un archivo del disco "public": [ancho, alto]. */
    public static function dimensiones(string $ruta): array
    {
        return array_slice(getimagesize(Storage::disk('public')->path($ruta)), 0, 2);
    }

    /**
     * Sustituye el disco "public" (ya falso) por uno en el que la escritura número $fallarEn devuelve false,
     * como el disco local con 'throw' => false cuando no puede escribir.
     */
    public static function discoQueFallaAlEscribir(int $fallarEn): void
    {
        $falso = Storage::disk('public');

        Storage::set('public', new class($falso->getDriver(), $falso->getAdapter(), $falso->getConfig(), $fallarEn) extends FilesystemAdapter
        {
            private int $escrituras = 0;

            public function __construct($driver, $adapter, array $config, private int $fallarEn)
            {
                parent::__construct($driver, $adapter, $config);
            }

            public function put($path, $contents, $options = [])
            {
                return ++$this->escrituras === $this->fallarEn ? false : parent::put($path, $contents, $options);
            }
        });
    }
}
