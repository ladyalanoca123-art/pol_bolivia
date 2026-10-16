<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda imágenes subidas por usuarios de forma segura.
 *
 * La imagen se vuelve a generar desde cero con GD. Así:
 * - se eliminan los metadatos EXIF (ubicación GPS, modelo de celular, fecha);
 * - se descarta cualquier contenido oculto dentro del archivo (código PHP, scripts);
 * - el nombre del archivo es aleatorio y la extensión la decide el servidor.
 *
 * Además se reduce a LADO_GUARDADO como máximo (sin agrandar las pequeñas) y se guarda una miniatura
 * de LADO_MINIATURA en "miniaturas/" junto a ella (rutaMiniatura()). Antes de decodificar se leen solo
 * las dimensiones de la cabecera (problema()), para rechazar imágenes que agotarían la memoria.
 */
class ImagenSegura
{
    private const CALIDAD_JPEG = 85;

    /** Lado mayor de la imagen guardada; las más pequeñas se conservan tal cual. */
    public const LADO_GUARDADO = 1600;

    /** Lado mayor de la miniatura de listados y tarjetas. */
    public const LADO_MINIATURA = 400;

    /** Lado menor admitido en imágenes nuevas: por debajo, la miniatura tendría que agrandarse. */
    public const LADO_MINIMO = 400;

    /**
     * Límites antes de decodificar. GD guarda cada píxel en 4 bytes: 25 megapíxeles son unos 100 MB, más la
     * memoria de trabajo del decodificador. Cubren las fotos de cámaras y celulares (12-24 MP)
     * y cortan las "bombas" de descompresión (pocos KB en disco, decenas de miles de píxeles por lado).
     */
    public const LADO_MAXIMO = 10000;

    public const MEGAPIXELES_MAXIMOS = 25;

    /** Memoria estimada por píxel al decodificar (4 bytes de GD + margen del decodificador). */
    private const BYTES_POR_PIXEL = 5;

    /** Memoria reservada para el resto de la petición. */
    private const MARGEN_MEMORIA = 32 * 1024 * 1024;

