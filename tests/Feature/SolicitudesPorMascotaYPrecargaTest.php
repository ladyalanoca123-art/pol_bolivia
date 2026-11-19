<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CompletaFlujoAdopcion;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\TestCase;

/**
 * Ayudas para decidir y para el ciudadano:
 *  - El administrador compara las solicitudes en trámite por la misma mascota (verdes primero).
 *  - El cuestionario se completa con las respuestas de la solicitud anterior del ciudadano
 *    (sin los consentimientos, que deben marcarse cada vez).
 */
class SolicitudesPorMascotaYPrecargaTest extends TestCase
{
    use CompletaFlujoAdopcion;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('pol_bolivia_test', DB::connection()->getDatabaseName());
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    // ---------- Administrador: solicitudes por la misma mascota ----------

    public function test_la_ficha_compara_las_solicitudes_por_la_misma_mascota_con_las_verdes_primero(): void
    {
        $bongo = $this->mascota('Bongo');
        $roja = $this->solicitud($bongo, 'Rita Roja', 'rojo', '2026-11-01 10:00:00-04');
        $amarilla = $this->solicitud($bongo, 'Ana Amarilla', 'amarillo', '2026-11-02 10:00:00-04');
        $verde = $this->solicitud($bongo, 'Vera Verde', 'verde', '2026-11-03 10:00:00-04');
        $otraMascota = $this->solicitud($this->mascota('Nina'), 'Olga Otra', 'verde', '2026-11-04 10:00:00-04');
        // Una ya finalizada por la misma mascota no se compara.
        $finalizada = $this->solicitud($bongo, 'Fabio Finalizado', 'verde', '2026-10-01 10:00:00-04');
        $finalizada->update(['estado' => 'rechazada']);

        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.show', $roja))->assertOk()
            ->assertSee('Solicitudes por Bongo (3)')
            ->assertSee('1 verde · 1 amarilla · 1 roja')
            ->assertSeeInOrder(['Vera Verde', 'Recomendada', 'Ir a esta solicitud', 'Ana Amarilla', 'Ver solicitud', 'Rita Roja', 'Estás viendo esta solicitud'], false)
            ->assertSee('1 respuesta')
            ->assertSee(route('admin.solicitudes-adopcion.show', $verde), false)
            ->assertSee(route('admin.solicitudes-adopcion.show', $amarilla), false)
            ->assertDontSee('Olga Otra')
            ->assertDontSee('Fabio Finalizado');

        // Con una sola solicitud en trámite no aparece el recuadro.
        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.show', $otraMascota))->assertOk()
            ->assertDontSee('Solicitudes por Nina');
    }

    public function test_la_lista_indica_cuantas_solicitudes_tiene_cada_mascota(): void
    {
        $bongo = $this->mascota('Bongo');
        foreach (['Uno', 'Dos', 'Tres'] as $i => $nombre) {
            $this->solicitud($bongo, "Persona {$nombre}", 'verde', '2026-11-0'.($i + 1).' 10:00:00-04');
        }
        $this->solicitud($this->mascota('Nina'), 'Persona Sola', 'verde', '2026-11-05 10:00:00-04');

        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.index'))->assertOk()
            ->assertSee('3 solicitudes por Bongo')
            ->assertDontSee('solicitudes por Nina');
    }

    public function test_la_lista_pone_primero_las_mas_aptas_y_en_cada_color_la_mas_antigua(): void
    {
        $bongo = $this->mascota('Bongo');
        $this->solicitud($bongo, 'Rita Roja', 'rojo', '2026-11-01 10:00:00-04');
        $this->solicitud($bongo, 'Vera Nueva', 'verde', '2026-11-05 10:00:00-04');
        $this->solicitud($bongo, 'Ana Amarilla', 'amarillo', '2026-11-02 10:00:00-04');
        $this->solicitud($bongo, 'Vito Antiguo', 'verde', '2026-11-03 10:00:00-04');

        foreach ([[], ['estado' => 'pendiente']] as $filtro) {
            $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.index', $filtro))->assertOk()
                ->assertSeeInOrder(['Vito Antiguo', 'Vera Nueva', 'Ana Amarilla', 'Rita Roja']);
        }
    }

    public function test_el_boton_hoy_muestra_solo_las_recibidas_hoy_en_segundo_lugar(): void
    {
        $bongo = $this->mascota('Bongo');
        $this->solicitud($bongo, 'Hugo Hoy', 'verde', now()->toIso8601String());
        $this->solicitud($bongo, 'Ayer Antes', 'verde', now()->subDays(2)->toIso8601String());

        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.index'))->assertOk()
            ->assertSeeInOrder(['Todas', 'Hoy (1)', 'Recibidas', 'En evaluación']);

        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.index', ['estado' => 'hoy']))->assertOk()
            ->assertSee('Hugo Hoy')
            ->assertDontSee('Ayer Antes');

        DB::table('solicitudes_adopcion')->update(['created_at' => now()->subDays(3)]);
        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.index', ['estado' => 'hoy']))->assertOk()
            ->assertSee('Hoy (0)')
            ->assertSee('Hoy no llegaron solicitudes.');
    }

