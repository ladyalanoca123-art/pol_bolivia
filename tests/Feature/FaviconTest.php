<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Favicon de POL-Bolivia (resources/views/partials/favicon.blade.php) en todas las páginas, con archivos reales.
 */
class FaviconTest extends TestCase
{
    use RefreshDatabase;

    private const ENLACES = [
        '<link rel="icon" href="%s/favicon.ico" sizes="any">',
        '<link rel="icon" type="image/png" sizes="32x32" href="%s/images/pol-bolivia/logos/favicon_32.png">',
        '<link rel="icon" type="image/png" sizes="192x192" href="%s/images/pol-bolivia/logos/favicon_192.png">',
        '<link rel="apple-touch-icon" href="%s/images/pol-bolivia/logos/favicon_192.png">',
    ];

    public function test_todas_las_paginas_incluyen_el_favicon_de_pol(): void
    {
        $ciudadano = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['rol' => 'administrador'])->save();

        $paginas = [
            'inicio (layout public)' => [null, '/'],
            'catálogo (layout public)' => [null, '/mascotas'],
            'iniciar sesión' => [null, '/login'],
            'crear cuenta' => [null, '/register'],
            '¿Olvidaste tu contraseña? (layout guest)' => [null, '/forgot-password'],
            'Mi perfil (layout app)' => [$ciudadano, '/profile'],
            'panel del administrador' => [$admin, '/admin/panel'],
        ];

        foreach ($paginas as $pagina => [$usuario, $ruta]) {
            $html = ($usuario ? $this->actingAs($usuario) : $this)->get($ruta)->assertOk()->getContent();
            $this->assertEnlaces($html, $pagina);
            auth()->logout();
            $this->flushSession();
        }

        // Páginas de error (plantilla propia, sin @vite).
        $this->assertEnlaces(View::make('errors.500')->render(), 'error 500');
    }

    public function test_los_archivos_del_favicon_existen_y_son_imagenes_validas(): void
    {
        $ico = file_get_contents(public_path('favicon.ico'));
        $this->assertNotSame('', $ico, 'public/favicon.ico ya no está vacío');
        $this->assertSame("\x00\x00\x01\x00", substr($ico, 0, 4), 'cabecera de un archivo .ico');
        $this->assertSame(file_get_contents(public_path('images/pol-bolivia/logos/favicon.ico')), $ico, 'es el favicon de POL');

        foreach (['favicon_32.png' => 32, 'favicon_192.png' => 192] as $archivo => $lado) {
            $info = getimagesize(public_path("images/pol-bolivia/logos/{$archivo}"));
            $this->assertSame([$lado, $lado, IMAGETYPE_PNG], [$info[0], $info[1], $info[2]], $archivo);
        }
    }

    public function test_ya_no_existe_el_logo_de_laravel(): void
    {
        $this->assertFalse(View::exists('components.application-logo'));
        $this->assertFileDoesNotExist(resource_path('views/components/application-logo.blade.php'));
    }

    private function assertEnlaces(string $html, string $pagina): void
    {
        $base = rtrim(asset(''), '/');

        foreach (self::ENLACES as $enlace) {
            $this->assertStringContainsString(sprintf($enlace, $base), $html, "Falta el favicon en: {$pagina}");
        }
    }
}
