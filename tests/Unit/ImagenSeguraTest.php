<?php

namespace Tests\Unit;

use App\Services\ImagenSegura;
use PHPUnit\Framework\TestCase;
use Tests\Support\ImagenesDePrueba;

/**
 * Comprobaciones de ImagenSegura que no necesitan la aplicación: rutas, memoria y cabeceras.
 */
class ImagenSeguraTest extends TestCase
{
    public function test_la_miniatura_se_guarda_en_la_carpeta_miniaturas_con_un_formato_conocido(): void
    {
        $this->assertSame('mascotas/7/miniaturas/abc.jpg', ImagenSegura::rutaMiniatura('mascotas/7/abc.jpg'));
        $this->assertSame('mascotas/7/miniaturas/abc.png', ImagenSegura::rutaMiniatura('mascotas/7/abc.png'));
        $this->assertSame('mascotas/7/miniaturas/abc.jpg', ImagenSegura::rutaMiniatura('mascotas/7/abc.JPEG'));
        $this->assertSame('mascotas/7/miniaturas/abc.png', ImagenSegura::rutaMiniatura('mascotas/7/abc.webp'));
        $this->assertSame('miniaturas/abc.jpg', ImagenSegura::rutaMiniatura('abc.jpg'));
    }

    public function test_la_memoria_disponible_limita_las_dimensiones_que_se_decodifican(): void
    {
        $mb = 1024 * 1024;

        // 12 MP (4000 × 3000): unos 57 MB más el margen; caben en 128 MB con 20 MB en uso.
        $this->assertTrue(ImagenSegura::memoriaSuficiente(4000, 3000, 128 * $mb, 20 * $mb));
        // 40 MP: unos 190 MB; no caben en 128 MB, sí en 512 MB.
        $this->assertFalse(ImagenSegura::memoriaSuficiente(8000, 5000, 128 * $mb, 20 * $mb));
        $this->assertTrue(ImagenSegura::memoriaSuficiente(8000, 5000, 512 * $mb, 40 * $mb));
        // memory_limit = -1: sin límite.
        $this->assertTrue(ImagenSegura::memoriaSuficiente(10000, 4000, -1, 0));
    }

    public function test_la_cabecera_decide_sin_decodificar_la_imagen(): void
    {
        $this->assertNull(ImagenSegura::problema($this->archivo(ImagenesDePrueba::jpeg(400, 400))));
        $this->assertSame('La imagen es demasiado pequeña (300 × 500 px). El mínimo es 400 × 400 px.', ImagenSegura::problema($this->archivo(ImagenesDePrueba::jpeg(300, 500))));
        $this->assertNull(ImagenSegura::problema($this->archivo(ImagenesDePrueba::jpeg(300, 500)), exigirMinimo: false));
        $this->assertSame('La imagen es demasiado grande (12000 × 12000 px). El máximo es 10.000 px por lado.', ImagenSegura::problema($this->archivo(ImagenesDePrueba::cabeceraPng(12000, 12000))));
        // 9000 × 5000 = 45 MP y 6000 × 4500 = 27 MP: por debajo de 10.000 px por lado, pero más de 25 MP.
        $this->assertSame('La imagen tiene una resolución demasiado alta (máximo 25 megapíxeles).', ImagenSegura::problema($this->archivo(ImagenesDePrueba::cabeceraPng(9000, 5000))));
        $this->assertSame('La imagen tiene una resolución demasiado alta (máximo 25 megapíxeles).', ImagenSegura::problema($this->archivo(ImagenesDePrueba::cabeceraPng(6000, 4500))));
        $this->assertSame('El archivo no es una imagen válida o está dañado.', ImagenSegura::problema($this->archivo('no es una imagen')));
    }

    private function archivo(string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($ruta, $contenido);
        register_shutdown_function(fn () => @unlink($ruta));

        return $ruta;
    }
}
