<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Incidencia;
use App\Models\Mascota;
use App\Models\SeguimientoAdopcion;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use App\Models\VisitaSeguimiento;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeguimientoAdopcionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrator_can_assign_activate_and_close_a_tracking_case(): void
    {
        [$administrator, $seguimiento] = $this->acceptedTracking();
        $volunteer = User::factory()->create(['rol' => 'voluntario']);

        $this->actingAs($administrator)->patch(route('admin.seguimientos.assign', $seguimiento), ['voluntario_id' => $volunteer->id])->assertRedirect();
        $this->assertDatabaseHas('seguimientos_adopcion', ['id' => $seguimiento->id, 'voluntario_id' => $volunteer->id, 'estado' => 'activo']);

        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $this->actingAs($citizen)->patch(route('admin.seguimientos.close', $seguimiento))->assertForbidden();
    }

    public function test_assigned_volunteer_can_only_view_own_tracking_case(): void
    {
        [$administrator, $own] = $this->acceptedTracking();
        [, $other] = $this->acceptedTracking();
        $volunteer = User::factory()->create(['rol' => 'voluntario']);
        $own->update(['voluntario_id' => $volunteer->id, 'estado' => 'activo', 'fecha_inicio' => now()]);

        $this->actingAs($volunteer)->get(route('seguimientos.show', $own))->assertOk();
        $this->actingAs($volunteer)->get(route('seguimientos.show', $other))->assertForbidden();
        $this->actingAs(User::factory()->create(['rol' => 'ciudadano']))->get(route('seguimientos.show', $own))->assertForbidden();
    }

    public function test_only_assigned_volunteer_can_register_visits_and_postgresql_limits_them_to_two(): void
    {
        [$administrator, $seguimiento] = $this->acceptedTracking();
        $volunteer = $this->activate($administrator, $seguimiento);
        $other = User::factory()->create(['rol' => 'voluntario']);

        $this->actingAs($other)->post(route('seguimientos.visitas.store', $seguimiento), $this->visitData())->assertForbidden();
        $this->actingAs($volunteer)->post(route('seguimientos.visitas.store', $seguimiento), $this->visitData())->assertRedirect();
        $this->actingAs($volunteer)->post(route('seguimientos.visitas.store', $seguimiento), $this->visitData(['fecha_programada' => now()->addDay()->toDateString()]))->assertRedirect();

        $this->expectException(QueryException::class);
        VisitaSeguimiento::create(['seguimiento_id' => $seguimiento->id, 'voluntario_id' => $volunteer->id, 'fecha_programada' => now()->addDays(2)->toDateString(), 'estado' => 'programada']);
    }

    public function test_visit_cannot_be_completed_before_its_scheduled_date(): void
    {
        [$administrator, $seguimiento] = $this->acceptedTracking();
        $volunteer = $this->activate($administrator, $seguimiento);
        $visita = VisitaSeguimiento::create(['seguimiento_id' => $seguimiento->id, 'voluntario_id' => $volunteer->id, 'fecha_programada' => now()->addDay()->toDateString(), 'estado' => 'programada']);

        $this->expectException(QueryException::class);
        $visita->update(['estado' => 'realizada', 'fecha_realizada' => now()]);
    }

    public function test_only_assigned_volunteer_can_report_incident_and_incident_visit_must_belong_to_same_tracking(): void
    {
        [$administrator, $seguimiento] = $this->acceptedTracking();
        $volunteer = $this->activate($administrator, $seguimiento);
        [, $otherTracking] = $this->acceptedTracking();
        $otherVolunteer = $this->activate($administrator, $otherTracking);
        $foreignVisit = VisitaSeguimiento::create(['seguimiento_id' => $otherTracking->id, 'voluntario_id' => $otherVolunteer->id, 'fecha_programada' => now()->toDateString(), 'estado' => 'programada']);

        $this->actingAs(User::factory()->create(['rol' => 'voluntario']))->post(route('seguimientos.incidencias.store', $seguimiento), $this->incidentData())->assertForbidden();
        $this->actingAs($volunteer)->post(route('seguimientos.incidencias.store', $seguimiento), $this->incidentData(['visita_id' => $foreignVisit->id]))->assertNotFound();
        $this->actingAs($volunteer)->post(route('seguimientos.incidencias.store', $seguimiento), $this->incidentData())->assertRedirect();
        $this->assertDatabaseHas('incidencias', ['seguimiento_id' => $seguimiento->id, 'reportado_por' => $volunteer->id, 'estado' => 'pendiente']);
    }

    public function test_incidence_requires_resolution_to_be_attended_and_cannot_reopen(): void
    {
        [$administrator, $seguimiento] = $this->acceptedTracking();
        $volunteer = $this->activate($administrator, $seguimiento);
        $incidencia = Incidencia::create([...$this->incidentData(), 'seguimiento_id' => $seguimiento->id, 'reportado_por' => $volunteer->id, 'estado' => 'pendiente']);

        $this->actingAs($administrator)->patch(route('admin.seguimientos.incidencias.attend', [$seguimiento, $incidencia]), ['comentarios_resolucion' => 'Caso resuelto'])->assertForbidden();
        $this->assertDatabaseHas('incidencias', ['id' => $incidencia->id, 'estado' => 'pendiente']);

        $this->actingAs($administrator)->patch(route('admin.incidencias.progress', $incidencia))->assertRedirect();
        $this->actingAs($administrator)->patch(route('admin.seguimientos.incidencias.attend', [$seguimiento, $incidencia]), ['comentarios_resolucion' => 'Caso resuelto'])->assertRedirect();
        $this->assertDatabaseHas('incidencias', ['id' => $incidencia->id, 'estado' => 'atendida', 'atendida_por' => $administrator->id]);

        $this->expectException(QueryException::class);
        $incidencia->refresh();
        $incidencia->update(['estado' => 'pendiente']);
    }

    public function test_cannot_close_tracking_with_pending_visits_or_unattended_incidents(): void
    {
        [$administrator, $seguimiento] = $this->acceptedTracking();
        $volunteer = $this->activate($administrator, $seguimiento);
        VisitaSeguimiento::create(['seguimiento_id' => $seguimiento->id, 'voluntario_id' => $volunteer->id, 'fecha_programada' => now()->toDateString(), 'estado' => 'programada']);
        $this->expectException(QueryException::class);
        $seguimiento->update(['estado' => 'cerrado', 'fecha_cierre' => now()]);
    }

    public function test_administrator_can_close_tracking_after_all_visits_and_incidents_are_resolved(): void
    {
        [$administrator, $seguimiento] = $this->acceptedTracking();
        $volunteer = $this->activate($administrator, $seguimiento);
        $visita = VisitaSeguimiento::create(['seguimiento_id' => $seguimiento->id, 'voluntario_id' => $volunteer->id, 'fecha_programada' => now()->toDateString(), 'estado' => 'programada']);
        $visita->update(['estado' => 'realizada', 'fecha_realizada' => now()]);
        $incidencia = Incidencia::create([...$this->incidentData(), 'seguimiento_id' => $seguimiento->id, 'reportado_por' => $volunteer->id, 'estado' => 'pendiente']);
        $incidencia->update(['estado' => 'atendida', 'comentarios_resolucion' => 'Atendida', 'atendida_por' => $administrator->id, 'atendida_at' => now()]);

        $this->actingAs($administrator)->patch(route('admin.seguimientos.close', $seguimiento))->assertRedirect();
        $this->assertDatabaseHas('seguimientos_adopcion', ['id' => $seguimiento->id, 'estado' => 'cerrado']);
    }

    private function acceptedTracking(): array
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $citizen = User::factory()->create(['rol' => 'ciudadano']);
        $mascota = DB::transaction(function () use ($administrator): Mascota {
            $mascota = Mascota::create(['nombre' => fake()->unique()->firstName(), 'especie' => 'perro', 'edad' => 2, 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $administrator->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'test.jpg', 'es_principal' => true, 'orden' => 1]);
            return $mascota;
        });
        $solicitud = SolicitudAdopcion::create(['solicitante_id' => $citizen->id, 'mascota_id' => $mascota->id, 'estado' => 'pendiente', 'tiene_otras_mascotas' => false, 'viven_otras_personas' => false, 'tiene_espacio_adecuado' => true, 'zona_aproximada' => 'Centro', 'horario_preferido' => 'Tarde', 'declara_informacion_veraz' => true]);
        $solicitud->update(['estado' => 'aceptada']);
        return [$administrator, $solicitud->seguimiento()->firstOrFail()];
    }

    private function activate(User $administrator, SeguimientoAdopcion $seguimiento): User
    {
        $volunteer = User::factory()->create(['rol' => 'voluntario']);
        $seguimiento->update(['voluntario_id' => $volunteer->id, 'estado' => 'activo', 'fecha_inicio' => now()]);
        return $volunteer;
    }

    private function visitData(array $data = []): array { return array_merge(['fecha_programada' => now()->toDateString()], $data); }
    private function incidentData(array $data = []): array { return array_merge(['tipo' => 'salud', 'descripcion' => 'Revisión necesaria'], $data); }
}