    private const TIPOS_ADMITIDOS = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_BMP];

    private const MENSAJE_INVALIDA = 'El archivo no es una imagen válida o está dañado.';

    /**
     * Motivo por el que una imagen no puede procesarse, o null. Solo lee la cabecera del archivo.
     * $exigirMinimo: las imágenes nuevas deben medir al menos LADO_MINIMO por lado.
     */
    public static function problema(string $ruta, bool $exigirMinimo = true): ?string
    {
        $info = @getimagesize($ruta);

        if ($info === false || ! in_array($info[2], self::TIPOS_ADMITIDOS, true) || $info[0] < 1 || $info[1] < 1) {
            return self::MENSAJE_INVALIDA;
        }

        [$ancho, $alto] = $info;

        if (max($ancho, $alto) > self::LADO_MAXIMO) {
            return "La imagen es demasiado grande ({$ancho} × {$alto} px). El máximo es ".number_format(self::LADO_MAXIMO, 0, ',', '.').' px por lado.';
        }

        if ($ancho * $alto > self::MEGAPIXELES_MAXIMOS * 1_000_000) {
            return 'La imagen tiene una resolución demasiado alta (máximo '.self::MEGAPIXELES_MAXIMOS.' megapíxeles).';
        }

        if (! self::memoriaSuficiente($ancho, $alto)) {
            return 'La imagen es demasiado grande para procesarla en este servidor. Reduce su tamaño e inténtalo de nuevo.';
        }

        if ($exigirMinimo && min($ancho, $alto) < self::LADO_MINIMO) {
            return "La imagen es demasiado pequeña ({$ancho} × {$alto} px). El mínimo es ".self::LADO_MINIMO.' × '.self::LADO_MINIMO.' px.';
        }

        return null;
    }

    /** Si hay memoria para decodificar una imagen de esas dimensiones (memory_limit = -1: sin límite). */
    public static function memoriaSuficiente(int $ancho, int $alto, ?int $limite = null, ?int $uso = null): bool
    {
        $limite ??= self::limiteMemoria();

        if ($limite < 0) {
            return true;
        }

        return $ancho * $alto * self::BYTES_POR_PIXEL + self::MARGEN_MEMORIA <= $limite - ($uso ?? memory_get_usage());
    }

    /** Indica si GD puede leer el archivo sin agotar la memoria: es lo que guardar() necesita. */
    public static function puedeLeer(UploadedFile $archivo): bool
    {
        if (self::problema($archivo->getRealPath(), exigirMinimo: false) !== null) {
            return false;
        }

        $imagen = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));

        if ($imagen === false) {
            return false;
        }

        imagedestroy($imagen);

        return true;
    }

    /** Ruta de la miniatura de una imagen guardada: "<carpeta>/miniaturas/<nombre>.<jpg|png>". */
    public static function rutaMiniatura(string $ruta): string
    {
        $carpeta = pathinfo($ruta, PATHINFO_DIRNAME);
        $extension = in_array(strtolower(pathinfo($ruta, PATHINFO_EXTENSION)), ['png', 'gif', 'webp'], true) ? 'png' : 'jpg';

        return ($carpeta === '.' ? '' : $carpeta.'/').'miniaturas/'.pathinfo($ruta, PATHINFO_FILENAME).'.'.$extension;
    }

    /**
     * Guarda la imagen (reducida a LADO_GUARDADO) y su miniatura. Devuelve la ruta de la imagen.
     * Si algo falla no deja archivos a medias y lanza ImagenNoProcesada con un mensaje para el usuario.
     */
    public function guardar(UploadedFile $archivo, string $carpeta, string $disco = 'public'): string
    {
        $origen = $archivo->getRealPath();

        if (($problema = self::problema($origen, exigirMinimo: false)) !== null) {
            throw new ImagenNoProcesada($problema);
        }

        $imagen = @imagecreatefromstring((string) file_get_contents($origen));

        if ($imagen === false) {
            throw new ImagenNoProcesada(self::MENSAJE_INVALIDA);
        }

        // Primero se reduce y después se gira: girar la imagen completa duplicaría la memoria.
        $imagen = $this->corregirOrientacion($this->reducir($imagen, self::LADO_GUARDADO), $origen);
        $formato = in_array((string) $archivo->getMimeType(), ['image/png', 'image/gif', 'image/webp'], true) ? 'png' : 'jpg';
        $miniatura = $this->reducir($imagen, self::LADO_MINIATURA, conservarOriginal: true);

        $contenido = $this->codificar($imagen, $formato);
        $contenidoMiniatura = $this->codificar($miniatura, $formato);
        $miniatura === $imagen || imagedestroy($miniatura);
        imagedestroy($imagen);

        $ruta = trim($carpeta, '/').'/'.Str::random(40).'.'.$formato;
        $almacen = Storage::disk($disco);

        if (! $almacen->put($ruta, $contenido)) {
            throw new ImagenNoProcesada('No se pudo guardar la imagen. Inténtalo de nuevo.');
        }

        if (! $almacen->put(self::rutaMiniatura($ruta), $contenidoMiniatura)) {
            $almacen->delete($ruta);

            throw new ImagenNoProcesada('No se pudo guardar la imagen. Inténtalo de nuevo.');
        }

        return $ruta;
    }

    /** Elimina una imagen guardada y su miniatura. Devuelve false si alguna no se pudo borrar. */
    public function eliminar(string $ruta, string $disco = 'public'): bool
    {
        $almacen = Storage::disk($disco);
        $miniatura = self::rutaMiniatura($ruta);

        $imagenBorrada = ! $almacen->exists($ruta) || $almacen->delete($ruta);
        $miniaturaBorrada = ! $almacen->exists($miniatura) || $almacen->delete($miniatura);

        return $imagenBorrada && $miniaturaBorrada;
    }

    /**
     * Genera la miniatura de una imagen ya guardada, sin modificar la imagen. Devuelve 'omitida' si ya hay
     * una miniatura válida, y 'procesada' si la generó (o la generaría, con $simular). Lanza ImagenNoProcesada
     * si la imagen no existe, no se puede leer o la miniatura no se puede guardar.
     */
    public function generarMiniatura(string $ruta, string $disco = 'public', bool $simular = false): string
    {
        $almacen = Storage::disk($disco);
        $rutaMiniatura = self::rutaMiniatura($ruta);

        if (! $almacen->exists($ruta)) {
            throw new ImagenNoProcesada('No existe el archivo de la imagen.');
        }

        if ($almacen->exists($rutaMiniatura) && self::miniaturaValida($almacen->path($rutaMiniatura))) {
            return 'omitida';
        }

        $origen = $almacen->path($ruta);

        if (($problema = self::problema($origen, exigirMinimo: false)) !== null) {
            throw new ImagenNoProcesada($problema);
        }

        if ($simular) {
            return 'procesada';
        }

        $imagen = @imagecreatefromstring((string) $almacen->get($ruta));

        if ($imagen === false) {
            throw new ImagenNoProcesada(self::MENSAJE_INVALIDA);
        }

        $miniatura = $this->corregirOrientacion($this->reducir($imagen, self::LADO_MINIATURA), $origen);
        $contenido = $this->codificar($miniatura, pathinfo($rutaMiniatura, PATHINFO_EXTENSION));
        imagedestroy($miniatura);

        if (! $almacen->put($rutaMiniatura, $contenido)) {
            throw new ImagenNoProcesada('No se pudo guardar la miniatura.');
        }

        return 'procesada';
    }

    /** Una miniatura es válida si se puede leer y no supera LADO_MINIATURA. */
    private static function miniaturaValida(string $ruta): bool
    {
        $info = @getimagesize($ruta);

        return $info !== false && $info[0] > 0 && $info[1] > 0 && max($info[0], $info[1]) <= self::LADO_MINIATURA;
    }

    /**
     * Reduce la imagen para que su lado mayor no supere $lado, conservando la proporción y la transparencia.
     * Si ya es más pequeña la devuelve tal cual. Sin $conservarOriginal, libera la imagen original.
     */
    private function reducir(\GdImage $imagen, int $lado, bool $conservarOriginal = false): \GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);

        if (max($ancho, $alto) <= $lado) {
            return $imagen;
        }

        $escala = $lado / max($ancho, $alto);
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $reducida = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($reducida, false);
        imagesavealpha($reducida, true);
        imagefill($reducida, 0, 0, imagecolorallocatealpha($reducida, 0, 0, 0, 127));
        imagecopyresampled($reducida, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        if (! $conservarOriginal) {
            imagedestroy($imagen);
        }

        return $reducida;
    }

    /**
     * Las fotos de celular guardan la rotación en EXIF; como EXIF se elimina,
     * se aplica la rotación a la imagen antes.
     */
    private function corregirOrientacion(\GdImage $imagen, string $origen): \GdImage
    {
        if (! function_exists('exif_read_data') || (@getimagesize($origen)[2] ?? null) !== IMAGETYPE_JPEG) {
            return $imagen;
        }

        $orientacion = @exif_read_data($origen)['Orientation'] ?? 1;
        $grados = [3 => 180, 6 => -90, 8 => 90][$orientacion] ?? 0;

        if (! $grados) {
            return $imagen;
        }

        $girada = imagerotate($imagen, $grados, 0);
        imagedestroy($imagen);

        return $girada;
    }

    /** Codifica como PNG (formatos con transparencia) o como JPEG. */
    private function codificar(\GdImage $imagen, string $formato): string
    {
        ob_start();

        if ($formato === 'png') {
            imagesavealpha($imagen, true);
            imagepng($imagen);
        } else {
            imagejpeg($imagen, null, self::CALIDAD_JPEG);
        }

        return (string) ob_get_clean();
    }

    /** memory_limit en bytes (-1 si no hay límite). */
    private static function limiteMemoria(): int
    {
        $valor = trim((string) ini_get('memory_limit'));

        if ($valor === '' || $valor === '-1') {
            return -1;
        }

        $numero = (int) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g' => $numero * 1024 ** 3,
            'm' => $numero * 1024 ** 2,
            'k' => $numero * 1024,
            default => $numero,
        };
    }
}