    public function test_las_adopciones_concretadas_se_muestran_como_tarjetas_con_foto(): void
    {
        $concretada = $this->solicitud($this->mascota('Bongo'), 'Carla Concreta', 'verde', '2026-11-01 10:00:00-04');
        $this->completarAdopcionConTransicionesDeBase($concretada);
        $cerrada = $this->solicitud($this->mascota('Nina'), 'Ramiro Rechazo', 'rojo', '2026-11-02 10:00:00-04');
        $cerrada->update(['estado' => 'rechazada']);

        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.index'))->assertOk()
            ->assertSeeInOrder(['Adopciones concretadas', 'Bongo', 'Adoptada por:', 'Carla Concreta', 'Ver adopción', 'Cerradas sin adopción', 'Ramiro Rechazo'])
            ->assertSee('mascotas/e.jpg', false);

        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.index', ['estado' => 'aceptada']))->assertOk()
            ->assertSee('mascota ya tiene')
            ->assertDontSee('Ramiro Rechazo');
    }

    // ---------- Ciudadano: cuestionario completado con la solicitud anterior ----------

    public function test_el_cuestionario_se_completa_con_la_solicitud_anterior_sin_los_consentimientos(): void
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano']);
        $anterior = SolicitudAdopcion::create([
            ...CuestionarioAdopcionDatos::registro([
                'zona_aproximada' => 'Sacaba',
                'respuestas' => [...CuestionarioAdopcionDatos::registro()['respuestas'], 'tenencia' => 'alquilada', 'permiso_propietario' => 'si', 'adultos' => 3],
            ]),
            'solicitante_id' => $ciudadano->id,
            'mascota_id' => $this->mascota('Kira')->id,
            'estado' => 'pendiente',
        ]);
        $anterior->update(['estado' => 'cancelada', 'motivo_cancelacion' => 'Cancelada por el solicitante']);

        $html = $this->actingAs($ciudadano)->get(route('solicitudes-adopcion.create', $this->mascota('Bongo')))->assertOk()
            ->assertSee('Completamos tus respuestas')
            ->assertSee('por Kira')
            ->getContent();

        $this->assertMatchesRegularExpression('/id="tenencia-alquilada"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/id="permiso_propietario-si"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/name="adultos"[^>]*value="3"/', $html);
        $this->assertStringContainsString('value="Sacaba"', $html);
        $this->assertMatchesRegularExpression('/id="horarios_visita-tarde"[^>]*checked/', $html);
        // Las aceptaciones nunca se completan solas.
        foreach (['acepta_visita_previa', 'acepta_seguimiento', 'no_ceder', 'declara_informacion_veraz'] as $campo) {
            $this->assertDoesNotMatchRegularExpression('/name="'.$campo.'"[^>]*checked/', $html, $campo);
        }
    }

    public function test_sin_solicitud_anterior_el_cuestionario_empieza_vacio(): void
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano']);

        $html = $this->actingAs($ciudadano)->get(route('solicitudes-adopcion.create', $this->mascota('Bongo')))->assertOk()
            ->assertDontSee('Completamos tus respuestas')
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/id="tenencia-[a-z_]+"[^>]*checked/', $html);
    }

    public function test_tras_un_error_se_conservan_las_respuestas_recien_enviadas_y_no_las_anteriores(): void
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano']);
        SolicitudAdopcion::create([
            ...CuestionarioAdopcionDatos::registro(['respuestas' => [...CuestionarioAdopcionDatos::registro()['respuestas'], 'tenencia' => 'alquilada']]),
            'solicitante_id' => $ciudadano->id, 'mascota_id' => $this->mascota('Kira')->id, 'estado' => 'pendiente',
        ]);
        $bongo = $this->mascota('Bongo');

        $this->actingAs($ciudadano)->from(route('solicitudes-adopcion.create', $bongo))
            ->post(route('solicitudes-adopcion.store', $bongo), CuestionarioAdopcionDatos::valido(['tenencia' => 'familiar', 'tipo_vivienda' => null]))
            ->assertRedirect(route('solicitudes-adopcion.create', $bongo));

        $html = $this->actingAs($ciudadano)->get(route('solicitudes-adopcion.create', $bongo))->assertOk()
            ->assertDontSee('Completamos tus respuestas')
            ->getContent();
        $this->assertMatchesRegularExpression('/id="tenencia-familiar"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="tenencia-alquilada"[^>]*checked/', $html);
    }

    // ---------- Datos de prueba ----------

    private function solicitud(Mascota $mascota, string $nombre, string $nivel, string $creada): SolicitudAdopcion
    {
        [$nombres, $apellidos] = explode(' ', $nombre, 2);
        $alertas = match ($nivel) {
            'rojo' => [['nivel' => 'rojo', 'pregunta' => 3, 'mensaje' => 'El propietario no permite mascotas.']],
            'amarillo' => [['nivel' => 'amarillo', 'pregunta' => 17, 'mensaje' => 'Pasaría muchas horas sola.']],
            default => [],
        };
        $solicitud = SolicitudAdopcion::create([
            ...CuestionarioAdopcionDatos::registro(['nivel_alerta' => $nivel, 'alertas' => $alertas]),
            'solicitante_id' => User::factory()->create(['rol' => 'ciudadano', 'nombres' => $nombres, 'apellidos' => $apellidos])->id,
            'mascota_id' => $mascota->id,
            'estado' => 'pendiente',
        ]);
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['created_at' => $creada]);

        return $solicitud->refresh();
    }

    private function mascota(string $nombre): Mascota
    {
        return DB::transaction(function () use ($nombre): Mascota {
            $mascota = Mascota::create(['nombre' => $nombre, 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'macho', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'mascotas/e.jpg', 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
    }
}
