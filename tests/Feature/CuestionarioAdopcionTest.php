<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\SeguimientoAdopcion;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CuestionarioAdopcionDatos as Datos;
use Tests\TestCase;

/**
 * Cuestionario de adopción A2, alertas para el administrador y flujo
 * pendiente → en evaluación → aprobada → entrega concretada, o rechazada con motivo obligatorio.
 */
class CuestionarioAdopcionTest extends TestCase
{
    use RefreshDatabase;

    private const MOTIVO = ['comentarios_admin' => 'Revisión realizada.'];

    // ---------- Formulario y validación ----------

    public function test_ciudadano_ve_el_cuestionario_con_sus_26_preguntas(): void
    {
        $response = $this->actingAs($this->ciudadano())
            ->get(route('solicitudes-adopcion.create', $this->mascota()))
            ->assertOk()
            ->assertSee('Sección 1 de 6')
            ->assertSee('¿Qué tipo de vivienda tiene?')
            ->assertSee('¿Cuánto tiempo permanecería sola la mascota en un día habitual?')
            ->assertSee('Consentimientos obligatorios');

        $html = $response->getContent();
        preg_match_all('/data-num="(\d+)"/', $html, $numeros);
        $this->assertSame(range(1, 26), array_values(array_unique(array_map('intval', $numeros[1]))));
    }

    public function test_cuestionario_completo_crea_una_solicitud_pendiente_y_sin_alertas(): void
    {
        $ciudadano = $this->ciudadano();
        $mascota = $this->mascota();

        $this->actingAs($ciudadano)
            ->post(route('solicitudes-adopcion.store', $mascota), Datos::valido())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('solicitudes-adopcion.index'));

