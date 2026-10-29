<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\PostulacionVoluntariado;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CompletaFlujoAdopcion;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\TestCase;

class ReporteManagementTest extends TestCase
{
    use CompletaFlujoAdopcion;
    use RefreshDatabase;

    public function test_only_active_administrator_can_access_reports(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $this->actingAs($administrator)->get(route('admin.reportes.index'))->assertOk()->assertSee('Reportes y estadísticas');

        foreach (['ciudadano', 'voluntario'] as $role) {
            $this->actingAs(User::factory()->create(['rol' => $role]))->get(route('admin.reportes.index'))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['rol' => 'administrador', 'estado' => false]))->get(route('admin.reportes.index'))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.reportes.index'))->assertRedirect(route('login'));
    }

    public function test_report_displays_counts_from_existing_records(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $available = $this->makePet($administrator);
        $accepted = $this->makeAcceptedRequest($administrator);
        $rejected = SolicitudAdopcion::create([...$this->requestData(), 'solicitante_id' => User::factory()->create()->id, 'mascota_id' => $available->id, 'estado' => 'pendiente']);
        $rejected->update(['estado' => 'rechazada']);
        $cancelledApplication = PostulacionVoluntariado::create(['usuario_id' => User::factory()->create(['rol' => 'ciudadano'])->id, 'motivacion' => 'Quiero colaborar.', 'disponibilidad' => 'Fines de semana', 'zona_preferencia' => 'Centro', 'estado' => 'pendiente']);
        $cancelledApplication->update(['estado' => 'cancelada']);

        $response = $this->actingAs($administrator)->get(route('admin.reportes.index'));
        $response->assertOk()
            ->assertSee('Total mascotas')
            ->assertSee('Mascotas disponibles')
            ->assertSee('Mascotas adoptadas')
            ->assertSee('Solicitudes de adopción')
            ->assertSee('Adopciones realizadas')
            ->assertSee('Postulaciones canceladas')
            ->assertSee('Seguimientos')
            ->assertSee('Incidencias')
            ->assertSee('Reportes y estadísticas');

        $this->assertDatabaseHas('mascotas', ['id' => $available->id, 'estado' => 'disponible']);
        $this->assertDatabaseHas('solicitudes_adopcion', ['id' => $accepted->id, 'estado' => 'aceptada']);
        $this->assertDatabaseHas('solicitudes_adopcion', ['id' => $rejected->id, 'estado' => 'rechazada']);
        $this->assertSame(1, $response->viewData('postulacionesPorEstado')['cancelada']);
    }

    public function test_adoption_period_is_validated_and_filters_accepted_requests(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $old = $this->makeAcceptedRequest($administrator);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();
        // Las adopciones del período se cuentan por su fecha de resolución (resuelta_at).
        DB::table('solicitudes_adopcion')->where('id', $old->id)->update(['resuelta_at' => now()->subDays(10)]);
        $recent = $this->makeAcceptedRequest($administrator);

        $response = $this->actingAs($administrator)->get(route('admin.reportes.index', [
            'fecha_inicio' => now()->subDay()->toDateString(), 'fecha_fin' => now()->toDateString(),
        ]));

        $response->assertOk()->assertSee('Adopciones en el período');
        $adopcionesPorFecha = $response->viewData('adopcionesPorFecha');
        $this->assertSame(1, $response->viewData('totalAdopciones'));
        $this->assertFalse($adopcionesPorFecha->has($old->fresh()->resuelta_at->toDateString()));
        $this->assertTrue($adopcionesPorFecha->has($recent->fresh()->resuelta_at->toDateString()));
        $this->assertSame(1, $adopcionesPorFecha->sum());

        $this->actingAs($administrator)->get(route('admin.reportes.index', [
            'fecha_inicio' => now()->toDateString(), 'fecha_fin' => now()->subDay()->toDateString(),
        ]))->assertSessionHasErrors('fecha_inicio');

        $this->assertNotSame($old->id, $recent->id);
    }

    private function makeAcceptedRequest(User $administrator): SolicitudAdopcion
    {
        $pet = $this->makePet($administrator);
        $request = SolicitudAdopcion::create([...$this->requestData(), 'solicitante_id' => User::factory()->create()->id, 'mascota_id' => $pet->id, 'estado' => 'pendiente']);
        $this->completarAdopcionPorRutas($administrator, $request);

        return $request;
    }

    private function makePet(User $administrator): Mascota
    {
        return DB::transaction(function () use ($administrator): Mascota {
            $pet = Mascota::create(['nombre' => fake()->unique()->firstName(), 'especie' => 'perro', 'raza' => 'Mestiza', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $administrator->id]);
            FotoMascota::create(['mascota_id' => $pet->id, 'tipo' => 'foto', 'url' => "mascotas/{$pet->id}.jpg", 'es_principal' => true, 'orden' => 1]);

            return $pet;
        });
    }

    private function requestData(): array
    {
        return CuestionarioAdopcionDatos::registro();
    }
}
