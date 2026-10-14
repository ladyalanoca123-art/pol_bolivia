<?php

namespace Tests\Unit;

use App\Rules\TelefonoBoliviano;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TelefonoBolivianoTest extends TestCase
{
    #[DataProvider('entradasNormalizables')]
    public function test_normaliza_a_ocho_digitos_nacionales(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, TelefonoBoliviano::normalizar($entrada));
    }

    public static function entradasNormalizables(): array
    {
        return [
            'prefijo 5' => ['57123456', '57123456'],
            'prefijo 6' => ['61234567', '61234567'],
            'prefijo 7' => ['71234567', '71234567'],
            'con espacios' => [' 7123 4567 ', '71234567'],
            'con guiones' => ['712-34-567', '71234567'],
            '+591' => ['+59171234567', '71234567'],
            '+591 con espacio' => ['+591 61234567', '61234567'],
            '591 sin +' => ['59157123456', '57123456'],
            '+591 con separadores' => ['+591 7123-4567', '71234567'],
            // 8 dígitos que empiezan por 591: es un número nacional, no se recorta.
            'nacional que empieza por 591' => ['59112345', '59112345'],
        ];
    }

    public function test_el_valor_vacio_pasa_a_null(): void
    {
        $this->assertNull(TelefonoBoliviano::normalizar(''));
        $this->assertNull(TelefonoBoliviano::normalizar('   '));
        $this->assertNull(TelefonoBoliviano::normalizar(' - '));
        $this->assertNull(TelefonoBoliviano::normalizar(null));
    }

    #[DataProvider('formatosValidos')]
    public function test_acepta_moviles_con_prefijo_5_6_y_7(string $telefono): void
    {
        $this->assertSame([], $this->errores($telefono));
    }

    public static function formatosValidos(): array
    {
        return [['57000000'], ['57999999'], ['59112345'], ['61234567'], ['71234567'], ['79999999']];
    }

    #[DataProvider('formatosInvalidos')]
    public function test_rechaza_formatos_invalidos(mixed $telefono): void
    {
        $this->assertSame([TelefonoBoliviano::MENSAJE], $this->errores($telefono));
    }

    public static function formatosInvalidos(): array
    {
        return [
            'prefijo 4 (fijo de Cochabamba)' => ['44123456'],
            'prefijo 2' => ['22123456'],
            'prefijo 8' => ['81234567'],
            'prefijo 0' => ['01234567'],
            'siete dígitos' => ['7123456'],
            'nueve dígitos' => ['712345678'],
            'cinco dígitos' => ['12345'],
            'letras' => ['7123abcd'],
            'otro país' => ['+5491123456789'],
            '+591 con 7 dígitos' => ['+5917123456'],
            'paréntesis' => ['(7)1234567'],
            'no es texto' => [[71234567]],
        ];
    }

    public function test_null_es_valido_porque_el_telefono_es_opcional(): void
    {
        $this->assertSame([], $this->errores(null));
    }

    /** Aplica normalización + regla como lo hacen los FormRequest. */
    private function errores(mixed $valor): array
    {
        $errores = [];
        (new TelefonoBoliviano)->validate('telefono', TelefonoBoliviano::normalizar($valor), function (string $mensaje) use (&$errores) {
            $errores[] = $mensaje;
        });

        return $errores;
    }
}
