<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * config/trustedproxy.php: detrás de un intermediario de confianza se usa la IP real del visitante y se
 * reconoce el HTTPS original; sin configurarlo, las cabeceras reenviadas se ignoran (no se pueden falsificar).
 */
class IntermediariosConfianzaTest extends TestCase
{
    private function peticionReenviada(): Request
    {
        return Request::create('http://pol.test/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '181.115.10.20',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]);
    }

    private function procesar(Request $peticion): Request
    {
        (new TrustProxies)->handle($peticion, fn ($p) => response('ok'));

        return $peticion;
    }

    public function test_sin_intermediarios_de_confianza_se_ignoran_las_cabeceras_reenviadas(): void
    {
        config(['trustedproxy.proxies' => null]);
        $peticion = $this->procesar($this->peticionReenviada());

        $this->assertSame('10.0.0.5', $peticion->ip());
        $this->assertFalse($peticion->isSecure());
    }

    public function test_con_el_intermediario_de_confianza_se_usa_la_ip_real_y_el_https(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.0/8']);
        $peticion = $this->procesar($this->peticionReenviada());

        $this->assertSame('181.115.10.20', $peticion->ip());
        $this->assertTrue($peticion->isSecure());
    }

    public function test_un_intermediario_que_no_esta_en_la_lista_no_puede_cambiar_la_ip(): void
    {
        config(['trustedproxy.proxies' => '192.168.1.1']);
        $peticion = $this->procesar($this->peticionReenviada());

        $this->assertSame('10.0.0.5', $peticion->ip());
    }
}
