<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * auditorias.created_at guarda siempre el instante en UTC (registros históricos y nuevos) y se
 * presenta en la zona de la aplicación. Se ejecuta con la configuración actual y con la corregida.
 */
class AuditoriaZonaHorariaTest extends TestCase
{
    use RefreshDatabase;

    /** 13/10 23:30 en La Paz = 14/10 03:30 UTC: cerca de la medianoche boliviana. */
    private const INSTANTE_UTC = '2026-10-14 03:30:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('pol_bolivia_test', DB::connection()->getDatabaseName());
        $this->travelTo(Carbon::parse(self::INSTANTE_UTC, 'UTC'));
    }

    public function test_un_registro_nuevo_se_guarda_con_el_instante_en_utc(): void
    {
        $usuario = User::factory()->create();

        $texto = DB::table('auditorias')->where('modelo', 'User')->where('modelo_id', $usuario->id)->where('accion', 'creado')->value('created_at');

        $this->assertSame(self::INSTANTE_UTC, substr($texto, 0, 19));
    }

    public function test_se_presenta_en_la_zona_de_la_aplicacion_sin_cambiar_el_instante(): void
    {
        $usuario = User::factory()->create();
        $auditoria = Auditoria::where('modelo', 'User')->where('modelo_id', $usuario->id)->sole();

        $this->assertSame(config('app.timezone'), $auditoria->created_at->tzName);
        $this->assertSame(Carbon::parse(self::INSTANTE_UTC, 'UTC')->getTimestamp(), $auditoria->created_at->getTimestamp());
    }

    public function test_historicos_y_nuevos_se_ordenan_cronologicamente(): void
    {
        // Histórico ya guardado en UTC, 30 minutos antes (como los registros existentes).
        DB::table('auditorias')->insert(['accion' => 'creado', 'modelo' => 'Historico', 'modelo_id' => 1, 'created_at' => '2026-10-14 03:00:00']);
        $usuario = User::factory()->create();

        $orden = Auditoria::query()->whereIn('modelo', ['Historico', 'User'])->oldest('created_at')->oldest('id')->pluck('modelo')->all();

        $this->assertSame(['Historico', 'User'], $orden);
        $this->assertSame('User', Auditoria::query()->latest('created_at')->latest('id')->value('modelo'));
    }

    public function test_los_registros_historicos_no_cambian(): void
    {
        DB::table('auditorias')->insert(['accion' => 'creado', 'modelo' => 'Historico', 'modelo_id' => 1, 'created_at' => '2026-09-03 20:54:23']);
        $antes = DB::table('auditorias')->where('modelo', 'Historico')->value('created_at');

        User::factory()->count(2)->create();

        $this->assertSame($antes, DB::table('auditorias')->where('modelo', 'Historico')->value('created_at'));
        $this->assertSame('2026-09-03 20:54:23', substr($antes, 0, 19));
    }

    public function test_la_pantalla_de_auditoria_muestra_la_hora_de_la_zona_de_la_aplicacion(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $esperado = Carbon::parse(self::INSTANTE_UTC, 'UTC')->setTimezone(config('app.timezone'))->format('d/m/Y H:i');

        $this->actingAs($admin)->get(route('admin.auditoria.index'))
            ->assertOk()
            ->assertSee($esperado);
    }
}
