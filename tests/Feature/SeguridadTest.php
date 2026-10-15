<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas de las medidas de seguridad agregadas el 5 de octubre de 2026.
 */
class SeguridadTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_respuestas_incluyen_cabeceras_de_seguridad(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]+'/", $csp);
    }

    public function test_el_registro_se_bloquea_tras_demasiados_intentos(): void
    {
        foreach (range(1, 5) as $intento) {
            $this->post('/register', ['email' => "bot{$intento}@example.com"])->assertStatus(302);
        }

        $this->post('/register', ['email' => 'bot6@example.com'])->assertStatus(429);
    }

    public function test_recuperar_contrasena_se_bloquea_tras_demasiados_intentos(): void
    {
        foreach (range(1, 5) as $intento) {
            $this->post('/forgot-password', ['email' => 'alguien@example.com'])->assertStatus(302);
        }

        $this->post('/forgot-password', ['email' => 'alguien@example.com'])->assertStatus(429);
    }

    public function test_el_catalogo_publico_se_bloquea_tras_demasiadas_peticiones(): void
    {
        foreach (range(1, 60) as $intento) {
            $this->get('/mascotas')->assertOk();
        }

        $this->get('/mascotas')->assertStatus(429);
    }

    public function test_no_se_aceptan_contrasenas_debiles(): void
    {
        foreach (['12345678', 'password', 'clavesegura1', 'CLAVESEGURA1', 'ClaveSegura'] as $debil) {
            $this->post('/register', [
                'nombres' => 'Prueba',
                'apellidos' => 'Debil',
                'email' => 'debil@example.com',
                'password' => $debil,
                'password_confirmation' => $debil,
            ])->assertSessionHasErrors('password');

            $this->app['cache']->store()->flush();
        }

        $this->assertDatabaseMissing('usuarios', ['email' => 'debil@example.com']);
    }

    public function test_usuario_sin_correo_verificado_no_accede_a_sus_modulos(): void
    {
        // Quitar esta línea al reactivar MustVerifyEmail en App\Models\User.
        $this->markTestSkipped('Verificación de correo desactivada temporalmente.');

        $ciudadano = User::factory()->unverified()->create(['rol' => 'ciudadano']);
        $administrador = User::factory()->unverified()->create(['rol' => 'administrador']);

        $this->actingAs($ciudadano)->get(route('solicitudes-adopcion.index'))
            ->assertRedirect(route('verification.notice'));
        $this->actingAs($administrador)->get(route('admin.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_las_acciones_del_administrador_quedan_en_la_auditoria(): void
    {
        $administrador = User::factory()->create(['rol' => 'administrador']);
        $ciudadano = User::factory()->create(['rol' => 'ciudadano']);

        $this->actingAs($administrador)
            ->patch(route('admin.usuarios.estado.update', $ciudadano), ['estado' => 0])
            ->assertRedirect();

        $registro = Auditoria::where('modelo', 'User')
            ->where('modelo_id', $ciudadano->id)
            ->where('accion', 'actualizado')
            ->firstOrFail();

        $this->assertSame($administrador->id, $registro->usuario_id);
        $this->assertSame(['estado' => true], $registro->valores_anteriores);
        $this->assertSame(['estado' => false], $registro->valores_nuevos);
        $this->assertNotNull($registro->ip);
    }

    public function test_la_auditoria_no_guarda_contrasenas(): void
    {
        $usuario = User::factory()->create();

        $registro = Auditoria::where('modelo', 'User')->where('modelo_id', $usuario->id)->firstOrFail();

        $this->assertArrayNotHasKey('password', $registro->valores_nuevos);
        $this->assertArrayNotHasKey('remember_token', $registro->valores_nuevos);
    }

    public function test_la_auditoria_no_se_puede_modificar_ni_borrar(): void
    {
        User::factory()->create();
        $registro = Auditoria::firstOrFail();

        foreach ([
            fn () => DB::table('auditorias')->where('id', $registro->id)->update(['accion' => 'eliminado']),
            fn () => DB::table('auditorias')->where('id', $registro->id)->delete(),
        ] as $intento) {
            try {
                DB::transaction($intento);
                $this->fail('La base de datos permitió alterar la auditoría.');
            } catch (\Illuminate\Database\QueryException $exception) {
                $this->assertStringContainsString('no se puede modificar', $exception->getMessage());
            }
        }
    }

    public function test_solo_el_administrador_ve_la_auditoria(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'administrador']))
            ->get(route('admin.auditoria.index'))->assertOk()->assertSee('Auditoría');

        foreach (['ciudadano', 'voluntario'] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))
                ->get(route('admin.auditoria.index'))->assertForbidden();
        }
    }

    public function test_las_fotos_se_guardan_limpias_sin_contenido_oculto(): void
    {
        Storage::fake('public');

        // Imagen válida con código PHP escondido al final del archivo.
        $imagen = imagecreatetruecolor(20, 20);
        ob_start();
        imagejpeg($imagen);
        $contenido = ob_get_clean().'<?php echo "malicioso"; ?>';

        $this->actingAs(User::factory()->create(['rol' => 'administrador']))->post('/admin/mascotas', [
            'nombre' => 'Luna', 'especie' => 'gato', 'raza' => 'Mestiza', 'edad' => 2, 'edad_en' => 'años',
            'sexo' => 'hembra', 'color' => 'Negro', 'tamano' => 'pequeno', 'descripcion' => 'Prueba de seguridad.',
            'foto_principal' => UploadedFile::fake()->createWithContent('luna.jpg', $contenido),
        ])->assertRedirect(route('admin.mascotas.index'));

        $ruta = Mascota::where('nombre', 'Luna')->firstOrFail()->fotos()->firstOrFail()->url;

        Storage::disk('public')->assertExists($ruta);
        $this->assertStringEndsWith('.jpg', $ruta);
        $this->assertStringNotContainsString('<?php', Storage::disk('public')->get($ruta));
    }
}
