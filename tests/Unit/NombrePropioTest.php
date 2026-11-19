<?php

namespace Tests\Unit;

use App\Support\NombrePropio;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NombrePropioTest extends TestCase
{
    #[DataProvider('casos')]
    public function test_pone_mayuscula_inicial(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, NombrePropio::formatear($entrada));
    }

    public static function casos(): array
    {
        return [
            'minúsculas' => ['jhon cruz', 'Jhon Cruz'],
            'mezcla' => ['jose carlos COPA', 'Jose Carlos Copa'],
            'tildes y ñ' => ['ÁNGELA PEÑA ÑUFLO', 'Ángela Peña Ñuflo'],
            'espacios sobrantes' => ['  daniela   flores  ', 'Daniela Flores'],
            'partículas' => ['maría DE LOS ángeles', 'María de los Ángeles'],
            'partícula al inicio' => ['de la cruz', 'De la Cruz'],
            'guion' => ['pérez-gómez', 'Pérez-Gómez'],
            'apóstrofe' => ["o'brien", "O'Brien"],
            'punto' => ['ana.maría', 'Ana.María'],
            'ya correcto' => ['Lady Alanoca', 'Lady Alanoca'],
        ];
    }

    public function test_respeta_nulo_y_vacio(): void
    {
        $this->assertNull(NombrePropio::formatear(null));
        $this->assertSame('', NombrePropio::formatear('   '));
    }
}
