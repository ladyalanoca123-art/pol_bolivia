<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Incidencia;
use App\Models\Mascota;
use App\Models\SeguimientoAdopcion;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use App\Support\CuestionarioAdopcion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CompletaFlujoAdopcion;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\TestCase;

/**
 * Foto del acta firmada también después de la entrega, incidencias que se atienden sin pasar por «En proceso»
 * y consentimiento de contacto (a2-v3) en lugar de la visita previa al hogar.
 */
class EvidenciaIncidenciasConsentimientoTest extends TestCase
{
    use CompletaFlujoAdopcion;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['rol' => 'administrador']);
        Storage::fake('local');
    }

    // --- B. Foto del acta firmada ---

    public function test_la_foto_del_acta_se_puede_subir_despues_de_registrar_la_entrega(): void
    {
        $solicitud = $this->solicitudPendiente();
        $this->completarAdopcionPorRutas($this->admin, $solicitud);
        $this->assertSame('aceptada', $solicitud->refresh()->estado);

        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.show', $solicitud))
            ->assertSee('Subir la foto del acta firmada');

        $this->actingAs($this->admin)
            ->post(route('admin.solicitudes-adopcion.commitment-evidence.store', $solicitud), ['evidencia_compromiso' => UploadedFile::fake()->image('acta.jpg', 900, 1200)])
            ->assertRedirect(route('admin.solicitudes-adopcion.show', $solicitud))
            ->assertSessionHas('status', 'La foto del acta firmada se guardó de forma privada.');

        $ruta = $solicitud->refresh()->compromiso_foto_path;
        $this->assertStringStartsWith("compromisos/{$solicitud->id}/", $ruta);
        Storage::disk('local')->assertExists($ruta);

        // Se puede reemplazar: el archivo anterior se borra.
        $this->actingAs($this->admin)
            ->post(route('admin.solicitudes-adopcion.commitment-evidence.store', $solicitud), ['evidencia_compromiso' => UploadedFile::fake()->image('acta2.jpg', 900, 1200)])
            ->assertRedirect();
        $this->assertNotSame($ruta, $solicitud->refresh()->compromiso_foto_path);
        Storage::disk('local')->assertMissing($ruta);
        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.show', $solicitud))
            ->assertSee('Reemplazar la foto del acta firmada');
    }

    public function test_la_foto_del_acta_no_se_sube_en_solicitudes_sin_reserva_ni_en_finales_sin_adopcion(): void
    {
        $pendiente = $this->solicitudPendiente();
        $rechazada = $this->solicitudPendiente();
        $this->actingAs($this->admin)->patch(route('admin.solicitudes-adopcion.reject', $rechazada), ['comentarios_admin' => 'No cumple los requisitos.'])->assertRedirect();

        foreach ([$pendiente, $rechazada] as $solicitud) {
            $this->actingAs($this->admin)
                ->post(route('admin.solicitudes-adopcion.commitment-evidence.store', $solicitud), ['evidencia_compromiso' => UploadedFile::fake()->image('acta.jpg', 900, 1200)])
                ->assertForbidden();
        }
    }

    public function test_postgresql_permite_la_foto_tras_la_entrega_pero_no_en_una_rechazada(): void
    {
        $concretada = $this->solicitudPendiente();
        $this->completarAdopcionConTransicionesDeBase($concretada);
        DB::table('solicitudes_adopcion')->where('id', $concretada->id)->update(['compromiso_foto_path' => "compromisos/{$concretada->id}/acta.jpg"]);
        $this->assertSame("compromisos/{$concretada->id}/acta.jpg", $concretada->refresh()->compromiso_foto_path);

        $rechazada = $this->solicitudPendiente();
        DB::table('solicitudes_adopcion')->where('id', $rechazada->id)->update(['estado' => 'rechazada']);
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('La evidencia del compromiso solo se registra en una solicitud reservada o con la adopción concretada');
        DB::table('solicitudes_adopcion')->where('id', $rechazada->id)->update(['compromiso_foto_path' => "compromisos/{$rechazada->id}/acta.jpg"]);
    }

    // --- A. Incidencias ---

    public function test_el_voluntario_atiende_una_incidencia_directamente_sin_en_proceso(): void
    {
        [$seguimiento, $voluntario] = $this->seguimientoActivo();
        $incidencia = Incidencia::create(['seguimiento_id' => $seguimiento->id, 'reportado_por' => $voluntario->id, 'tipo' => 'salud', 'descripcion' => 'Herida leve en la pata', 'estado' => 'pendiente']);

        $this->actingAs($voluntario)->get(route('seguimientos.show', $seguimiento))->assertSee('Atender');

        $this->actingAs($voluntario)
            ->patch(route('seguimientos.incidencias.attend', [$seguimiento, $incidencia]), ['comentarios_resolucion' => 'Se llevó al veterinario, ya está bien.'])
            ->assertRedirect();

        $incidencia->refresh();
        $this->assertSame('atendida', $incidencia->estado);
        $this->assertSame($voluntario->id, $incidencia->atendida_por);
        $this->assertNotNull($incidencia->atendida_at);
    }

    public function test_marcar_en_proceso_sigue_siendo_posible_y_opcional(): void
    {
        [$seguimiento, $voluntario] = $this->seguimientoActivo();
        $incidencia = Incidencia::create(['seguimiento_id' => $seguimiento->id, 'reportado_por' => $voluntario->id, 'tipo' => 'otro', 'descripcion' => 'La familia pidió orientación', 'estado' => 'pendiente']);

        $this->actingAs($this->admin)->get(route('admin.seguimientos.show', $seguimiento))->assertSee('Marcar en proceso (opcional)');
        $this->actingAs($this->admin)->patch(route('admin.incidencias.progress', $incidencia))->assertRedirect();
        $this->assertSame('en_proceso', $incidencia->refresh()->estado);

        $this->actingAs($voluntario)
            ->patch(route('seguimientos.incidencias.attend', [$seguimiento, $incidencia]), ['comentarios_resolucion' => 'Orientación brindada.'])
            ->assertRedirect();
        $this->assertSame('atendida', $incidencia->refresh()->estado);
    }

    public function test_otro_voluntario_no_atiende_la_incidencia(): void
    {
        [$seguimiento, $voluntario] = $this->seguimientoActivo();
        $incidencia = Incidencia::create(['seguimiento_id' => $seguimiento->id, 'reportado_por' => $voluntario->id, 'tipo' => 'salud', 'descripcion' => 'Revisión necesaria', 'estado' => 'pendiente']);
        $otro = User::factory()->create(['rol' => 'voluntario']);

        $this->actingAs($otro)
            ->patch(route('seguimientos.incidencias.attend', [$seguimiento, $incidencia]), ['comentarios_resolucion' => 'Intento ajeno'])
            ->assertForbidden();
        $this->assertSame('pendiente', $incidencia->refresh()->estado);
    }

    // --- C. Consentimiento ---

    public function test_el_formulario_pide_consentimiento_de_contacto_y_no_una_visita_previa(): void
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano']);

        $this->actingAs($ciudadano)->get(route('solicitudes-adopcion.create', $this->mascota()))->assertOk()
            ->assertSee('Acepto que POL-Bolivia me contacte por WhatsApp o llamada para evaluar mi solicitud y coordinar la entrega de la mascota.')
            ->assertSee('visitas de seguimiento después de la adopción')
            ->assertDontSee('visita previa')
            ->assertDontSee('visita domiciliaria');

        $this->actingAs($ciudadano)
            ->post(route('solicitudes-adopcion.store', $this->mascota()), CuestionarioAdopcionDatos::valido(['acepta_visita_previa' => null]))
            ->assertSessionHasErrors(['acepta_visita_previa' => 'Debes aceptar que la fundación te contacte para evaluar tu solicitud y coordinar la entrega.']);
    }

    public function test_las_solicitudes_anteriores_conservan_el_consentimiento_que_aceptaron(): void
    {
        $this->assertSame('a2-v3', CuestionarioAdopcion::VERSION);
        $anterior = $this->solicitudPendiente(['version_cuestionario' => 'a2-v2']);
        $nueva = $this->solicitudPendiente(['version_cuestionario' => 'a2-v3']);

        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.show', $anterior))
            ->assertSee('Aceptados (visita previa, seguimiento, no ceder y declaración de veracidad)');
        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.show', $nueva))
            ->assertSee('Aceptados (contacto por WhatsApp o llamada, seguimiento, no ceder y declaración de veracidad)');
    }

    // --- Ayudantes ---

    private function solicitudPendiente(array $cambios = []): SolicitudAdopcion
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano']);

        return SolicitudAdopcion::create(['solicitante_id' => $ciudadano->id, 'mascota_id' => $this->mascota()->id, 'estado' => 'pendiente', ...CuestionarioAdopcionDatos::registro($cambios)]);
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

    private function mascota(): Mascota
    {
        return DB::transaction(function (): Mascota {
            $mascota = Mascota::create(['nombre' => fake()->unique()->firstName(), 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $this->admin->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'test.jpg', 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
    }
}