        $solicitud = SolicitudAdopcion::sole();
        $this->assertSame('pendiente', $solicitud->estado);
        $this->assertSame('verde', $solicitud->nivel_alerta);
        $this->assertSame([], $solicitud->alertas);
        $this->assertSame('a2-v3', $solicitud->version_cuestionario);
        $this->assertSame('casa', $solicitud->respuestas['tipo_vivienda']);
        $this->assertTrue($solicitud->respuestas['consentimientos']['no_ceder']);
        // Columnas que ya usaba el sistema
        $this->assertSame('Tarde, Fin de semana', $solicitud->horario_preferido);
        $this->assertSame('Queru Queru, Cochabamba', $solicitud->zona_aproximada);
        $this->assertTrue($solicitud->viven_otras_personas);
        $this->assertFalse($solicitud->tiene_otras_mascotas);
    }

    public function test_preguntas_condicionales_son_obligatorias_solo_cuando_aplican(): void
    {
        $casos = [
            'permiso_propietario' => ['tenencia' => 'alquilada'],
            'cerramiento' => ['permanencia' => 'exterior'],
            'edades_ninos' => ['ninos' => 2],
            'mascotas_actuales' => ['otras_mascotas' => 'si'],
            'que_ocurrio' => ['mascotas_anteriores' => 'entregadas'],
            'responsable_otro' => ['responsable' => 'otra_persona'],
        ];

        foreach ($casos as $campo => $cambios) {
            $this->actingAs($this->ciudadano())
                ->post(route('solicitudes-adopcion.store', $this->mascota()), Datos::valido($cambios))
                ->assertSessionHasErrors($campo);
        }

        $this->assertSame(0, SolicitudAdopcion::count());
    }

    public function test_preguntas_condicionales_que_no_aplican_se_descartan(): void
    {
        // Vivienda propia: aunque el navegador envíe "permiso = no", no se guarda ni genera alerta roja.
        $this->actingAs($this->ciudadano())
            ->post(route('solicitudes-adopcion.store', $this->mascota()), Datos::valido([
                'permiso_propietario' => 'no',
                'cerramiento' => 'no',
                'edades_ninos' => '5 años',
                'que_ocurrio' => 'Texto que no aplica',
            ]))
            ->assertSessionHasNoErrors();

        $solicitud = SolicitudAdopcion::sole();
        $this->assertSame('verde', $solicitud->nivel_alerta);
        foreach (['permiso_propietario', 'cerramiento', 'edades_ninos', 'que_ocurrio'] as $campo) {
            $this->assertArrayNotHasKey($campo, $solicitud->respuestas);
        }
    }

    public function test_campos_otro_son_opcionales(): void
    {
        $this->actingAs($this->ciudadano())
            ->post(route('solicitudes-adopcion.store', $this->mascota()), Datos::valido(['motivo' => 'otro', 'si_no_puede' => 'otro']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, SolicitudAdopcion::count());
    }

    public function test_los_cuatro_consentimientos_son_obligatorios(): void
    {
        foreach (['acepta_visita_previa', 'acepta_seguimiento', 'no_ceder', 'declara_informacion_veraz'] as $campo) {
            $datos = Datos::valido();
            unset($datos[$campo]);

            $this->actingAs($this->ciudadano())
                ->post(route('solicitudes-adopcion.store', $this->mascota()), $datos)
                ->assertSessionHasErrors($campo);
        }
    }

    public function test_rechaza_opciones_que_no_estan_en_el_catalogo(): void
    {
        $this->actingAs($this->ciudadano())
            ->post(route('solicitudes-adopcion.store', $this->mascota()), Datos::valido([
                'tipo_vivienda' => 'castillo',
                'horarios_visita' => ['madrugada'],
                'adultos' => 0,
            ]))
            ->assertSessionHasErrors(['tipo_vivienda', 'horarios_visita.0', 'adultos']);
    }

    // ---------- Alertas ----------

    public function test_alertas_rojas_marcan_la_solicitud_sin_rechazarla(): void
    {
        $solicitud = $this->enviar([
            'tenencia' => 'alquilada', 'permiso_propietario' => 'no',
            'acuerdo_hogar' => 'no',
            'compromiso_vacunas' => 'no',
        ]);

        $this->assertSame('rojo', $solicitud->nivel_alerta);
        $this->assertSame('pendiente', $solicitud->estado);
        $this->assertSame([3, 9, 19], array_column($solicitud->alertas, 'pregunta'));
        $this->assertSame(['rojo'], array_values(array_unique(array_column($solicitud->alertas, 'nivel'))));
    }

    public function test_cada_regla_amarilla_genera_su_alerta_con_el_numero_de_pregunta(): void
    {
        $solicitud = $this->enviar([
            'tenencia' => 'familiar', 'permiso_propietario' => 'no_se',
            'permanencia' => 'dentro_fuera', 'cerramiento' => 'no_seguro',
            'acuerdo_hogar' => 'no_hablado',
            'otras_mascotas' => 'si',
            'mascotas_actuales' => ['cantidad' => 2, 'especies' => ['perros', 'gatos'], 'vacunas' => 'algunas', 'esterilizadas' => 'ninguna'],
            'mascotas_anteriores' => 'perdidas', 'que_ocurrio' => 'Se escapó por la puerta.',
            'tiempo_sola' => 'mas_8',
            'si_no_puede' => 'por_su_cuenta',
        ]);

        $this->assertSame('amarillo', $solicitud->nivel_alerta);
        $this->assertSame([3, 5, 9, 11, 12, 17, 20], array_column($solicitud->alertas, 'pregunta'));
    }

    public function test_respuestas_sin_alerta_no_generan_alertas(): void
    {
        $solicitud = $this->enviar([
            'motivo' => 'otro', 'motivo_otro' => 'Para mi hija',
            'tiempo_sola' => '6_8',
            'apoyo' => 'no',
            'otras_mascotas' => 'si',
            'mascotas_actuales' => ['cantidad' => 1, 'especies' => ['gatos'], 'vacunas' => 'todas', 'esterilizadas' => 'ninguna'],
        ]);

        $this->assertSame('verde', $solicitud->nivel_alerta);
        $this->assertSame([], $solicitud->alertas);
    }

    public function test_el_ciudadano_nunca_ve_las_alertas(): void
    {
        $ciudadano = $this->ciudadano();
        $this->actingAs($ciudadano)->post(route('solicitudes-adopcion.store', $this->mascota()), Datos::valido(['acuerdo_hogar' => 'no']));
        $solicitud = SolicitudAdopcion::sole();

        $this->actingAs($ciudadano)
            ->get(route('solicitudes-adopcion.show', $solicitud))
            ->assertOk()
            ->assertSee('Tus respuestas')
            ->assertDontSee('Condición incompatible')
            ->assertDontSee('Requiere revisión')
            ->assertDontSee('No todos los miembros del hogar están de acuerdo.');
    }

    // ---------- Ayuda al administrador ----------

    public function test_el_administrador_ve_alertas_y_respuestas_en_el_detalle(): void
    {
        $solicitud = $this->enviar(['tenencia' => 'alquilada', 'permiso_propietario' => 'no']);

        $this->actingAs($this->administrador())
            ->get(route('admin.solicitudes-adopcion.show', $solicitud))
            ->assertOk()
            ->assertSee('Alertas para el administrador')
            ->assertSee('Pregunta 3')
            ->assertSee('El propietario o responsable no permite mascotas.')
            ->assertSee('Respuestas del cuestionario')
            ->assertSee('Alquilada')
            ->assertSee('Historial de la solicitud')
            ->assertSee('Solicitud recibida');
    }

    public function test_la_lista_filtra_por_nivel_de_alerta(): void
    {
        $this->enviar(['acuerdo_hogar' => 'no'], 'Roja');
        $this->enviar([], 'Verde');

        $this->actingAs($this->administrador())
            ->get(route('admin.solicitudes-adopcion.index', ['nivel_alerta' => 'rojo']))
            ->assertOk()
            ->assertSee('Roja')
            ->assertDontSee('Verde</td>', false);
    }

    public function test_el_panel_principal_cuenta_solicitudes_por_nivel(): void
    {
        $this->enviar(['acuerdo_hogar' => 'no']);
        $this->enviar(['tiempo_sola' => 'mas_8']);
        $this->enviar(['tiempo_sola' => 'mas_8']);
        $this->enviar([]);

        $this->actingAs($this->administrador())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Solicitudes de adopción por revisar')
            ->assertViewHas('indicadores', fn (array $i) => $i['solicitudesRojas'] === 1
                && $i['solicitudesAmarillas'] === 2
                && $i['solicitudesVerdes'] === 1);
    }

    // ---------- Flujo de decisión ----------

    public function test_flujo_completo_pendiente_evaluacion_aprobacion_y_entrega(): void
    {
        $admin = $this->administrador();
        $solicitud = $this->enviar([]);
        $mascota = $solicitud->mascota;

        // Desde "Pendiente" no se puede aprobar directamente.
        $this->actingAs($admin)->patch(route('admin.solicitudes-adopcion.approve', $solicitud), self::MOTIVO)->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('admin.solicitudes-adopcion.evaluate', $solicitud), ['comentarios_admin' => 'Coordinar visita el sábado.'])
            ->assertRedirect(route('admin.solicitudes-adopcion.show', $solicitud));
        $this->assertSame('en_espera', $solicitud->refresh()->estado);
        $this->assertSame('disponible', $mascota->refresh()->estado);

        // «Solicitar información» ya no existe: solo guardaba una nota interna que el ciudadano nunca veía.
        $this->assertFalse(Route::has('admin.solicitudes-adopcion.request-information'));

        $this->actingAs($admin)
            ->patch(route('admin.solicitudes-adopcion.approve', $solicitud), [])
            ->assertRedirect();
        $this->assertSame('aprobada', $solicitud->refresh()->estado);
        // Aprobar no requiere entrevista ni visita previa: esas columnas ya no existen (2026_10_24).
        foreach (['entrevista_fecha', 'entrevista_resultado', 'visita_previa_fecha', 'fecha_entrega_acordada', 'permitir_otra_adopcion'] as $columna) {
            $this->assertFalse(Schema::hasColumn('solicitudes_adopcion', $columna), $columna);
        }

        $this->actingAs($admin)
            ->patch(route('admin.solicitudes-adopcion.deliver', $solicitud), [
                'fecha_entrega' => now()->toDateString(),
                'compromiso_firmado' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('aceptada', $solicitud->refresh()->estado);
        $this->assertSame('adoptada', $mascota->refresh()->estado);
        $seguimiento = SeguimientoAdopcion::where('solicitud_id', $solicitud->id)->sole();
        $this->assertSame('Tarde, Fin de semana', $seguimiento->horario_preferido);

        // El historial muestra cada paso con su motivo.
        $this->actingAs($admin)
            ->get(route('admin.solicitudes-adopcion.show', $solicitud))
            ->assertSee('Recibida → En evaluación')
            ->assertSee('Coordinar visita el sábado.')
            ->assertSee('En evaluación → Reservada')
            ->assertSee('Reservada → Adopción concretada')
            ->assertSee('La entrega quedó registrada y la mascota fue marcada como adoptada.');
    }

    public function test_rechazar_exige_motivo(): void
    {
        $admin = $this->administrador();
        $solicitud = $this->enviar([]);

        $this->actingAs($admin)
            ->patch(route('admin.solicitudes-adopcion.reject', $solicitud), ['comentarios_admin' => '  '])
            ->assertSessionHasErrors('comentarios_admin');

        $this->assertSame('pendiente', $solicitud->refresh()->estado);
        $this->assertNull($solicitud->comentarios_admin);
    }

    public function test_se_puede_rechazar_desde_pendiente_o_desde_evaluacion(): void
    {
        $admin = $this->administrador();
        $desdePendiente = $this->enviar([]);
        $desdeEvaluacion = $this->enviar([]);
        $desdeEvaluacion->update(['estado' => 'en_espera']);

        foreach ([$desdePendiente, $desdeEvaluacion] as $solicitud) {
            $this->actingAs($admin)->patch(route('admin.solicitudes-adopcion.reject', $solicitud), self::MOTIVO)->assertRedirect();
            $this->assertSame('rechazada', $solicitud->refresh()->estado);
            $this->assertSame('disponible', $solicitud->mascota->refresh()->estado);
        }
    }

    public function test_solo_se_evalua_una_solicitud_pendiente(): void
    {
        $admin = $this->administrador();
        $solicitud = $this->enviar([]);
        $solicitud->update(['estado' => 'en_espera']);

        $this->actingAs($admin)->patch(route('admin.solicitudes-adopcion.evaluate', $solicitud), self::MOTIVO)->assertForbidden();
    }

    public function test_ciudadano_y_voluntario_no_pueden_evaluar_ni_rechazar(): void
    {
        $solicitud = $this->enviar([]);

        foreach (['ciudadano', 'voluntario'] as $rol) {
            $actor = User::factory()->create(['rol' => $rol]);
            $this->actingAs($actor)->patch(route('admin.solicitudes-adopcion.evaluate', $solicitud), self::MOTIVO)->assertForbidden();
            $this->actingAs($actor)->patch(route('admin.solicitudes-adopcion.reject', $solicitud), self::MOTIVO)->assertForbidden();
        }

        $this->assertSame('pendiente', $solicitud->refresh()->estado);
    }

    public function test_el_ciudadano_ve_en_evaluacion_con_nombre_claro(): void
    {
        $ciudadano = $this->ciudadano();
        $this->actingAs($ciudadano)->post(route('solicitudes-adopcion.store', $this->mascota()), Datos::valido());
        $solicitud = SolicitudAdopcion::sole();
        $solicitud->update(['estado' => 'en_espera']);

        $this->actingAs($ciudadano)
            ->get(route('solicitudes-adopcion.index'))
            ->assertSee('En evaluación');
        $this->actingAs($ciudadano)
            ->get(route('solicitudes-adopcion.show', $solicitud))
            ->assertSee('Tu solicitud está en evaluación. La fundación se pondrá en contacto contigo para continuar el proceso.');
    }

    // ---------- Ayudantes ----------

    private function enviar(array $cambios, ?string $nombreMascota = null): SolicitudAdopcion
    {
        $ciudadano = $this->ciudadano();
        $mascota = $this->mascota($nombreMascota);

        $this->actingAs($ciudadano)
            ->post(route('solicitudes-adopcion.store', $mascota), Datos::valido($cambios))
            ->assertSessionHasNoErrors();

        return SolicitudAdopcion::where('solicitante_id', $ciudadano->id)->sole();
    }

    private function ciudadano(): User
    {
        return User::factory()->create(['rol' => 'ciudadano']);
    }

    private function administrador(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    private function mascota(?string $nombre = null): Mascota
    {
        $admin = $this->administrador();

        return DB::transaction(function () use ($admin, $nombre): Mascota {
            $mascota = Mascota::create([
                'nombre' => $nombre ?? fake()->unique()->firstName(),
                'especie' => 'perro', 'raza' => 'Mestiza', 'edad' => 2, 'edad_en' => 'años',
                'sexo' => 'hembra', 'color' => 'Canela', 'tamano' => 'mediano',
                'descripcion' => 'Mascota de prueba', 'estado' => 'disponible', 'registrado_por' => $admin->id,
            ]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => "mascotas/{$mascota->id}/principal.jpg", 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
    }
}
