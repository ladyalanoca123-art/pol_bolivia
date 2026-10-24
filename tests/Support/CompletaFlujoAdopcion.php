<?php

namespace Tests\Support;

use App\Models\SolicitudAdopcion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait CompletaFlujoAdopcion
{
    protected function completarAdopcionPorRutas(User $administrador, SolicitudAdopcion $solicitud): void
    {
        if ($solicitud->fresh()->estado === 'pendiente') {
            $this->actingAs($administrador)
                ->patch(route('admin.solicitudes-adopcion.evaluate', $solicitud), ['comentarios_admin' => 'Inicio de evaluación.'])
                ->assertRedirect();
        }

        $this->actingAs($administrador)
            ->patch(route('admin.solicitudes-adopcion.approve', $solicitud), [])
            ->assertRedirect();

        $this->actingAs($administrador)
            ->patch(route('admin.solicitudes-adopcion.deliver', $solicitud), [
                'fecha_entrega' => now()->toDateString(),
                'compromiso_firmado' => '1',
            ])
            ->assertRedirect();
    }

    protected function completarAdopcionConTransicionesDeBase(SolicitudAdopcion $solicitud): void
    {
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['estado' => 'en_espera']);
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update(['estado' => 'aprobada']);
        DB::table('solicitudes_adopcion')->where('id', $solicitud->id)->update([
            'estado' => 'aceptada',
            'compromiso_firmado' => true,
            'entregada_at' => DB::selectOne('select CURRENT_TIMESTAMP as ahora')->ahora,
        ]);
    }
}
