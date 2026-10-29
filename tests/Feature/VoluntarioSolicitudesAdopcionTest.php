<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\PostulacionVoluntariado;
use App\Models\SeguimientoAdopcion;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CompletaFlujoAdopcion;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\Support\CuestionarioVoluntariadoDatos;
use Tests\TestCase;

/**
 * Un ciudadano con una solicitud de adopción abierta que es aprobado como voluntario
 * conserva su solicitud: puede consultarla y el proceso del administrador sigue igual.
 */
class VoluntarioSolicitudesAdopcionTest extends TestCase
{
    use CompletaFlujoAdopcion;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    public function test_al_ser_aprobado_como_voluntario_sigue_viendo_su_solicitud_pendiente(): void
    {
        $ciudadano = $this->ciudadano();
        $solicitud = $this->solicitud($ciudadano);
        $voluntario = $this->aprobarComoVoluntario($ciudadano);

        $this->actingAs($voluntario)->get(route('solicitudes-adopcion.index'))
            ->assertOk()
            ->assertSee('Mis solicitudes')
            ->assertSee(route('solicitudes-adopcion.show', $solicitud), false);

        $this->actingAs($voluntario)->get(route('solicitudes-adopcion.show', $solicitud))
            ->assertOk()
            ->assertSee($solicitud->mascota->nombre);
    }

    public function test_el_voluntario_sigue_el_avance_de_su_solicitud(): void
    {
        $ciudadano = $this->ciudadano();
        $solicitud = $this->solicitud($ciudadano);
        $voluntario = $this->aprobarComoVoluntario($ciudadano);

        $this->actingAs($this->admin)->patch(route('admin.solicitudes-adopcion.evaluate', $solicitud), ['comentarios_admin' => 'Coordinar visita.'])->assertRedirect();
        $this->actingAs($voluntario)->get(route('solicitudes-adopcion.show', $solicitud))
            ->assertOk()
            ->assertSee('Tu solicitud está en evaluación. La fundación se pondrá en contacto contigo para continuar el proceso.')
            ->assertDontSee('Coordinar visita.'); // los comentarios internos siguen sin mostrarse

        $this->completarAdopcionPorRutas($this->admin, $solicitud);
        $this->actingAs($voluntario)->get(route('solicitudes-adopcion.show', $solicitud))
            ->assertOk()
            ->assertSee('La adopción fue concretada y la entrega quedó registrada.', false);
    }

    public function test_el_voluntario_no_puede_ver_solicitudes_ajenas(): void
    {
        $voluntario = $this->aprobarComoVoluntario($this->ciudadano());
        $ajena = $this->solicitud($this->ciudadano());

        $this->actingAs($voluntario)->get(route('solicitudes-adopcion.show', $ajena))->assertNotFound();
    }

    public function test_el_voluntario_no_puede_crear_solicitudes_nuevas(): void
    {
        $voluntario = $this->aprobarComoVoluntario($this->ciudadano());
        $mascota = $this->mascota();
        $antes = SolicitudAdopcion::count();

        $this->actingAs($voluntario)->get(route('solicitudes-adopcion.create', $mascota))->assertForbidden();
        $this->actingAs($voluntario)->post(route('solicitudes-adopcion.store', $mascota), CuestionarioAdopcionDatos::valido())->assertForbidden();

        $this->assertSame($antes, SolicitudAdopcion::count());
    }

    public function test_el_voluntario_sin_solicitudes_no_ve_invitacion_a_adoptar(): void
    {
        $voluntario = $this->aprobarComoVoluntario($this->ciudadano());

        $this->actingAs($voluntario)->get(route('solicitudes-adopcion.index'))
            ->assertOk()
            ->assertSee('No tienes solicitudes de adopción registradas')
            ->assertDontSee('Ver mascotas disponibles')
            ->assertDontSee('Todavía no tienes solicitudes de adopción');
    }

    public function test_el_ciudadano_conserva_su_estado_vacio(): void
    {
        $this->actingAs($this->ciudadano())->get(route('solicitudes-adopcion.index'))
            ->assertOk()
            ->assertSee('Todavía no tienes solicitudes de adopción')
            ->assertSee('Ver mascotas disponibles')
            ->assertDontSee('No tienes solicitudes de adopción registradas');
    }

