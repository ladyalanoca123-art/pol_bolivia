<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\PostulacionVoluntariado;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use App\Models\VisitaSeguimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CompletaFlujoAdopcion;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\Support\CuestionarioVoluntariadoDatos;
use Tests\TestCase;

/**
 * «Mi espacio»: inicio del ciudadano y del voluntario. Solo muestra datos de la propia persona.
 */
class MiEspacioTest extends TestCase
{
    use CompletaFlujoAdopcion;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    public function test_el_ciudadano_ve_sus_solicitudes_y_mascotas_que_esperan_hogar(): void
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano', 'nombres' => 'Rosa Elena', 'telefono' => '71234567']);
        $solicitada = $this->mascota('Firulais');
        $this->mascota('Pelusa');
        SolicitudAdopcion::create(['solicitante_id' => $ciudadano->id, 'mascota_id' => $solicitada->id, 'estado' => 'pendiente', ...CuestionarioAdopcionDatos::registro()]);

        $this->actingAs($ciudadano)->get(route('inicio'))->assertOk()
            ->assertSee(', Rosa')
            ->assertSee('Mis solicitudes de adopción')
            ->assertSee('Firulais')->assertSee('Recibida')
            ->assertSee('Solicitudes en trámite')
            ->assertSee('Esperan un hogar')->assertSee('Pelusa')
            ->assertSee('¿Quieres ayudar más?')
            ->assertSee('La fundación revisará tu solicitud y te escribirá por WhatsApp.')
            ->assertDontSee('Falta tu teléfono')
            ->assertSee('aria-current="page"', false);
    }

    public function test_el_ciudadano_sin_solicitudes_ni_telefono_recibe_invitaciones_claras(): void
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano', 'telefono' => null]);

        $this->actingAs($ciudadano)->get(route('inicio'))->assertOk()
            ->assertSee('Todavía no tienes solicitudes de adopción.')
            ->assertSee('Ver mascotas disponibles')
            ->assertSee('Falta tu teléfono (WhatsApp).')
            ->assertSee('Sin postulación');
    }

    public function test_no_muestra_solicitudes_de_otras_personas(): void
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano']);
        $otra = User::factory()->create(['rol' => 'ciudadano']);
        SolicitudAdopcion::create(['solicitante_id' => $otra->id, 'mascota_id' => $this->mascota('Ajena')->id, 'estado' => 'pendiente', ...CuestionarioAdopcionDatos::registro()]);

        $this->actingAs($ciudadano)->get(route('inicio'))->assertOk()
            ->assertSee('Todavía no tienes solicitudes de adopción.');
        $this->assertSame(0, $this->actingAs($ciudadano)->get(route('inicio'))->viewData('solicitudes')->count());
    }

    public function test_el_voluntario_ve_sus_seguimientos_y_proximas_visitas(): void
    {
        $voluntario = User::factory()->create(['rol' => 'voluntario', 'nombres' => 'Pedro']);
        $ciudadano = User::factory()->create(['rol' => 'ciudadano', 'apellidos' => 'Condori']);
        $solicitud = SolicitudAdopcion::create(['solicitante_id' => $ciudadano->id, 'mascota_id' => $this->mascota('Max')->id, 'estado' => 'pendiente', ...CuestionarioAdopcionDatos::registro()]);
        $this->completarAdopcionPorRutas($this->admin, $solicitud);
        $seguimiento = $solicitud->seguimiento()->firstOrFail();
        $seguimiento->update(['voluntario_id' => $voluntario->id, 'estado' => 'activo', 'fecha_inicio' => now()]);
        VisitaSeguimiento::create(['seguimiento_id' => $seguimiento->id, 'voluntario_id' => $voluntario->id, 'fecha_programada' => now()->addDays(5)->toDateString(), 'estado' => 'programada']);

        $this->actingAs($voluntario)->get(route('inicio'))->assertOk()
            ->assertSee(', Pedro')
            ->assertSee('Mis seguimientos activos')
            ->assertSee('Max')->assertSee('Familia Condori')->assertSee('0 de 2')
            ->assertSee('Próximas visitas')->assertSee('Programada')
            ->assertSee('Mis seguimientos')
            ->assertDontSee('¿Quieres ayudar más?');
    }

    public function test_una_visita_atrasada_se_destaca(): void
    {
        $voluntario = User::factory()->create(['rol' => 'voluntario']);
        $ciudadano = User::factory()->create(['rol' => 'ciudadano']);
        $solicitud = SolicitudAdopcion::create(['solicitante_id' => $ciudadano->id, 'mascota_id' => $this->mascota('Luna')->id, 'estado' => 'pendiente', ...CuestionarioAdopcionDatos::registro()]);
        $this->completarAdopcionPorRutas($this->admin, $solicitud);
        $seguimiento = $solicitud->seguimiento()->firstOrFail();
        $seguimiento->update(['voluntario_id' => $voluntario->id, 'estado' => 'activo', 'fecha_inicio' => now()]);
        // Programada en el pasado (directo en la base: el formulario no acepta fechas pasadas).
        DB::table('visitas_seguimiento')->insert(['seguimiento_id' => $seguimiento->id, 'voluntario_id' => $voluntario->id, 'fecha_programada' => now()->subDays(3)->toDateString(), 'estado' => 'programada', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($voluntario)->get(route('inicio'))->assertOk()
            ->assertSee('Atrasada: regístrala o reprográmala');
    }

    public function test_solo_ciudadano_y_voluntario_tienen_mi_espacio(): void
    {
        $this->get(route('inicio'))->assertRedirect(route('login'));
        $this->actingAs($this->admin)->get(route('inicio'))->assertForbidden();
    }

    public function test_la_postulacion_pendiente_se_ve_en_el_resumen(): void
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano']);
        PostulacionVoluntariado::create(['usuario_id' => $ciudadano->id, 'estado' => 'pendiente', ...CuestionarioVoluntariadoDatos::registro()]);

        $this->actingAs($ciudadano)->get(route('inicio'))->assertOk()
            ->assertSee('En revisión')
            ->assertSee('Ver mi postulación')
            ->assertDontSee('¿Quieres ayudar más?');
    }

    private function mascota(string $nombre): Mascota
    {
        return DB::transaction(function () use ($nombre): Mascota {
            $mascota = Mascota::create(['nombre' => $nombre, 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'macho', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'test.jpg', 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
    }
}
