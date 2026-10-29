<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\SeguimientoAdopcion;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\TestCase;

class CompromisoYSeguimientoAdopcionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $ciudadano;

    protected function setUp(): void
    {
        parent::setUp();
        // TestCase comprueba antes de RefreshDatabase la identidad de PostgreSQL y la marca de seguridad.
        $this->assertSame('pol_bolivia_test', DB::connection()->getDatabaseName());
        $this->admin = User::factory()->create(['rol' => 'administrador']);
        $this->ciudadano = User::factory()->create(['rol' => 'ciudadano']);
        Storage::fake('local');
    }

    public function test_admin_uploads_private_evidence_without_implicitly_marking_it_signed_and_can_download_protected_pdf(): void
    {
        $solicitud = $this->solicitudAprobada();

        $this->actingAs($this->admin)
            ->post(route('admin.solicitudes-adopcion.commitment-evidence.store', $solicitud), [
                'evidencia_compromiso' => UploadedFile::fake()->image('compromiso.png', 600, 800),
            ])
            ->assertRedirect(route('admin.solicitudes-adopcion.show', $solicitud));

        $solicitud->refresh();
        $this->assertFalse($solicitud->compromiso_firmado, 'Adjuntar evidencia no equivale a confirmar que se firmó.');
        $this->assertStringStartsWith('compromisos/'.$solicitud->id.'/', $solicitud->compromiso_foto_path);
        Storage::disk('local')->assertExists($solicitud->compromiso_foto_path);
        Storage::disk('public')->assertMissing($solicitud->compromiso_foto_path);

        $this->actingAs($this->admin)
            ->get(route('admin.solicitudes-adopcion.commitment-evidence.download', $solicitud))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($this->admin)
            ->get(route('admin.solicitudes-adopcion.commitment-pdf', $solicitud))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->ciudadano)
            ->get(route('admin.solicitudes-adopcion.commitment-evidence.download', $solicitud))
            ->assertForbidden();
    }

    public function test_delivery_cannot_be_registered_without_explicit_signed_commitment_confirmation(): void
    {
        $solicitud = $this->solicitudAprobada();
        $mascota = $solicitud->mascota;

        $this->actingAs($this->admin)
            ->from(route('admin.solicitudes-adopcion.show', $solicitud))
            ->patch(route('admin.solicitudes-adopcion.deliver', $solicitud), [
                'fecha_entrega' => now()->toDateString(),
            ])
            ->assertRedirect(route('admin.solicitudes-adopcion.show', $solicitud))
            ->assertSessionHasErrors('compromiso_firmado');

        $this->assertSame('aprobada', $solicitud->refresh()->estado);
        $this->assertFalse($solicitud->compromiso_firmado);
        $this->assertSame('reservada', $mascota->refresh()->estado);
        $this->assertDatabaseMissing('seguimientos_adopcion', ['solicitud_id' => $solicitud->id]);
    }

    public function test_a_final_request_with_new_flow_data_prevents_destructive_migration_rollback(): void
    {
        $solicitud = $this->solicitudAprobada();
        $this->actingAs($this->admin)->patch(route('admin.solicitudes-adopcion.deliver', $solicitud), [
            'fecha_entrega' => now()->toDateString(),
            'compromiso_firmado' => '1',
        ])->assertRedirect();

        // Como en un rollback real: primero se revierten las migraciones posteriores que tocan las mismas columnas.
        (require database_path('migrations/2026_10_24_000000_quitar_columnas_sin_uso_de_solicitudes_adopcion.php'))->down();

        $migracion = require database_path('migrations/2026_10_20_000000_nuevo_flujo_solicitudes_adopcion.php');
        try {
            $migracion->down();
            $this->fail('La reversión debió detenerse para conservar los datos de la adopción concretada.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('conservan datos del nuevo flujo', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('solicitudes_adopcion', 'numero_acta'));
        $this->assertSame('aceptada', $solicitud->refresh()->estado);
    }

    public function test_insert_rejects_user_supplied_evidence_and_cancellation_reason(): void
    {
        foreach ([
            ['compromiso_foto_path' => 'compromisos/falso.png'],
            ['motivo_cancelacion' => 'Motivo inyectado'],
        ] as $datosProtegidos) {
            $mascota = $this->mascotaDisponible();
            try {
                DB::transaction(fn () => SolicitudAdopcion::forceCreate([
                        ...CuestionarioAdopcionDatos::registro(),
                        'solicitante_id' => $this->ciudadano->id,
                        'mascota_id' => $mascota->id,
                        'estado' => 'pendiente',
                        ...$datosProtegidos,
                    ]));
                $this->fail('El trigger debía rechazar campos protegidos enviados al crear una solicitud.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('Una solicitud nueva no puede traer evidencia', $exception->getMessage());
            }
        }
    }

    public function test_evidence_cannot_be_added_to_a_pending_request_but_citizen_can_cancel_legitimately(): void
    {
        $solicitud = $this->solicitudPendiente();

        try {
            DB::transaction(fn () => DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update([
                    'compromiso_foto_path' => 'compromisos/'.$solicitud->id.'/falso.png',
                ]));
            $this->fail('La evidencia solo debe añadirse en el estado aprobado.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('La evidencia del compromiso solo se registra en una solicitud reservada o con la adopción concretada', $exception->getMessage());
        }

        try {
            DB::transaction(fn () => DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update([
                    'motivo_cancelacion' => 'Motivo agregado sin cancelar',
                ]));
            $this->fail('El motivo de cancelación solo debe guardarse al cancelar una solicitud activa.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('El motivo de cancelación solo se registra al cancelar una solicitud activa', $exception->getMessage());
        }

        $this->actingAs($this->ciudadano)
            ->patch(route('solicitudes-adopcion.cancel', $solicitud))
            ->assertRedirect(route('solicitudes-adopcion.show', $solicitud))
            ->assertSessionHas('status', 'Cancelaste tu solicitud de adopción.');

        $this->assertSame('cancelada', $solicitud->refresh()->estado);
        $this->assertSame('Cancelada por el solicitante', $solicitud->motivo_cancelacion);
    }

    public function test_database_rejects_a_third_active_request_for_the_same_citizen(): void
    {
        $this->solicitudPendiente();
        $this->solicitudPendiente();
        $terceraMascota = $this->mascotaDisponible();

        try {
            DB::transaction(fn () => SolicitudAdopcion::forceCreate([
                    ...CuestionarioAdopcionDatos::registro(),
                    'solicitante_id' => $this->ciudadano->id,
                    'mascota_id' => $terceraMascota->id,
                    'estado' => 'pendiente',
                ]));
            $this->fail('La base debe limitar a dos las solicitudes activas por ciudadano.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Ya tienes 2 solicitudes de adopción activas', $exception->getMessage());
        }
    }

    public function test_follow_up_dates_are_suggestions_and_do_not_create_visits_automatically(): void
    {
        $solicitud = $this->solicitudAprobada();

        $this->actingAs($this->admin)->patch(route('admin.solicitudes-adopcion.deliver', $solicitud), [
            'fecha_entrega' => now()->toDateString(),
            'compromiso_firmado' => '1',
        ])->assertRedirect();

        $this->assertSame('aceptada', $solicitud->refresh()->estado);
        $this->assertTrue($solicitud->compromiso_firmado);
        $this->assertNull($solicitud->compromiso_foto_path, 'La confirmación administrativa permite registrar la entrega sin archivo adjunto.');
        $seguimiento = SeguimientoAdopcion::query()->where('solicitud_id', $solicitud->id)->sole();
        $fechaEntrega = $solicitud->entregada_at->timezone('America/La_Paz');
        $fechaVisita1 = $fechaEntrega->copy()->addDays(15);
        $fechaVisita2 = $fechaEntrega->copy()->addDays(90);
        $this->actingAs($this->admin)
            ->get(route('admin.seguimientos.show', $seguimiento))
            ->assertOk()
            ->assertSee('objetivo a los 15 días')
            ->assertSee('objetivo a los 90 días')
            ->assertSee($fechaVisita1->format('d/m/Y'))
            ->assertSee($fechaVisita2->format('d/m/Y'))
            ->assertSee('margen recomendado')
            ->assertSee('cada visita se programa');

        $this->assertSame(0, $seguimiento->visitas()->count());
    }

    private function solicitudAprobada(): SolicitudAdopcion
    {
        $solicitud = $this->solicitudPendiente();

        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['estado' => 'en_espera']);
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['estado' => 'aprobada']);

        return $solicitud->fresh();
    }

    private function solicitudPendiente(): SolicitudAdopcion
    {
        $mascota = $this->mascotaDisponible();
        return SolicitudAdopcion::create([
            ...CuestionarioAdopcionDatos::registro(),
            'solicitante_id' => $this->ciudadano->id,
            'mascota_id' => $mascota->id,
            'estado' => 'pendiente',
        ]);
    }

    private function mascotaDisponible(): Mascota
    {
        $mascota = Mascota::create([
            'nombre' => fake()->unique()->firstName(), 'especie' => 'perro', 'edad' => 2,
            'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible',
            'registrado_por' => $this->admin->id,
        ]);
        FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'test.jpg', 'es_principal' => true, 'orden' => 1]);
        return $mascota;
    }
}