    public function test_el_administrador_puede_aceptar_la_solicitud_de_un_voluntario(): void
    {
        $ciudadano = $this->ciudadano();
        $solicitud = $this->solicitud($ciudadano);
        $this->aprobarComoVoluntario($ciudadano);

        $this->actingAs($this->admin)->patch(route('admin.solicitudes-adopcion.evaluate', $solicitud), ['comentarios_admin' => 'Coordinar visita.'])->assertRedirect();
        $this->completarAdopcionPorRutas($this->admin, $solicitud);

        $this->assertSame('aceptada', $solicitud->refresh()->estado);
        $this->assertSame('adoptada', $solicitud->mascota->refresh()->estado);
        $this->assertTrue(SeguimientoAdopcion::where('solicitud_id', $solicitud->id)->exists());
    }

    public function test_el_adoptante_voluntario_no_puede_ser_asignado_a_su_propio_seguimiento(): void
    {
        $ciudadano = $this->ciudadano();
        $solicitud = $this->solicitud($ciudadano);
        $adoptante = $this->aprobarComoVoluntario($ciudadano);
        $otroVoluntario = $this->aprobarComoVoluntario($this->ciudadano());

        $this->actingAs($this->admin)->patch(route('admin.solicitudes-adopcion.evaluate', $solicitud), ['comentarios_admin' => 'Coordinar visita.']);
        $this->completarAdopcionPorRutas($this->admin, $solicitud);
        $seguimiento = SeguimientoAdopcion::where('solicitud_id', $solicitud->id)->sole();

        // El adoptante no aparece entre las opciones; el otro voluntario sí.
        $this->actingAs($this->admin)->get(route('admin.seguimientos.show', $seguimiento))
            ->assertOk()
            ->assertDontSee('<option value="'.$adoptante->id.'"', false)
            ->assertSee('<option value="'.$otroVoluntario->id.'"', false);

        // Aunque se envíe a mano, se rechaza.
        $this->actingAs($this->admin)
            ->from(route('admin.seguimientos.show', $seguimiento))
            ->patch(route('admin.seguimientos.assign', $seguimiento), ['voluntario_id' => $adoptante->id])
            ->assertSessionHasErrors(['voluntario_id' => 'El adoptante no puede hacer el seguimiento de su propia adopción.']);
        $this->assertNull($seguimiento->refresh()->voluntario_id);

        // Otro voluntario sí puede ser asignado.
        $this->actingAs($this->admin)
            ->patch(route('admin.seguimientos.assign', $seguimiento), ['voluntario_id' => $otroVoluntario->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($otroVoluntario->id, $seguimiento->refresh()->voluntario_id);
        $this->assertSame('activo', $seguimiento->estado);
    }

    private function ciudadano(): User
    {
        return User::factory()->create(['rol' => 'ciudadano']);
    }

    private function aprobarComoVoluntario(User $ciudadano): User
    {
        $postulacion = PostulacionVoluntariado::create([...CuestionarioVoluntariadoDatos::registro(), 'usuario_id' => $ciudadano->id, 'estado' => 'pendiente']);
        $this->actingAs($this->admin)
            ->patch(route('admin.postulaciones-voluntariado.approve', $postulacion), ['comentarios_admin' => 'Aprobada'])
            ->assertRedirect();

        $voluntario = $ciudadano->fresh();
        $this->assertSame('voluntario', $voluntario->rol);

        return $voluntario;
    }

    private function mascota(): Mascota
    {
        return DB::transaction(function (): Mascota {
            $mascota = Mascota::create(['nombre' => fake()->unique()->firstName(), 'especie' => 'perro', 'raza' => 'Mestiza', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'mascotas/principal.jpg', 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
    }

    private function solicitud(User $ciudadano): SolicitudAdopcion
    {
        return SolicitudAdopcion::create(['solicitante_id' => $ciudadano->id, 'mascota_id' => $this->mascota()->id, ...CuestionarioAdopcionDatos::registro(), 'estado' => 'pendiente']);
    }
}
