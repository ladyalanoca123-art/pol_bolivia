<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\PostulacionVoluntariado;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CompletaFlujoAdopcion;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\Support\CuestionarioVoluntariadoDatos;
use Tests\TestCase;

/**
 * Aviso "Para adoptar necesitas una cuenta" al pulsar ENVIAR SOLICITUD sin cuenta de ciudadano.
 */
class AvisoSolicitudAdopcionTest extends TestCase
{
    use CompletaFlujoAdopcion;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    public function test_el_visitante_ve_el_aviso_en_lugar_del_enlace_directo(): void
    {
        $mascota = $this->mascota('Canela');

        foreach ([route('mascotas.catalogo'), route('home'), route('mascotas.public.show', $mascota)] as $pagina) {
            $html = $this->get($pagina)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<dialog'), "Un solo aviso en {$pagina}");
            $this->assertStringContainsString('Para adoptar a <span x-text="nombre"></span> necesitas una cuenta', $html);
            $this->assertStringContainsString('href="'.route('mascotas.solicitar-acceso', [$mascota, 'inicio']).'"', $html);
            $this->assertStringContainsString('abrir-aviso-adopcion', $html);
            $this->assertStringNotContainsString('href="'.route('solicitudes-adopcion.create', $mascota).'"', $html);
        }
    }

    public function test_el_aviso_incluye_imagen_whatsapp_y_redes_oficiales(): void
    {
        $this->mascota('Canela');
        $html = $this->get(route('mascotas.catalogo'))->getContent();

        $this->assertStringContainsString('images/pol-bolivia/modal-adopcion.jpg', $html);
        $this->assertStringContainsString('aria-labelledby="aviso-adopcion-titulo"', $html);
        $this->assertStringContainsString('aria-label="Cerrar aviso"', $html);
        foreach ([
            'https://wa.me/59175239523',
            'https://www.facebook.com/POLpaginaoficial',
            'https://www.instagram.com/polgrupo/',
            'https://vm.tiktok.com/ZS9ULCPRfQA85-FoBQC/',
        ] as $enlace) {
            $this->assertMatchesRegularExpression('#<a href="'.preg_quote($enlace, '#').'" target="_blank" rel="noopener noreferrer"#', $html);
        }
        $this->assertStringNotContainsString('style=', $html);
    }

    public function test_el_ciudadano_con_sesion_mantiene_el_enlace_directo_y_no_ve_el_aviso(): void
    {
        $mascota = $this->mascota('Canela');
        $ciudadano = User::factory()->create(['rol' => 'ciudadano']);

        foreach ([route('mascotas.catalogo'), route('mascotas.public.show', $mascota)] as $pagina) {
            $html = $this->actingAs($ciudadano)->get($pagina)->assertOk()->getContent();

            $this->assertStringContainsString('href="'.route('solicitudes-adopcion.create', $mascota).'"', $html);
            $this->assertStringNotContainsString('<dialog', $html);
            $this->assertStringNotContainsString('abrir-aviso-adopcion', $html);
        }
        $this->actingAs($ciudadano)->get(route('solicitudes-adopcion.create', $mascota))->assertOk();
    }

    public function test_el_voluntario_ve_un_aviso_explicativo_y_el_servidor_sigue_bloqueando(): void
    {
        $mascota = $this->mascota('Canela');
        $voluntario = $this->voluntario();

        $html = $this->actingAs($voluntario)->get(route('mascotas.public.show', $mascota))->assertOk()->getContent();

        $this->assertStringContainsString('Las solicitudes se envían desde una cuenta de ciudadano', $html);
        $this->assertStringContainsString('Tu cuenta es de voluntario', $html);
        $this->assertMatchesRegularExpression('/<button type="button" aria-haspopup="dialog"[^>]*abrir-aviso-adopcion/s', $html);
        $this->assertStringNotContainsString('href="'.route('solicitudes-adopcion.create', $mascota).'"', $html);
        $this->assertStringNotContainsString('Crear cuenta', $html);

        // La restricción del servidor no cambia.
        $this->actingAs($voluntario)->get(route('solicitudes-adopcion.create', $mascota))->assertForbidden();
    }

    public function test_el_administrador_ve_el_aviso_generico(): void
    {
        $mascota = $this->mascota('Canela');

        $this->actingAs($this->admin)->get(route('mascotas.public.show', $mascota))
            ->assertOk()
            ->assertSee('Las solicitudes se envían desde una cuenta de ciudadano')
            ->assertSee('Tu cuenta no es de ciudadano')
            ->assertDontSee('Tu cuenta es de voluntario');
    }

