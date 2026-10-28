<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\TestCase;

/**
 * Acta de adopción para imprimir, firmar y sellar (resources/views/admin/solicitudes-adopcion/compromiso-pdf.blade.php).
 */
class ActaAdopcionPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['rol' => 'administrador']);
    }

    public function test_el_acta_tiene_los_datos_los_compromisos_las_firmas_y_el_sello(): void
    {
        $solicitud = $this->solicitudReservada();
        $html = $this->html($solicitud);

        foreach ([
            'Acta de adopción', 'Acta N.º '.$solicitud->numero_acta, 'Cochabamba, Bolivia',
            'data:image/png;base64,',                       // logo incrustado, sin depender de la red
            'Rosa Elena Condori Mamani', '71234567', 'Sopocachi',
            'Pelusa', 'Gato', 'Hembra', 'Siamés · Pequeño', 'Blanco y gris', '3 años aprox.',
            'Compromisos de la persona adoptante', 'Ley N.º 700',
            'No venderla, regalarla ni entregarla a otra persona.',
            'Si ya no puede cuidarla, avisar y devolverla a POL-Bolivia. Nunca abandonarla.',
            'POL-Bolivia podrá pedir la devolución de la mascota para proteger su bienestar',
            'a los 15 y a los 90 días de la entrega',
            'Persona adoptante', 'Por POL-Bolivia', 'Sello',
            'Válida solo con firmas y sello originales',
        ] as $texto) {
            $this->assertStringContainsString($texto, $html, "Falta en el acta: {$texto}");
        }

        // Sin la ciudad en blanco (el sistema es de Cochabamba), sin fórmulas notariales, sin iniciales al pie
        // y sin el aviso interno del formato anterior.
        foreach (['En la ciudad de', 'comparecen', 'en adelante', 'Iniciales', 'Formato para firma', 'no acredita por sí mismo'] as $texto) {
            $this->assertStringNotContainsString($texto, $html, "No debe aparecer: {$texto}");
        }
    }

    public function test_el_acta_dice_quien_aprobo_y_quien_registro_la_entrega(): void
    {
        $aprobo = User::factory()->create(['rol' => 'administrador', 'nombres' => 'Lady', 'apellidos' => 'Alanoca']);
        $entrego = User::factory()->create(['rol' => 'administrador', 'nombres' => 'Carlos', 'apellidos' => 'Rojas Vargas']);
        $solicitud = $this->solicitudReservada($aprobo);

        // Reservada: figura quien aprobó, y firma por POL-Bolivia mientras no hay entrega.
        $html = $this->html($solicitud);
        $this->assertMatchesRegularExpression('#Aprobada por</td>\s*<td class="val">Lady Alanoca</td>#', $html);
        $this->assertMatchesRegularExpression('#<span class="quien">Lady Alanoca</span><br>Por POL-Bolivia#', $html);
        $this->assertStringNotContainsString('Carlos Rojas Vargas', $html);

        $this->actingAs($entrego)->patch(route('admin.solicitudes-adopcion.deliver', $solicitud), [
            'fecha_entrega' => now()->toDateString(), 'compromiso_firmado' => '1',
        ])->assertRedirect();

        // Concretada: quien registró la entrega (cerró el caso) aparece y firma por POL-Bolivia.
        $html = $this->html($solicitud->refresh());
        $this->assertMatchesRegularExpression('#Entregada por</td>\s*<td class="val">Carlos Rojas Vargas</td>#', $html);
        $this->assertMatchesRegularExpression('#<span class="quien">Carlos Rojas Vargas</span><br>Por POL-Bolivia#', $html);
        $this->assertMatchesRegularExpression('#Aprobada por</td>\s*<td class="val">Lady Alanoca</td>#', $html, 'se conserva quién aprobó');
        $this->assertStringContainsString(now('America/La_Paz')->translatedFormat('j \d\e F \d\e Y'), $html);

        $this->assertTrue($solicitud->responsableDe('aprobada')->is($aprobo));
        $this->assertTrue($solicitud->responsableDe('aceptada')->is($entrego));
    }

    public function test_antes_de_la_entrega_la_fecha_y_el_responsable_de_la_entrega_quedan_en_blanco(): void
    {
        $html = $this->html($this->solicitudReservada());

        $this->assertMatchesRegularExpression('#Entrega</td>\s*<td class="val"><span class="llenar">#', $html);
        $this->assertMatchesRegularExpression('#Entregada por</td>\s*<td class="val"><span class="llenar">#', $html);
    }

    public function test_solo_la_administracion_descarga_el_acta_en_pdf_con_su_numero(): void
    {
        $solicitud = $this->solicitudReservada();

        $this->actingAs($this->admin)->get(route('admin.solicitudes-adopcion.commitment-pdf', $solicitud))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload("acta-adopcion-{$solicitud->numero_acta}.pdf");

        $this->actingAs($solicitud->solicitante)->get(route('admin.solicitudes-adopcion.commitment-pdf', $solicitud))->assertForbidden();
    }

    /** Misma vista y mismos datos que entrega el controlador. */
    private function html(SolicitudAdopcion $solicitud): string
    {
        $solicitud->load(['solicitante', 'mascota']);

        return View::make('admin.solicitudes-adopcion.compromiso-pdf', [
            'solicitud' => $solicitud,
            'aprobadaPor' => $solicitud->responsableDe('aprobada'),
            'entregadaPor' => $solicitud->estado === 'aceptada' ? $solicitud->responsableDe('aceptada') : null,
        ])->render();
    }

    private function solicitudReservada(?User $aprueba = null): SolicitudAdopcion
    {
        $ciudadano = User::factory()->create(['rol' => 'ciudadano', 'nombres' => 'Rosa Elena', 'apellidos' => 'Condori Mamani', 'telefono' => '71234567']);
        $mascota = DB::transaction(function (): Mascota {
            $mascota = Mascota::create(['nombre' => 'Pelusa', 'especie' => 'gato', 'raza' => 'Siamés', 'color' => 'Blanco y gris', 'edad' => 3, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'pequeno', 'estado' => 'disponible', 'registrado_por' => $this->admin->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'test.jpg', 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
        $solicitud = SolicitudAdopcion::create([
            'solicitante_id' => $ciudadano->id, 'mascota_id' => $mascota->id, 'estado' => 'pendiente',
            ...CuestionarioAdopcionDatos::registro(), 'zona_aproximada' => 'Sopocachi',
        ]);

        $this->actingAs($this->admin)->patch(route('admin.solicitudes-adopcion.evaluate', $solicitud))->assertRedirect();
        $this->actingAs($aprueba ?? $this->admin)->patch(route('admin.solicitudes-adopcion.approve', $solicitud))->assertRedirect();

        return $solicitud->refresh();
    }
}
