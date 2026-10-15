<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\SeguimientoAdopcion;
use App\Models\SolicitudAdopcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Tests\Support\CuestionarioAdopcionDatos;
use Tests\TestCase;

/**
 * Fechas con la configuración corregida: Laravel y la sesión de PostgreSQL en America/La_Paz.
 * Se omite mientras el proyecto siga con la configuración anterior (Laravel en UTC).
 */
class ZonaHorariaTest extends TestCase
{
    use RefreshDatabase;

    private const ZONA = 'America/La_Paz';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pol_bolivia_test', DB::connection()->getDatabaseName());

        if (config('app.timezone') !== self::ZONA || DB::selectOne('show timezone')->TimeZone !== self::ZONA) {
            $this->markTestSkipped('Requiere APP_TIMEZONE y DB_TIMEZONE = America/La_Paz (configuración corregida).');
        }
    }

    public function test_cerca_de_la_medianoche_se_guarda_el_instante_correcto(): void
    {
        foreach (['2026-10-13 23:59:30' => '2026-10-13', '2026-10-14 00:00:30' => '2026-10-14'] as $local => $dia) {
            $this->travelTo(Carbon::parse($local, self::ZONA));
            $usuario = User::factory()->create();

            $guardado = DB::selectOne("select to_char(created_at at time zone 'UTC', 'YYYY-MM-DD HH24:MI:SS') utc from usuarios where id = ?", [$usuario->id])->utc;

            $this->assertSame(Carbon::parse($local, self::ZONA)->utc()->format('Y-m-d H:i:s'), $guardado);
            $this->assertTrue(User::whereKey($usuario->id)->whereDate('created_at', $dia)->exists(), "whereDate {$dia}");
            $this->assertSame($local, User::find($usuario->id)->created_at->format('Y-m-d H:i:s'));
        }
    }

    public function test_comparaciones_de_umbral_por_dias(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 00:30:00', self::ZONA));
        $hoy = now()->startOfDay();

        $hace7 = User::factory()->create(['created_at' => $hoy->copy()->subDays(7)->setTime(23, 50)]); // 13/10 23:50
        $hace8 = User::factory()->create(['created_at' => $hoy->copy()->subDays(8)->setTime(0, 10)]);  // 12/10 00:10

        $antiguos = User::where('created_at', '<', $hoy->copy()->subDays(7))->pluck('id')->all();

        $this->assertNotContains($hace7->id, $antiguos);
        $this->assertContains($hace8->id, $antiguos);
    }

    public function test_el_enlace_de_recuperacion_caduca_a_los_30_minutos(): void
    {
        $inicio = Carbon::parse('2026-10-13 23:45:00', self::ZONA);
        $this->travelTo($inicio);
        $usuario = User::factory()->create();
        $token = Password::createToken($usuario);

        $this->travelTo($inicio->copy()->addSeconds(30 * 60 - 1));
        $this->assertTrue(Password::tokenExists($usuario, $token), 'válido a los 29:59');

        $this->travelTo($inicio->copy()->addSeconds(30 * 60 + 1));
        $this->assertFalse(Password::tokenExists($usuario, $token), 'caducado a los 30:01');
    }

    public function test_el_reenvio_del_enlace_se_permite_tras_60_segundos(): void
    {
        $inicio = Carbon::parse('2026-10-13 12:00:00', self::ZONA);
        $this->travelTo($inicio);
        $usuario = User::factory()->create();
        Password::createToken($usuario);

        $this->travelTo($inicio->copy()->addSeconds(59));
        $this->assertTrue(Password::getRepository()->recentlyCreatedToken($usuario), 'bloqueado a los 59 s');

        $this->travelTo($inicio->copy()->addSeconds(61));
        $this->assertFalse(Password::getRepository()->recentlyCreatedToken($usuario), 'permitido a los 61 s');
    }

    public function test_una_fecha_introducida_por_la_persona_conserva_su_dia_local(): void
    {
        $r = DB::selectOne("select to_char(?::timestamptz at time zone 'America/La_Paz', 'YYYY-MM-DD HH24:MI') local", ['2026-10-13']);

        $this->assertSame('2026-10-13 00:00', $r->local);
    }

    public function test_fechas_de_triggers_y_de_laravel_coinciden(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $mascota = DB::transaction(function () use ($admin) {
            $mascota = Mascota::create(['nombre' => 'Zona', 'especie' => 'perro', 'raza' => 'Mestiza', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $admin->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'mascotas/p.jpg', 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
        $solicitud = SolicitudAdopcion::create(['solicitante_id' => User::factory()->create(['rol' => 'ciudadano'])->id, 'mascota_id' => $mascota->id, ...CuestionarioAdopcionDatos::registro(), 'estado' => 'pendiente']);
        $solicitud->update(['estado' => 'aceptada']);
        $seguimiento = SeguimientoAdopcion::where('solicitud_id', $solicitud->id)->sole();

        // created_at del seguimiento lo pone PostgreSQL; el de la solicitud, Laravel.
        $diferencia = DB::selectOne('select abs(extract(epoch from (s.created_at - sol.created_at))) seg from seguimientos_adopcion s join solicitudes_adopcion sol on sol.id = s.solicitud_id where s.id = ?', [$seguimiento->id])->seg;

        $this->assertLessThan(60, (float) $diferencia);
    }

    public function test_current_date_y_date_usan_el_dia_de_bolivia(): void
    {
        $r = DB::selectOne("select current_date::text hoy, (now() at time zone 'America/La_Paz')::date::text esperado");

        $this->assertSame($r->esperado, $r->hoy);
    }
}
