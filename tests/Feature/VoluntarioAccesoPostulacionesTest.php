<?php

namespace Tests\Feature;

use App\Models\PostulacionVoluntariado;
use App\Models\User;
use App\Models\Voluntario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CuestionarioVoluntariadoDatos as Datos;
use Tests\TestCase;

/**
 * Acceso de ciudadanos y voluntarios a sus propias postulaciones de voluntariado.
 */
class VoluntarioAccesoPostulacionesTest extends TestCase
{
    use RefreshDatabase;

    private const AVISO_PENDIENTE = 'Tu postulación está en revisión. Podrás enviar otra cuando la fundación responda.';

    private const MENSAJE_NEUTRAL = 'Esta postulación fue aprobada anteriormente. Para conocer tu situación actual, contacta con la fundación.';

    public function test_voluntario_ve_su_listado_y_el_detalle_de_su_postulacion_aprobada(): void
    {
        [$voluntario, $postulacion] = $this->voluntarioAprobado();

        $listado = $this->actingAs($voluntario)->get(route('postulaciones-voluntariado.index'))->assertOk();
        $listado->assertSee('Postulación del '.$postulacion->created_at->format('d/m/Y'));
        $listado->assertSee(route('postulaciones-voluntariado.show', $postulacion), false);
        $listado->assertSee('Mi postulación');
        $listado->assertDontSee('Nueva postulación');
        $listado->assertDontSee('Postularme como voluntario');
        $listado->assertDontSee(self::AVISO_PENDIENTE);
        $listado->assertDontSee(route('postulaciones-voluntariado.create'), false);

        $this->actingAs($voluntario)->get(route('postulaciones-voluntariado.show', $postulacion))
            ->assertOk()
            ->assertSee('Estado: Aprobada')
            ->assertSee('¡Bienvenido/a al equipo! La fundación te contactará para coordinar tu incorporación.')
            ->assertDontSee(self::MENSAJE_NEUTRAL)
            ->assertSee('Tus respuestas');
    }

    public function test_voluntario_no_puede_ver_el_detalle_de_otro_usuario(): void
    {
        [$voluntario] = $this->voluntarioAprobado();
        $ajena = $this->postulacion($this->ciudadano());

        $this->actingAs($voluntario)->get(route('postulaciones-voluntariado.show', $ajena))->assertNotFound();
    }

    public function test_voluntario_no_puede_abrir_el_formulario_ni_enviar_una_postulacion(): void
    {
        [$voluntario] = $this->voluntarioAprobado();
        $antes = PostulacionVoluntariado::count();

        $this->actingAs($voluntario)->get(route('postulaciones-voluntariado.create'))->assertForbidden();
        $this->actingAs($voluntario)->post(route('postulaciones-voluntariado.store'), Datos::valido())->assertForbidden();

        $this->assertSame($antes, PostulacionVoluntariado::count());
    }

    public function test_ciudadano_sin_postulacion_pendiente_ve_el_boton_para_postularse(): void
    {
        $ciudadano = $this->ciudadano();

        $this->actingAs($ciudadano)->get(route('postulaciones-voluntariado.index'))
            ->assertOk()
            ->assertSee('Postularme como voluntario')
            ->assertSee(route('postulaciones-voluntariado.create'), false);

        // Con una postulación rechazada puede volver a postularse.
        $this->rechazar($this->postulacion($ciudadano));

        $this->actingAs($ciudadano)->get(route('postulaciones-voluntariado.index'))
            ->assertOk()
            ->assertSee('Nueva postulación')
            ->assertDontSee(self::AVISO_PENDIENTE);
    }

    public function test_ciudadano_con_postulacion_pendiente_ve_el_aviso_y_no_el_boton(): void
    {
        $ciudadano = $this->ciudadano();
        $this->postulacion($ciudadano);

        $this->actingAs($ciudadano)->get(route('postulaciones-voluntariado.index'))
            ->assertOk()
            ->assertSee(self::AVISO_PENDIENTE)
            ->assertDontSee('Nueva postulación')
            ->assertDontSee('Postularme como voluntario');
    }

