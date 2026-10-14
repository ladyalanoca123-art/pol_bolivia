<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Comando zona-horaria:diagnosticar: clasifica las fechas históricas y no modifica nada.
 * Funciona con la configuración actual y con la corregida (La Paz).
 */
class DiagnosticarZonaHorariaCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Además del freno de Tests\TestCase: los datos solo se escriben en la base de pruebas.
        $this->assertSame('pol_bolivia_test', DB::connection()->getDatabaseName());
    }

    public function test_un_registro_de_laravel_con_auditoria_se_clasifica_segun_la_evidencia(): void
    {
        $this->travelTo(Carbon::parse('2026-10-13 12:00:00', 'UTC'));
        $usuario = User::factory()->create();

        // Con la configuración actual (UTC frente a la sesión de La Paz) queda +4 h;
        // con la configuración corregida queda bien (0 h).
        $esperado = config('app.timezone') === 'UTC' && DB::selectOne('show timezone')->TimeZone !== 'UTC' ? 'confirmado' : 'correcto';

        $this->assertSame($esperado, $this->categoria('usuarios.created_at', $usuario->id));
    }

    public function test_sin_fraccion_y_sin_auditoria_es_sospechoso_y_no_se_propone_corregir(): void
    {
        $id = DB::table('usuarios')->insertGetId([
            'nombres' => 'Sin', 'apellidos' => 'Evento', 'email' => 'sin.evento@example.test',
            'password' => 'x', 'created_at' => '2026-09-01 10:00:00', 'updated_at' => '2026-09-01 10:00:00',
        ]);

        $this->assertSame('sospechoso', $this->categoria('usuarios.created_at', $id));
        $this->assertMatchesRegularExpression('/usuarios\.created_at\s*\|\s*'.$id.'\s*\|[^\n]*excluido de la corrección/', $this->salida());
    }

    public function test_con_microsegundos_y_sin_auditoria_es_indeterminado(): void
    {
        // Valor por defecto CURRENT_TIMESTAMP (con microsegundos) y sin evento de auditoría.
        $id = DB::table('usuarios')->insertGetId([
            'nombres' => 'Por', 'apellidos' => 'Defecto', 'email' => 'defecto@example.test', 'password' => 'x',
        ]);

        $this->assertSame('indeterminado', $this->categoria('usuarios.created_at', $id));
    }

    public function test_un_valor_de_postgresql_con_auditoria_coincidente_es_correcto(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $foto = DB::transaction(function () use ($admin) {
            $mascota = Mascota::create(['nombre' => 'Diag', 'especie' => 'perro', 'raza' => 'Mestiza', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $admin->id]);

            return FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => 'mascotas/d.jpg', 'es_principal' => true, 'orden' => 1]);
        });

        $this->assertSame('correcto', $this->categoria('fotos_mascota.created_at', $foto->id));
    }

    public function test_evidencia_imposible_para_el_mecanismo_es_conflicto(): void
    {
        // Valor con microsegundos (PostgreSQL) cuya auditoría indica +4 h: contradicción.
        $id = DB::table('usuarios')->insertGetId([
            'nombres' => 'Con', 'apellidos' => 'Conflicto', 'email' => 'conflicto@example.test', 'password' => 'x',
        ]);
        $instante = DB::selectOne("select to_char((created_at - interval '4 hours') at time zone 'UTC', 'YYYY-MM-DD HH24:MI:SS') t from usuarios where id = ?", [$id])->t;
        DB::table('auditorias')->insert(['accion' => 'creado', 'modelo' => 'User', 'modelo_id' => $id, 'created_at' => $instante]);

        $this->assertSame('conflicto', $this->categoria('usuarios.created_at', $id));
    }

    public function test_no_modifica_ningun_registro(): void
    {
        User::factory()->count(3)->create();
        $antes = $this->huella();

        $this->salida();

        $this->assertSame($antes, $this->huella());
    }

    public function test_una_transaccion_de_solo_lectura_rechaza_escrituras(): void
    {
        // Garantía en la que se apoya el comando: dentro de su transacción READ ONLY, PostgreSQL
        // rechaza cualquier escritura.
        DB::beginTransaction();
        DB::statement('SET TRANSACTION READ ONLY');
        try {
            $this->expectException(\Illuminate\Database\QueryException::class);
            DB::table('usuarios')->insert(['nombres' => 'X', 'apellidos' => 'Y', 'email' => 'ro@example.test', 'password' => 'x']);
        } finally {
            DB::rollBack();
        }
    }

    private function salida(): string
    {
        Artisan::call('zona-horaria:diagnosticar', ['--detalle' => true]);

        return Artisan::output();
    }

    /** Categoría de una fila en el detalle del comando (lista todas las filas con valor). */
    private function categoria(string $columna, int $id): string
    {
        preg_match('/'.preg_quote($columna, '/').'\s*\|\s*'.$id.'\s*\|\s*([a-z]+)/', $this->salida(), $m);

        return $m[1] ?? 'no listado';
    }

    private function huella(): string
    {
        return collect(['usuarios', 'auditorias', 'password_reset_tokens'])
            ->map(fn ($tabla) => DB::selectOne("select md5(coalesce(string_agg(t::text, chr(10) order by t::text), '')) h from {$tabla} t")->h)
            ->implode('|');
    }
}
