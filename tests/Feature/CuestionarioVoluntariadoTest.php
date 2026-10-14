<?php

namespace Tests\Feature;

use App\Models\PostulacionVoluntariado;
use App\Models\User;
use App\Models\Voluntario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CuestionarioVoluntariadoDatos as Datos;
use Tests\TestCase;

/**
 * Cuestionario de postulación de voluntariado V1 (10 preguntas).
 */
class CuestionarioVoluntariadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_ciudadano_ve_el_cuestionario_con_sus_10_preguntas(): void
    {
        $response = $this->actingAs($this->ciudadano())
            ->get(route('postulaciones-voluntariado.create'))
            ->assertOk()
            ->assertSee('Sección 1 de 3')
            ->assertSee('¿Por qué quiere ser voluntario/a?')
            ->assertSee('Visitas de seguimiento')
            ->assertSee('Confirmo que soy mayor de 18 años.');

        preg_match_all('/data-num="(\d+)"/', $response->getContent(), $numeros);
        $this->assertSame(range(1, 10), array_values(array_unique(array_map('intval', $numeros[1]))));
    }

    public function test_cuestionario_completo_crea_una_postulacion_pendiente(): void
    {
        $ciudadano = $this->ciudadano();

        $this->actingAs($ciudadano)
            ->post(route('postulaciones-voluntariado.store'), Datos::valido([
                'dias_disponibles' => ['domingo', 'lunes'],
                'franjas_horarias' => ['noche', 'manana'],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('postulaciones-voluntariado.index'));

        $p = PostulacionVoluntariado::sole();
        $this->assertSame('pendiente', $p->estado);
        $this->assertSame('v1', $p->version_cuestionario);
        $this->assertFalse($p->experiencia_previa);
        $this->assertSame(['paseos', 'ferias_adopcion'], $p->areas_interes);
        $this->assertTrue($p->confirma_mayor_edad);
        // Texto que PostgreSQL copia al perfil del voluntario, ordenado según el catálogo.
        $this->assertSame('Lunes, Domingo · Mañana, Noche', $p->disponibilidad);
    }

    public function test_la_pregunta_3_es_obligatoria_solo_si_tiene_experiencia(): void
    {
        $this->actingAs($this->ciudadano())
            ->post(route('postulaciones-voluntariado.store'), Datos::valido(['experiencia_previa' => 'si']))
            ->assertSessionHasErrors('experiencia_detalle');

        // Sin experiencia: el detalle se descarta aunque el navegador lo envíe.
        $this->actingAs($this->ciudadano())
            ->post(route('postulaciones-voluntariado.store'), Datos::valido(['experiencia_detalle' => 'No aplica']))
            ->assertSessionHasNoErrors();

        $this->assertNull(PostulacionVoluntariado::sole()->experiencia_detalle);
    }

    public function test_habilidades_es_opcional(): void
    {
        $this->actingAs($this->ciudadano())
            ->post(route('postulaciones-voluntariado.store'), Datos::valido(['habilidades' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(PostulacionVoluntariado::sole()->habilidades);
    }

    public function test_campos_obligatorios_y_casillas(): void
    {
        foreach (['motivacion', 'experiencia_previa', 'areas_interes', 'dias_disponibles', 'franjas_horarias', 'zona_preferencia', 'confirma_mayor_edad', 'declara_informacion_veraz'] as $campo) {
            $datos = Datos::valido();
            unset($datos[$campo]);

            $this->actingAs($this->ciudadano())
                ->post(route('postulaciones-voluntariado.store'), $datos)
                ->assertSessionHasErrors($campo);
        }

        $this->assertSame(0, PostulacionVoluntariado::count());
    }

    public function test_rechaza_opciones_fuera_del_catalogo(): void
    {
        $this->actingAs($this->ciudadano())
            ->post(route('postulaciones-voluntariado.store'), Datos::valido([
                'areas_interes' => ['cocina'],
                'dias_disponibles' => ['feriado'],
                'experiencia_previa' => 'quizas',
            ]))
            ->assertSessionHasErrors(['areas_interes.0', 'dias_disponibles.0', 'experiencia_previa']);
    }

    public function test_al_aprobar_el_perfil_del_voluntario_recibe_la_disponibilidad(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $ciudadano = $this->ciudadano();
        $this->actingAs($ciudadano)->post(route('postulaciones-voluntariado.store'), Datos::valido());
        $p = PostulacionVoluntariado::sole();

        $this->actingAs($admin)->patch(route('admin.postulaciones-voluntariado.approve', $p), ['comentarios_admin' => 'Bienvenida'])->assertRedirect();

        $perfil = Voluntario::where('usuario_id', $ciudadano->id)->sole();
        $this->assertSame('Sábado, Domingo · Mañana, Tarde', $perfil->disponibilidad);
        $this->assertSame('Centro', $perfil->zona_preferencia);
        $this->assertSame('Primeros auxilios', $perfil->habilidades);
    }

    public function test_el_administrador_filtra_por_area_y_por_dia(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $this->crear('Rescatista', ['areas_interes' => ['rescate'], 'dias_disponibles' => ['lunes']]);
        $this->crear('Paseadora', ['areas_interes' => ['paseos'], 'dias_disponibles' => ['sabado']]);

        $this->actingAs($admin)->get(route('admin.postulaciones-voluntariado.index', ['area' => 'rescate']))
            ->assertOk()->assertSee('Rescatista')->assertDontSee('Paseadora');
        $this->actingAs($admin)->get(route('admin.postulaciones-voluntariado.index', ['dia' => 'sabado']))
            ->assertOk()->assertSee('Paseadora')->assertDontSee('Rescatista');
        $this->actingAs($admin)->get(route('admin.postulaciones-voluntariado.index', ['area' => 'invalida']))
            ->assertSessionHasErrors('area');
    }

    public function test_el_administrador_y_el_ciudadano_ven_las_respuestas(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $ciudadano = $this->ciudadano();
        $this->actingAs($ciudadano)->post(route('postulaciones-voluntariado.store'), Datos::valido(['experiencia_previa' => 'si', 'experiencia_detalle' => 'Un año en un refugio']));
        $p = PostulacionVoluntariado::sole();

        $this->actingAs($admin)->get(route('admin.postulaciones-voluntariado.show', $p))
            ->assertOk()->assertSee('Respuestas del cuestionario')->assertSee('Un año en un refugio')->assertSee('Ferias de adopción');
        $this->actingAs($ciudadano)->get(route('postulaciones-voluntariado.show', $p))
            ->assertOk()->assertSee('Tus respuestas')->assertSee('Sábado, Domingo');
    }

    private function crear(string $nombres, array $cambios): void
    {
        PostulacionVoluntariado::create([
            ...Datos::registro($cambios),
            'usuario_id' => User::factory()->create(['rol' => 'ciudadano', 'nombres' => $nombres])->id,
            'estado' => 'pendiente',
        ]);
    }

    private function ciudadano(): User
    {
        return User::factory()->create(['rol' => 'ciudadano']);
    }
}
