<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\SeguimientoAdopcion;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use App\Models\VisitaSeguimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CompletaFlujoAdopcion;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\TestCase;

/**
 * Flujo simplificado de adopción (sin entrevista ni visita previa registradas) y seguimiento que no se traba:
 * comentario opcional al aprobar, etapa «Reservada», reprogramar, «No asistió», visita adelantada y fotos sin EXIF.
 */
class AjustesFlujoAdopcionSeguimientoTest extends TestCase
{
    use CompletaFlujoAdopcion;
    use RefreshDatabase;

    private const RESPUESTAS_VISITA_1 = [
        'apetito' => 'normal', 'animo' => 'bien', 'adaptacion' => 'buena', 'convivencia' => 'buena', 'sintomas' => ['ninguno'],
    ];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['rol' => 'administrador', 'nombres' => 'Ana', 'apellidos' => 'Quispe']);
    }

    // --- Solicitudes ---

    public function test_aprobar_guarda_el_comentario_opcional_con_quien_aprobo(): void
    {
        $solicitud = $this->solicitudEnEvaluacion();

        $this->actingAs($this->admin)
            ->patch(route('admin.solicitudes-adopcion.approve', $solicitud), ['comentarios_admin' => '  Hablé por WhatsApp: casa con patio.  '])
            ->assertRedirect(route('admin.solicitudes-adopcion.show', $solicitud));

        $solicitud->refresh();
        $this->assertSame('aprobada', $solicitud->estado);
        $this->assertSame('Hablé por WhatsApp: casa con patio.', $solicitud->comentarios_admin);

        $auditoria = Auditoria::query()->where('modelo', 'SolicitudAdopcion')->where('modelo_id', $solicitud->id)
            ->whereRaw("valores_nuevos ->> 'estado' = 'aprobada'")->sole();
        $this->assertSame($this->admin->id, $auditoria->usuario_id, 'queda quién aprobó');
        $this->assertSame('Hablé por WhatsApp: casa con patio.', $auditoria->valores_nuevos['comentarios_admin'], 'y por qué');

        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.show', $solicitud))
            ->assertSeeInOrder(['En evaluación → Reservada', 'Ana Quispe', '· Comentario: Hablé por WhatsApp: casa con patio.']);
    }

    public function test_aprobar_sin_comentario_sigue_siendo_un_solo_clic_y_no_borra_el_comentario_anterior(): void
    {
        $solicitud = $this->solicitudEnEvaluacion('Revisado el formulario.');

        $this->actingAs($this->admin)->patch(route('admin.solicitudes-adopcion.approve', $solicitud), ['comentarios_admin' => ''])->assertRedirect();

        $solicitud->refresh();
        $this->assertSame('aprobada', $solicitud->estado);
        $this->assertSame('Revisado el formulario.', $solicitud->comentarios_admin);
        $this->assertMatchesRegularExpression('/^POL-ADOP-\d{4}-\d{4,}$/', $solicitud->numero_acta);
    }

    public function test_el_comentario_al_aprobar_tiene_un_limite(): void
    {
        $solicitud = $this->solicitudEnEvaluacion();

        $this->actingAs($this->admin)
            ->patch(route('admin.solicitudes-adopcion.approve', $solicitud), ['comentarios_admin' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('comentarios_admin');
        $this->assertSame('en_espera', $solicitud->refresh()->estado);
    }

    public function test_la_etapa_aprobada_se_muestra_como_reservada_al_administrador_y_al_ciudadano(): void
    {
        $solicitud = $this->solicitudEnEvaluacion();
        $this->actingAs($this->admin)->patch(route('admin.solicitudes-adopcion.approve', $solicitud))->assertRedirect();

        $this->assertSame('Reservada', SolicitudAdopcion::ESTADOS_LEGIBLES['aprobada']);
        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.show', $solicitud))
            ->assertSee('3 · Reservada')->assertSee('Reservada el')->assertDontSee('3 · Aprobada');
        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.index'))->assertSee('Reservada');

        $this->actingAs($solicitud->solicitante)->get(route('solicitudes-adopcion.show', $solicitud))
            ->assertSee('Reservada')
            ->assertSee('La mascota quedó reservada para ti.')
            ->assertDontSee('Fecha de entrega acordada');
    }

    public function test_ya_no_existen_entrevista_visita_previa_ni_fecha_de_entrega_acordada(): void
    {
        foreach (['interview', 'home-visit', 'delivery-date'] as $nombre) {
            $this->assertFalse(Route::has("admin.solicitudes-adopcion.{$nombre}"), "ruta {$nombre}");
        }

        $solicitud = $this->solicitudEnEvaluacion();
        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.show', $solicitud))
            ->assertDontSee('Registrar entrevista')->assertDontSee('Entrega acordada');
    }

    public function test_la_secuencia_del_numero_de_acta_pertenece_a_su_columna(): void
    {
        // Ligada a la columna: PostgreSQL la elimina junto con la tabla y no queda suelta al recrear el esquema.
        $this->assertSame(
            'public.seq_numero_acta_adopcion',
            DB::selectOne("select pg_get_serial_sequence('solicitudes_adopcion', 'numero_acta') as s")->s
        );
    }

    // --- Seguimiento ---

    public function test_el_voluntario_reprograma_una_visita_sin_crear_otra(): void
    {
        [$seguimiento, $voluntario] = $this->seguimientoActivo();
        $visita = $this->programar($seguimiento, $voluntario, now()->addDays(10)->toDateString());
        $nuevaFecha = now()->addDays(12)->toDateString();

        $this->actingAs($voluntario)
            ->patch(route('seguimientos.visitas.reschedule', [$seguimiento, $visita]), ['fecha_programada' => $nuevaFecha])
            ->assertRedirect()->assertSessionHas('status', 'La visita fue reprogramada.');

        $this->assertSame($nuevaFecha, $visita->refresh()->fecha_programada->toDateString());
        $this->assertSame('programada', $visita->estado);
        $this->assertSame(1, $seguimiento->visitas()->count());
    }

    public function test_reprogramar_no_acepta_fechas_pasadas_ni_a_otro_voluntario_ni_visitas_terminadas(): void
    {
        [$seguimiento, $voluntario] = $this->seguimientoActivo();
        $visita = $this->programar($seguimiento, $voluntario, now()->addDays(10)->toDateString());

        $this->actingAs($voluntario)
            ->patch(route('seguimientos.visitas.reschedule', [$seguimiento, $visita]), ['fecha_programada' => now()->subDay()->toDateString()])
            ->assertSessionHasErrors('fecha_programada');

        $otro = User::factory()->create(['rol' => 'voluntario']);
        $this->actingAs($otro)
            ->patch(route('seguimientos.visitas.reschedule', [$seguimiento, $visita]), ['fecha_programada' => now()->addDays(11)->toDateString()])
            ->assertForbidden();

        $this->actingAs($voluntario)->patch(route('seguimientos.visitas.no-show', [$seguimiento, $visita]))->assertRedirect();
        $this->actingAs($voluntario)
            ->patch(route('seguimientos.visitas.reschedule', [$seguimiento, $visita]), ['fecha_programada' => now()->addDays(11)->toDateString()])
            ->assertNotFound();
    }

    public function test_no_asistio_cierra_la_visita_1_y_habilita_la_visita_2(): void
    {
        [$seguimiento, $voluntario] = $this->seguimientoActivo();
        $visita = $this->programar($seguimiento, $voluntario, now()->toDateString());

        // Con la visita 1 programada no se puede programar la 2.
        $this->actingAs($voluntario)
            ->post(route('seguimientos.visitas.store', $seguimiento), ['fecha_programada' => now()->addDays(80)->toDateString()])
            ->assertSessionHasErrors('fecha_programada');

        $this->actingAs($voluntario)
            ->patch(route('seguimientos.visitas.no-show', [$seguimiento, $visita]), ['observaciones' => 'No contestó el teléfono'])
            ->assertRedirect();

        $visita->refresh();
        $this->assertSame('no_asistio', $visita->estado);
        $this->assertNull($visita->fecha_realizada);
        $this->assertSame('No asistió: No contestó el teléfono', $visita->observaciones);

        $this->actingAs($voluntario)->get(route('seguimientos.show', $seguimiento))
            ->assertSee('No asistió')->assertSee('Programar visita 2');

        $this->actingAs($voluntario)
            ->post(route('seguimientos.visitas.store', $seguimiento), ['fecha_programada' => now()->addDays(80)->toDateString()])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $seguimiento->visitas()->count());
    }

    public function test_una_visita_adelantada_se_registra_con_su_fecha_real(): void
    {
        [$seguimiento, $voluntario] = $this->seguimientoActivo();
        $visita = $this->programar($seguimiento, $voluntario, now()->addDays(5)->toDateString());
        $hoy = now()->toDateString();

        $this->actingAs($voluntario)
            ->patch(route('seguimientos.visitas.complete', [$seguimiento, $visita]), ['fecha_realizada' => $hoy, 'respuestas' => self::RESPUESTAS_VISITA_1])
            ->assertRedirect()->assertSessionHasNoErrors();

        $visita->refresh();
        $this->assertSame('realizada', $visita->estado);
        $this->assertSame($hoy, $visita->fecha_realizada->toDateString());
        $this->assertSame($hoy, $visita->fecha_programada->toDateString(), 'la fecha programada pasa al día en que se hizo');
    }

    public function test_una_visita_no_puede_registrarse_antes_de_la_entrega(): void
    {
        [$seguimiento, $voluntario] = $this->seguimientoActivo();
        $visita = $this->programar($seguimiento, $voluntario, now()->toDateString());

        $this->actingAs($voluntario)
            ->patch(route('seguimientos.visitas.complete', [$seguimiento, $visita]), ['fecha_realizada' => now()->subDay()->toDateString(), 'respuestas' => self::RESPUESTAS_VISITA_1])
            ->assertSessionHasErrors(['fecha_realizada' => 'La fecha de la visita no puede ser anterior a la entrega de la mascota.']);
        $this->assertSame('programada', $visita->refresh()->estado);
    }

    public function test_la_foto_de_la_visita_se_guarda_sin_metadatos_exif_ni_ubicacion(): void
    {
        Storage::fake('local');
        [$seguimiento, $voluntario] = $this->seguimientoActivo();
        $visita = $this->programar($seguimiento, $voluntario, now()->toDateString());
        $foto = $this->fotoConExif();
        $this->assertStringContainsString('Exif', (string) file_get_contents($foto->getRealPath()), 'la foto de prueba trae EXIF');

        $this->actingAs($voluntario)
            ->patch(route('seguimientos.visitas.complete', [$seguimiento, $visita]), ['fecha_realizada' => now()->toDateString(), 'respuestas' => self::RESPUESTAS_VISITA_1, 'foto' => $foto])
            ->assertRedirect()->assertSessionHasNoErrors();

        $ruta = $visita->refresh()->foto_url;
        $this->assertMatchesRegularExpression('#^seguimientos/visitas/[A-Za-z0-9]{40}\.jpg$#', $ruta);
        Storage::disk('local')->assertExists($ruta);
        $guardada = Storage::disk('local')->get($ruta);
        $this->assertStringNotContainsString('Exif', $guardada, 'GD vuelve a codificar la imagen y descarta EXIF (GPS)');
        $this->assertStringNotContainsString('GPS-DE-PRUEBA', $guardada);
        Storage::disk('local')->assertMissing('seguimientos/visitas/miniaturas/'.basename($ruta));

        $this->actingAs($voluntario)->get(route('seguimientos.visitas.foto', [$seguimiento, $visita]))->assertOk();
    }

    // --- Ayudantes ---

    private function solicitudEnEvaluacion(?string $comentario = null): SolicitudAdopcion
    {
        $solicitud = $this->solicitudPendiente();
        $this->actingAs($this->admin)
            ->patch(route('admin.solicitudes-adopcion.evaluate', $solicitud), ['comentarios_admin' => $comentario])
            ->assertRedirect();

        return $solicitud->refresh();
    }

    private function solicitudPendiente(): SolicitudAdopcion
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano']);
        $mascota = DB::transaction(function (): Mascota {
            $mascota = Mascota::create(['nombre' => fake()->unique()->firstName(), 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'test.jpg', 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });

        return SolicitudAdopcion::create(['solicitante_id' => $ciudadano->id, 'mascota_id' => $mascota->id, 'estado' => 'pendiente', ...CuestionarioAdopcionDatos::registro()]);
    }

    /** @return array{0: SeguimientoAdopcion, 1: User} */
    private function seguimientoActivo(): array
    {
        $solicitud = $this->solicitudPendiente();
        $this->completarAdopcionPorRutas($this->admin, $solicitud);
        $seguimiento = $solicitud->seguimiento()->firstOrFail();
        $voluntario = User::factory()->create(['rol' => 'voluntario']);
        $seguimiento->update(['voluntario_id' => $voluntario->id, 'estado' => 'activo', 'fecha_inicio' => now()]);

        return [$seguimiento->refresh(), $voluntario];
    }

    private function programar(SeguimientoAdopcion $seguimiento, User $voluntario, string $fecha): VisitaSeguimiento
    {
        $this->actingAs($voluntario)
            ->post(route('seguimientos.visitas.store', $seguimiento), ['fecha_programada' => $fecha])
            ->assertRedirect()->assertSessionHasNoErrors();

        return $seguimiento->visitas()->latest('id')->firstOrFail();
    }

    /** JPEG real de 300×300 con un segmento APP1 «Exif» (como el que guarda un celular con la ubicación). */
    private function fotoConExif(): UploadedFile
    {
        $imagen = imagecreatetruecolor(300, 300);
        imagefilledrectangle($imagen, 0, 0, 299, 299, imagecolorallocate($imagen, 30, 90, 160));
        ob_start();
        imagejpeg($imagen, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($imagen);

        $datosExif = "Exif\0\0".'GPS-DE-PRUEBA -16.5000 -68.1500';
        $app1 = "\xFF\xE1".pack('n', strlen($datosExif) + 2).$datosExif;
        $ruta = tempnam(sys_get_temp_dir(), 'exif');
        file_put_contents($ruta, substr($jpeg, 0, 2).$app1.substr($jpeg, 2));

        return new UploadedFile($ruta, 'visita.jpg', 'image/jpeg', null, true);
    }
}