    public function test_iniciar_sesion_desde_el_aviso_vuelve_a_la_solicitud_de_la_mascota(): void
    {
        $mascota = $this->mascota('Canela');
        $ciudadano = User::factory()->create(['rol' => 'ciudadano', 'password' => Hash::make('ClaveSegura123')]);

        $this->get(route('mascotas.solicitar-acceso', [$mascota, 'inicio']))->assertRedirect(route('login'));
        $this->assertSame(route('solicitudes-adopcion.create', $mascota), session('url.intended'));

        $this->post('/login', ['email' => $ciudadano->email, 'password' => 'ClaveSegura123'])
            ->assertRedirect(route('solicitudes-adopcion.create', $mascota));
    }

    public function test_crear_cuenta_desde_el_aviso_vuelve_a_la_solicitud_de_la_mascota(): void
    {
        $mascota = $this->mascota('Canela');

        $this->get(route('mascotas.solicitar-acceso', [$mascota, 'registro']))->assertRedirect(route('register'));

        $this->post('/register', [
            'nombres' => 'Ana', 'apellidos' => 'Quispe', 'email' => 'ana.aviso@example.test', 'telefono' => '',
            'password' => 'ClaveSegura123', 'password_confirmation' => 'ClaveSegura123',
            'acepta_terminos' => '1', 'acepta_privacidad' => '1',
        ])->assertRedirect(route('solicitudes-adopcion.create', $mascota));

        // Petición nueva: el usuario se vuelve a leer de la base (rol y estado los asigna PostgreSQL).
        app('auth')->forgetGuards();
        $this->get(route('solicitudes-adopcion.create', $mascota))->assertOk();
    }

    public function test_la_ruta_solo_acepta_registro_o_inicio_y_mascotas_disponibles(): void
    {
        $disponible = $this->mascota('Canela');
        // Una mascota solo pasa a "adoptada" al concretarse la entrega (regla de PostgreSQL).
        $adoptada = $this->mascota('Max');
        $solicitud = SolicitudAdopcion::create(['solicitante_id' => User::factory()->create(['rol' => 'ciudadano'])->id, 'mascota_id' => $adoptada->id, ...CuestionarioAdopcionDatos::registro(), 'estado' => 'pendiente']);
        $this->completarAdopcionPorRutas($this->admin, $solicitud);
        $this->assertSame('adoptada', $adoptada->refresh()->estado);

        // El flujo por rutas deja autenticado al administrador; esta comprobación corresponde a un visitante.
        $this->app['auth']->guard()->logout();
        $this->assertGuest();

        $this->get('/mascotas/'.$disponible->id.'/solicitar/otra')->assertNotFound();
        $this->get('/mascotas/'.$disponible->id.'/solicitar/https:%2F%2Fevil.example')->assertNotFound();
        $this->get(route('mascotas.solicitar-acceso', [$adoptada, 'inicio']))->assertNotFound();
        $this->get('/mascotas/999999/solicitar/inicio')->assertNotFound();
        $this->assertNull(session('url.intended'));
    }

    public function test_el_destino_siempre_es_interno_aunque_se_envien_parametros(): void
    {
        $mascota = $this->mascota('Canela');

        $this->get(route('mascotas.solicitar-acceso', [$mascota, 'inicio']).'?redirect=https://evil.example&url.intended=https://evil.example')
            ->assertRedirect(route('login'));

        $this->assertSame(route('solicitudes-adopcion.create', $mascota), session('url.intended'));
        $this->assertStringStartsWith(config('app.url'), session('url.intended'));
    }

    public function test_un_usuario_con_sesion_no_usa_la_ruta_del_aviso(): void
    {
        $mascota = $this->mascota('Canela');

        $this->actingAs(User::factory()->create(['rol' => 'ciudadano']))
            ->get(route('mascotas.solicitar-acceso', [$mascota, 'inicio']))
            ->assertRedirect(route('dashboard'));
    }

    private function mascota(string $nombre): Mascota
    {
        return DB::transaction(function () use ($nombre): Mascota {
            $mascota = Mascota::create(['nombre' => $nombre, 'especie' => 'perro', 'raza' => 'Mestiza', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'mascotas/principal.jpg', 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
    }

    private function voluntario(): User
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano']);
        $postulacion = PostulacionVoluntariado::create([...CuestionarioVoluntariadoDatos::registro(), 'usuario_id' => $ciudadano->id, 'estado' => 'pendiente']);
        $this->actingAs($this->admin)->patch(route('admin.postulaciones-voluntariado.approve', $postulacion), ['comentarios_admin' => 'Aprobada']);

        return $ciudadano->fresh();
    }
}
