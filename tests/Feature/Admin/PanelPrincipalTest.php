<?php

namespace Tests\Feature\Admin;

use App\Models\FotoMascota;
use App\Models\Incidencia;
use App\Models\Mascota;
use App\Models\PostulacionVoluntariado;
use App\Models\SeguimientoAdopcion;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use App\Models\VisitaSeguimiento;
use App\Services\PanelAdministrador;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\Support\CuestionarioVoluntariadoDatos;
use Tests\TestCase;

/**
 * Servicio de datos del panel principal del administrador (App\Services\PanelAdministrador).
 * Los datos se crean con los modelos y respetan los triggers y restricciones de PostgreSQL.
 */
class PanelPrincipalTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $ahora;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('pol_bolivia_test', DB::connection()->getDatabaseName());

        $this->ahora = CarbonImmutable::parse('2026-10-14 08:30:00', 'America/La_Paz');
        $this->travelTo($this->ahora);
        $this->admin = User::factory()->create(['rol' => 'administrador', 'nombres' => 'Lady Maribel']);
    }

    // ---------- Encabezado ----------

    public function test_el_saludo_respeta_las_franjas_horarias(): void
    {
        $saludo = fn (string $hora) => PanelAdministrador::saludo(CarbonImmutable::parse("2026-10-14 {$hora}", 'America/La_Paz'));

        $this->assertSame('Buenas noches', $saludo('04:59:59'));
        $this->assertSame('Buenos días', $saludo('05:00:00'));
        $this->assertSame('Buenos días', $saludo('11:59:59'));
        $this->assertSame('Buenas tardes', $saludo('12:00:00'));
        $this->assertSame('Buenas tardes', $saludo('18:59:59'));
        $this->assertSame('Buenas noches', $saludo('19:00:00'));
        $this->assertSame('Buenas noches', $saludo('00:00:00'));
    }

    public function test_el_encabezado_usa_el_primer_nombre_y_la_fecha_en_espanol_de_bolivia(): void
    {
        // 08:30 en La Paz son las 12:30 UTC: el saludo debe seguir la hora de Bolivia.
        $encabezado = $this->panel()['encabezado'];

        $this->assertSame('Buenos días', $encabezado['saludo']);
        $this->assertSame('Lady', $encabezado['nombre']);
        $this->assertSame('miércoles, 14 de octubre de 2026', $encabezado['fecha']);
    }

    // ---------- Indicadores ----------

    public function test_la_variacion_semanal_distingue_aumento_disminucion_igualdad_y_nuevo(): void
    {
        $this->assertSame(['tipo' => 'aumento', 'diferencia' => 2], PanelAdministrador::variacion(3, 1));
        $this->assertSame(['tipo' => 'disminucion', 'diferencia' => 2], PanelAdministrador::variacion(1, 3));
        $this->assertSame(['tipo' => 'igual', 'diferencia' => 0], PanelAdministrador::variacion(2, 2));
        $this->assertSame(['tipo' => 'igual', 'diferencia' => 0], PanelAdministrador::variacion(0, 0));
        $this->assertSame(['tipo' => 'nuevo', 'diferencia' => 2], PanelAdministrador::variacion(2, 0));
        $this->assertSame(['tipo' => 'disminucion', 'diferencia' => 1], PanelAdministrador::variacion(0, 1));
    }

    public function test_los_indicadores_cuentan_la_semana_actual_la_anterior_y_los_estados_abiertos(): void
    {
        // Semana actual: desde hace 7 días exactos hasta ahora.
        $this->solicitudEn($this->ahora->subDay());
        $this->solicitudEn($this->ahora->subDays(7)->addMinute());
        $evaluacion = $this->solicitudEn($this->ahora->subDays(3));
        $evaluacion->update(['estado' => 'en_espera']);
        // Semana anterior.
        $this->solicitudEn($this->ahora->subDays(7)->subMinute());
        // Fuera de ambos periodos.
        $this->solicitudEn($this->ahora->subDays(14)->subMinute());

        // Seguimientos e incidencias (sus solicitudes, de hace 30 días, no cuentan como nuevas).
        $this->travelTo($this->ahora->subDays(30));
        [$activo, $voluntario] = $this->seguimientoActivo();
        $this->seguimientoSinAsignar();
        $this->travelTo($this->ahora);
        $this->incidencia($activo, $voluntario, 'pendiente');
        $this->incidencia($activo, $voluntario, 'en_proceso');
        $this->incidencia($activo, $voluntario, 'atendida');

        $indicadores = $this->panel()['indicadores'];

        $this->assertSame(3, $indicadores['solicitudesNuevas']);
        $this->assertSame(1, $indicadores['solicitudesSemanaAnterior']);
        $this->assertSame(['tipo' => 'aumento', 'diferencia' => 2], $indicadores['variacion']);
        $this->assertSame(1, $indicadores['solicitudesEnEvaluacion']);
        $this->assertSame(1, $indicadores['seguimientosActivos']);
        $this->assertSame(2, $indicadores['incidenciasAbiertas']);
    }

    public function test_sin_solicitudes_anteriores_la_variacion_es_nuevo(): void
    {
        $this->solicitudEn($this->ahora->subHour());

        $this->assertSame(['tipo' => 'nuevo', 'diferencia' => 1], $this->panel()['indicadores']['variacion']);
    }

    // ---------- Avisos ----------

    public function test_los_avisos_respetan_sus_umbrales_y_usan_singular(): void
    {
        $this->solicitudEn($this->ahora->subDays(7)->subMinute());
        $this->solicitudEn($this->ahora->subDays(7)->addMinute());
        $this->solicitudEn($this->ahora->subDays(20))->update(['estado' => 'en_espera']);

        $this->postulacionEn($this->ahora->subDays(7)->subMinute());
        $this->postulacionEn($this->ahora->subDays(7)->addMinute());

        $this->fechaDeCreacion($this->seguimientoSinAsignar(), $this->ahora->subDays(3)->subMinute());
        $this->fechaDeCreacion($this->seguimientoSinAsignar(), $this->ahora->subDays(3)->addMinute());

        [$activo, $voluntario] = $this->seguimientoActivo();
        $this->visita($activo, $voluntario, $this->ahora->subDay());
        $this->visita($activo, $voluntario, $this->ahora, 'programada');
        [$otro, $otroVoluntario] = $this->seguimientoActivo();
        $this->visita($otro, $otroVoluntario, $this->ahora->subDays(2), 'realizada');
        $this->visita($otro, $otroVoluntario, $this->ahora->subDays(2), 'no_asistio');

        $avisos = $this->panel()['avisos'];

        $this->assertSame(['mascotas_sin_fotos', 'solicitudes_sin_respuesta', 'seguimientos_sin_asignar', 'visitas_vencidas', 'postulaciones_sin_revisar'], array_keys($avisos));
        $this->assertSame(1, $avisos['solicitudes_sin_respuesta']['total']);
        $this->assertSame('1 solicitud lleva más de 7 días sin respuesta.', $avisos['solicitudes_sin_respuesta']['texto']);
        $this->assertSame(1, $avisos['postulaciones_sin_revisar']['total']);
        $this->assertSame('1 postulación lleva más de 7 días sin revisión.', $avisos['postulaciones_sin_revisar']['texto']);
        $this->assertSame(1, $avisos['seguimientos_sin_asignar']['total']);
        $this->assertSame('1 seguimiento lleva más de 3 días sin voluntario asignado.', $avisos['seguimientos_sin_asignar']['texto']);
        $this->assertSame(1, $avisos['visitas_vencidas']['total']);
        $this->assertSame('1 visita programada ya pasó su fecha sin registrarse.', $avisos['visitas_vencidas']['texto']);
        // Los avisos sin resultados se conservan con total 0.
        $this->assertSame(0, $avisos['mascotas_sin_fotos']['total']);
        $this->assertSame('0 mascotas disponibles no tienen foto y no lucen en el catálogo.', $avisos['mascotas_sin_fotos']['texto']);

        $this->assertSame(['estado' => 'pendiente'], $avisos['solicitudes_sin_respuesta']['parametros']);
        foreach ($avisos as $aviso) {
            $this->assertNotEmpty(route($aviso['ruta'], $aviso['parametros']));
        }
    }

    public function test_los_avisos_usan_plural(): void
    {
        $this->solicitudEn($this->ahora->subDays(8));
        $this->solicitudEn($this->ahora->subDays(9));

        $this->assertSame('2 solicitudes llevan más de 7 días sin respuesta.', $this->panel()['avisos']['solicitudes_sin_respuesta']['texto']);
    }

    public function test_el_aviso_de_mascotas_sin_fotos_detecta_las_que_no_tienen_ninguna_foto(): void
    {
        // El trigger diferido trigger_validar_minimo_fotos solo valida al confirmar (COMMIT); la
        // transacción de la prueba nunca confirma, lo que permite comprobar este control de seguridad.
        $sinFoto = $this->mascota(conFoto: false);
        $soloVideo = $this->mascota(conFoto: false);
        FotoMascota::create(['mascota_id' => $soloVideo->id, 'tipo' => 'video', 'url' => 'mascotas/v.mp4', 'es_principal' => false, 'orden' => 1]);
        $this->mascota();

        $aviso = $this->panel()['avisos']['mascotas_sin_fotos'];

        $this->assertSame(2, $aviso['total']);
        $this->assertSame('2 mascotas disponibles no tienen foto y no lucen en el catálogo.', $aviso['texto']);
        $this->assertNotNull($sinFoto);
    }

    // ---------- Bandeja ----------

    public function test_las_solicitudes_pendientes_se_ordenan_por_alerta_y_luego_por_antiguedad(): void
    {
        // nivel_alerta es NOT NULL en el esquema: no pueden existir solicitudes sin nivel. El servicio
        // igualmente las enviaría al final (rama else del orden) en lugar de fallar.
        $esperado = [
            $this->solicitudEn($this->ahora->subDays(5), ['nivel_alerta' => 'rojo'])->id,
            $this->solicitudEn($this->ahora->subDays(1), ['nivel_alerta' => 'rojo'])->id,
            $this->solicitudEn($this->ahora->subDays(3), ['nivel_alerta' => 'amarillo'])->id,
            $this->solicitudEn($this->ahora->subDays(10), ['nivel_alerta' => 'verde'])->id,
            $this->solicitudEn($this->ahora->subDays(9), ['nivel_alerta' => 'verde'])->id,
        ];
        $this->solicitudEn($this->ahora->subDays(2), ['nivel_alerta' => 'verde']);
        $this->solicitudEn($this->ahora->subDays(1))->update(['estado' => 'en_espera']);

        $lista = $this->panel()['bandeja']['solicitudesPendientes'];

        $this->assertSame($esperado, $lista['items']->pluck('id')->all());
        $this->assertSame(6, $lista['total']);
        $this->assertTrue($lista['items']->first()->relationLoaded('mascota'));
        $this->assertTrue($lista['items']->first()->relationLoaded('solicitante'));
    }

    public function test_las_incidencias_abiertas_muestran_primero_las_pendientes_y_luego_las_mas_antiguas(): void
    {
        [$seguimiento, $voluntario] = $this->seguimientoActivo();
        $this->travelTo($this->ahora->subDays(5));
        $enProcesoAntigua = $this->incidencia($seguimiento, $voluntario, 'en_proceso');
        $this->travelTo($this->ahora->subDays(4));
        $pendienteAntigua = $this->incidencia($seguimiento, $voluntario, 'pendiente');
        $this->travelTo($this->ahora->subDays(1));
        $pendienteReciente = $this->incidencia($seguimiento, $voluntario, 'pendiente');
        $this->incidencia($seguimiento, $voluntario, 'atendida');
        $this->travelTo($this->ahora);

        $lista = $this->panel()['bandeja']['incidenciasAbiertas'];

        $this->assertSame([$pendienteAntigua->id, $pendienteReciente->id, $enProcesoAntigua->id], $lista['items']->pluck('id')->all());
        $this->assertSame(3, $lista['total']);
        $this->assertSame($seguimiento->solicitud->mascota->nombre, $lista['items']->first()->seguimiento->solicitud->mascota->nombre);
    }

    public function test_la_bandeja_limita_cada_lista_a_cinco_y_conserva_el_total_real(): void
    {
        foreach (range(1, 6) as $dias) {
            $this->solicitudEn($this->ahora->subDays($dias))->update(['estado' => 'en_espera']);
            $this->postulacionEn($this->ahora->subDays($dias));
        }
        $seguimientos = [];
        foreach (range(1, 6) as $dias) {
            $seguimientos[$dias] = $this->seguimientoSinAsignar();
            $this->fechaDeCreacion($seguimientos[$dias], $this->ahora->subDays($dias));
        }

        $bandeja = $this->panel()['bandeja'];

        foreach (['solicitudesEnEvaluacion', 'postulacionesPendientes', 'seguimientosSinAsignar'] as $lista) {
            $this->assertCount(5, $bandeja[$lista]['items'], $lista);
            $this->assertSame(6, $bandeja[$lista]['total'], $lista);
        }
        // Los más antiguos primero.
        $this->assertSame($seguimientos[6]->id, $bandeja['seguimientosSinAsignar']['items']->first()->id);
        $this->assertTrue($bandeja['seguimientosSinAsignar']['items']->first()->solicitud->relationLoaded('mascota'));
        $this->assertTrue($bandeja['postulacionesPendientes']['items']->first()->relationLoaded('usuario'));
    }

    // ---------- Agenda ----------

    public function test_la_agenda_incluye_de_hoy_a_hoy_mas_siete_en_orden_y_con_limite(): void
    {
        $fechas = [];
        foreach ([[0, 7], [1, 8], [2, -1], [3, 6], [4, 5]] as [$a, $b]) {
            [$seguimiento, $voluntario] = $this->seguimientoActivo();
            $fechas[] = $this->visita($seguimiento, $voluntario, $this->ahora->addDays($a));
            $fechas[] = $this->visita($seguimiento, $voluntario, $this->ahora->addDays($b));
        }
        [$otro, $otroVoluntario] = $this->seguimientoActivo();
        $this->visita($otro, $otroVoluntario, $this->ahora, 'realizada');
        $this->visita($otro, $otroVoluntario, $this->ahora->addDay(), 'no_asistio');

        $agenda = $this->panel()['agenda'];

        // En el rango [hoy, hoy+7]: días 0,1,2,3,4,5,6,7 (el 8 y el −1 quedan fuera).
        $this->assertSame(8, $agenda['total']);
        $this->assertSame(['2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17', '2026-10-18'], $agenda['items']->map(fn ($v) => $v->fecha_programada->toDateString())->all());
        $this->assertSame('2026-10-14', $agenda['desde']);
        $this->assertSame('2026-10-21', $agenda['hasta']);
        $this->assertTrue($agenda['items']->every(fn ($v) => $v->estado === 'programada'));
        $primera = $agenda['items']->first();
        $this->assertTrue($primera->relationLoaded('voluntario'));
        $this->assertTrue($primera->seguimiento->solicitud->relationLoaded('mascota'));
        $this->assertTrue($primera->seguimiento->solicitud->relationLoaded('solicitante'));
    }

    // ---------- Actividad reciente ----------

    public function test_la_actividad_reciente_filtra_modelos_ordena_y_no_expone_datos_personales(): void
    {
        $this->travelTo($this->ahora->subHours(3));
        $mascotaDelSistema = $this->mascota();          // sin sesión: «El sistema»
        $this->travelTo($this->ahora->subHours(2));
        $this->actingAs($this->admin);
        $solicitud = $this->solicitudEn($this->ahora->subHours(2));
        $this->travelTo($this->ahora->subHour());
        $solicitud->update(['estado' => 'en_espera']);
        User::factory()->create(['email' => 'no.debe.aparecer@example.test']); // modelo User: excluido
        $this->travelTo($this->ahora);

        $actividad = $this->panel()['actividad'];

        $this->assertLessThanOrEqual(8, $actividad->count());
        $this->assertTrue($actividad->every(fn ($a) => in_array($a['modelo'], PanelAdministrador::MODELOS_ACTIVIDAD, true)));
        $this->assertSame($actividad->pluck('fecha')->sortDesc()->values()->all(), $actividad->pluck('fecha')->all());

        $cambio = $actividad->first();
        $this->assertSame(['SolicitudAdopcion', 'actualizado', $solicitud->id, 'pendiente', 'en_espera', false, 'Lady Maribel '.$this->admin->apellidos],
            [$cambio['modelo'], $cambio['accion'], $cambio['modelo_id'], $cambio['estado_anterior'], $cambio['estado_nuevo'], $cambio['es_sistema'], $cambio['actor']]);
        $this->assertSame('America/La_Paz', $cambio['fecha']->getTimezone()->getName());
        $this->assertSame('07:30', $cambio['fecha']->format('H:i'));

        $sistema = $actividad->firstWhere('modelo_id', $mascotaDelSistema->id);
        $this->assertTrue($sistema['es_sistema']);
        $this->assertSame('El sistema', $sistema['actor']);

        // Ninguna entrada lleva los valores completos, la IP ni el user_agent.
        $claves = ['id', 'accion', 'modelo', 'modelo_id', 'es_sistema', 'actor', 'estado_anterior', 'estado_nuevo', 'fecha'];
        foreach ($actividad as $entrada) {
            $this->assertSame($claves, array_keys($entrada));
        }
        $this->assertStringNotContainsString('no.debe.aparecer', json_encode($actividad));
    }

    public function test_la_actividad_sin_usuario_se_atribuye_al_sistema(): void
    {
        // Sin sesión (p. ej. un comando de consola), RegistraAuditoria guarda usuario_id nulo.
        $mascota = $this->mascota();

        $entrada = $this->panel()['actividad']->firstWhere('modelo_id', $mascota->id);

        $this->assertNull(DB::table('auditorias')->where('modelo', 'Mascota')->where('modelo_id', $mascota->id)->value('usuario_id'));
        $this->assertTrue($entrada['es_sistema']);
        $this->assertSame('El sistema', $entrada['actor']);
    }

    public function test_la_actividad_de_un_usuario_eliminado_no_se_atribuye_al_sistema_ni_inventa_su_identidad(): void
    {
        // auditorias.usuario_id no tiene clave foránea: al eliminar la cuenta, la auditoría conserva
        // el identificador, pero ya no hay usuario con el que mostrar su nombre.
        $persona = User::factory()->create(['rol' => 'voluntario', 'nombres' => 'Nombre Eliminado', 'apellidos' => 'Apellido Eliminado']);
        $this->actingAs($persona);
        $mascota = $this->mascota();
        $persona->delete();
        $this->assertDatabaseMissing('usuarios', ['id' => $persona->id]);

        $entrada = $this->panel()['actividad']->firstWhere('modelo_id', $mascota->id);

        $this->assertSame($persona->id, (int) DB::table('auditorias')->where('modelo', 'Mascota')->where('modelo_id', $mascota->id)->value('usuario_id'));
        $this->assertFalse($entrada['es_sistema']);
        $this->assertSame('Un usuario eliminado', $entrada['actor']);
        $this->assertStringNotContainsString('Eliminado', json_encode($this->panel()['actividad']));
    }

    public function test_la_actividad_reciente_muestra_como_maximo_ocho_entradas(): void
    {
        foreach (range(1, 10) as $minutos) {
            $this->travelTo($this->ahora->subMinutes(20 - $minutos));
            $this->mascota(conFoto: false);
        }
        $this->travelTo($this->ahora);

        $this->assertCount(8, $this->panel()['actividad']);
    }

    // ---------- Integración y rendimiento ----------

    public function test_el_panel_entrega_los_datos_y_no_tiene_consultas_n_mas_1(): void
    {
        // Con sesión en ambos escenarios, para que las auditorías tengan usuario en los dos.
        $this->actingAs($this->admin);
        $this->escenarioCompleto(1);
        [$peticionConPocos, $servicioConPocos] = [$this->consultasDelPanel(), $this->consultasDelServicio()];

        $this->escenarioCompleto(4);
        [$peticionConMas, $servicioConMas] = [$this->consultasDelPanel(), $this->consultasDelServicio()];

        // Comportamiento, no un número fijo: más registros en cada lista no añaden consultas.
        $this->assertSame($servicioConPocos, $servicioConMas);
        $this->assertSame($peticionConPocos, $peticionConMas);
    }

    public function test_los_datos_del_panel_tienen_cargadas_las_relaciones_que_usara_la_vista(): void
    {
        $this->actingAs($this->admin);
        $this->escenarioCompleto(2);

        // Con la carga perezosa prohibida, acceder a una relación no cargada con with() lanza
        // LazyLoadingViolationException: así se detecta un N+1 sin depender de un número de consultas.
        Model::preventLazyLoading();
        try {
            $panel = $this->panel();
            $b = $panel['bandeja'];

            foreach ([...$b['solicitudesPendientes']['items'], ...$b['solicitudesEnEvaluacion']['items']] as $s) {
                $this->assertNotNull($s->mascota->nombre.$s->solicitante->nombres);
            }
            foreach ($b['seguimientosSinAsignar']['items'] as $s) {
                $this->assertNotNull($s->solicitud->mascota->nombre.$s->solicitud->solicitante->nombres);
            }
            foreach ($b['incidenciasAbiertas']['items'] as $i) {
                $this->assertNotNull($i->seguimiento->solicitud->mascota->nombre.$i->reportadoPor->nombres);
            }
            foreach ($b['postulacionesPendientes']['items'] as $p) {
                $this->assertNotNull($p->usuario->nombres);
            }
            foreach ($panel['agenda']['items'] as $v) {
                $this->assertNotNull($v->seguimiento->solicitud->mascota->nombre.$v->seguimiento->solicitud->solicitante->nombres.$v->voluntario->nombres);
            }
            $this->assertNotEmpty($panel['actividad']);
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_el_panel_sigue_entregando_los_indicadores_de_la_vista_actual(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('indicadores', fn (array $i) => array_key_exists('solicitudesRojas', $i) && array_key_exists('mascotas', $i))
            ->assertViewHas('panel', fn (array $p) => array_keys($p) === ['encabezado', 'indicadores', 'avisos', 'bandeja', 'agenda', 'actividad']);
    }

    // ---------- Datos de prueba ----------

    private function panel(): array
    {
        return app(PanelAdministrador::class)->datos($this->admin);
    }

    private function consultasDelPanel(): int
    {
        $this->actingAs($this->admin);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('admin.dashboard'))->assertOk();
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $consultas;
    }

    private function consultasDelServicio(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->panel();
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $consultas;
    }

    private function escenarioCompleto(int $cantidad): void
    {
        foreach (range(1, $cantidad) as $i) {
            $this->solicitudEn($this->ahora->subDays(8 + $i));
            $this->solicitudEn($this->ahora->subDays($i))->update(['estado' => 'en_espera']);
            $this->postulacionEn($this->ahora->subDays(8 + $i));
            $this->fechaDeCreacion($this->seguimientoSinAsignar(), $this->ahora->subDays(4 + $i));
            [$seguimiento, $voluntario] = $this->seguimientoActivo();
            $this->incidencia($seguimiento, $voluntario, 'pendiente');
            $this->visita($seguimiento, $voluntario, $this->ahora->addDays($i));
            $this->visita($seguimiento, $voluntario, $this->ahora->subDays($i));
        }
    }

    private function mascota(bool $conFoto = true): Mascota
    {
        return DB::transaction(function () use ($conFoto) {
            $mascota = Mascota::create(['nombre' => fake()->unique()->firstName(), 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id]);
            if ($conFoto) {
                FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'mascotas/panel.jpg', 'es_principal' => true, 'orden' => 1]);
            }

            return $mascota;
        });
    }

    private function solicitudEn(CarbonImmutable $fecha, array $cambios = []): SolicitudAdopcion
    {
        $actual = CarbonImmutable::now();
        $this->travelTo($fecha);
        $solicitud = SolicitudAdopcion::create([
            'solicitante_id' => User::factory()->create(['rol' => 'ciudadano'])->id,
            'mascota_id' => $this->mascota()->id,
            'estado' => 'pendiente',
            ...CuestionarioAdopcionDatos::registro($cambios),
        ]);
        $this->travelTo($actual);

        return $solicitud;
    }

    private function postulacionEn(CarbonImmutable $fecha): PostulacionVoluntariado
    {
        $actual = CarbonImmutable::now();
        $this->travelTo($fecha);
        $postulacion = PostulacionVoluntariado::create([
            'usuario_id' => User::factory()->create(['rol' => 'ciudadano'])->id,
            'estado' => 'pendiente',
            ...CuestionarioVoluntariadoDatos::registro(),
        ]);
        $this->travelTo($actual);

        return $postulacion;
    }

    /** Al aceptar una solicitud, PostgreSQL crea su seguimiento en estado sin_asignar. */
    private function seguimientoSinAsignar(): SeguimientoAdopcion
    {
        $solicitud = $this->solicitudEn(CarbonImmutable::now()->subMonth());
        $solicitud->update(['estado' => 'aceptada']);

        return $solicitud->seguimiento()->firstOrFail();
    }

    /** @return array{0: SeguimientoAdopcion, 1: User} */
    private function seguimientoActivo(): array
    {
        $seguimiento = $this->seguimientoSinAsignar();
        $voluntario = User::factory()->create(['rol' => 'voluntario']);
        $seguimiento->update(['voluntario_id' => $voluntario->id, 'estado' => 'activo', 'fecha_inicio' => now()]);

        return [$seguimiento, $voluntario];
    }

    /**
     * El seguimiento lo crea un trigger con CURRENT_TIMESTAMP (hora real de la transacción), que no
     * sigue travelTo(): su antigüedad se fija explícitamente en la base de pruebas.
     */
    private function fechaDeCreacion(SeguimientoAdopcion $seguimiento, CarbonImmutable $fecha): void
    {
        DB::table('seguimientos_adopcion')->where('id', $seguimiento->id)->update(['created_at' => $fecha]);
    }

    private function visita(SeguimientoAdopcion $seguimiento, User $voluntario, CarbonImmutable $fecha, string $estado = 'programada'): VisitaSeguimiento
    {
        return VisitaSeguimiento::create([
            'seguimiento_id' => $seguimiento->id,
            'voluntario_id' => $voluntario->id,
            'fecha_programada' => $fecha->toDateString(),
            'estado' => $estado,
            'fecha_realizada' => $estado === 'realizada' ? $fecha : null,
        ]);
    }

    private function incidencia(SeguimientoAdopcion $seguimiento, User $voluntario, string $estado): Incidencia
    {
        $incidencia = Incidencia::create([
            'seguimiento_id' => $seguimiento->id,
            'reportado_por' => $voluntario->id,
            'tipo' => 'salud',
            'descripcion' => 'Revisión necesaria',
            'estado' => 'pendiente',
        ]);
        match ($estado) {
            'en_proceso' => $incidencia->update(['estado' => 'en_proceso']),
            'atendida' => $incidencia->update(['estado' => 'atendida', 'comentarios_resolucion' => 'Resuelta', 'atendida_por' => $this->admin->id, 'atendida_at' => now()]),
            default => null,
        };

        return $incidencia;
    }
}