    public function test_el_aviso_de_pendiente_se_mantiene_en_una_pagina_sin_la_postulacion_pendiente(): void
    {
        $ciudadano = $this->ciudadano();

        // 15 postulaciones rechazadas antiguas y una pendiente más reciente:
        // la pendiente queda en la página 1 y la página 2 solo tiene rechazadas.
        for ($i = 0; $i < 15; $i++) {
            $p = $this->rechazar($this->postulacion($ciudadano));
            $p->forceFill(['created_at' => now()->subDays(30 - $i)])->save();
        }
        $pendiente = $this->postulacion($ciudadano);

        $pagina2 = $this->actingAs($ciudadano)->get(route('postulaciones-voluntariado.index', ['page' => 2]))->assertOk();
        $pagina2->assertDontSee(route('postulaciones-voluntariado.show', $pendiente), false);
        $pagina2->assertDontSee('>Pendiente</span>', false);
        $pagina2->assertSee(self::AVISO_PENDIENTE);
        $pagina2->assertDontSee('Nueva postulación');
    }

    public function test_ciudadano_que_ve_una_postulacion_aprobada_recibe_el_mensaje_neutral(): void
    {
        [$voluntario, $postulacion] = $this->voluntarioAprobado();

        // Al desactivar el perfil, PostgreSQL devuelve el rol a ciudadano.
        $this->actingAs($this->administrador())
            ->patch(route('admin.voluntarios.deactivate', Voluntario::where('usuario_id', $voluntario->id)->sole()))
            ->assertRedirect();
        $ciudadano = $voluntario->fresh();
        $this->assertSame('ciudadano', $ciudadano->rol);

        $this->actingAs($ciudadano)->get(route('postulaciones-voluntariado.show', $postulacion))
            ->assertOk()
            ->assertSee('Estado: Aprobada')
            ->assertSee(self::MENSAJE_NEUTRAL)
            ->assertDontSee('¡Bienvenido/a al equipo!');
    }

    public function test_panel_administrativo_aprobar_y_rechazar_siguen_funcionando(): void
    {
        $admin = $this->administrador();
        $aAprobar = $this->postulacion($this->ciudadano());
        $aRechazar = $this->postulacion($this->ciudadano());

        $this->actingAs($admin)->get(route('admin.postulaciones-voluntariado.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.postulaciones-voluntariado.show', $aAprobar))
            ->assertOk()
            ->assertSee('>Aprobar</button>', false)
            ->assertSee('>Rechazar</button>', false);

        $this->actingAs($admin)->patch(route('admin.postulaciones-voluntariado.approve', $aAprobar), ['comentarios_admin' => 'Aprobada'])->assertRedirect();
        $this->assertSame('aprobada', $aAprobar->fresh()->estado);
        $this->assertSame('voluntario', $aAprobar->usuario()->first()->rol);
        $this->assertTrue(Voluntario::where('usuario_id', $aAprobar->usuario_id)->exists());

        $this->actingAs($admin)->patch(route('admin.postulaciones-voluntariado.reject', $aRechazar), ['comentarios_admin' => 'Falta experiencia'])->assertRedirect();
        $this->assertSame('rechazada', $aRechazar->fresh()->estado);
        $this->assertSame('ciudadano', $aRechazar->usuario()->first()->rol);
    }

    /** @return array{0: User, 1: PostulacionVoluntariado} */
    private function voluntarioAprobado(): array
    {
        $ciudadano = $this->ciudadano();
        $postulacion = $this->postulacion($ciudadano);
        $this->actingAs($this->administrador())
            ->patch(route('admin.postulaciones-voluntariado.approve', $postulacion), ['comentarios_admin' => 'Aprobada'])
            ->assertRedirect();

        $voluntario = $ciudadano->fresh();
        $this->assertSame('voluntario', $voluntario->rol);

        return [$voluntario, $postulacion->fresh()];
    }

    private function postulacion(User $ciudadano): PostulacionVoluntariado
    {
        return PostulacionVoluntariado::create([...Datos::registro(), 'usuario_id' => $ciudadano->id, 'estado' => 'pendiente']);
    }

    private function rechazar(PostulacionVoluntariado $p): PostulacionVoluntariado
    {
        $p->forceFill(['estado' => 'rechazada', 'revisado_por' => $this->administrador()->id, 'revisado_at' => now()])->save();

        return $p;
    }

    private function ciudadano(): User
    {
        return User::factory()->create(['rol' => 'ciudadano']);
    }

    private function administrador(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }
}
