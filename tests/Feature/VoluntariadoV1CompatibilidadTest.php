<?php

namespace Tests\Feature;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\PostulacionVoluntariado;
use App\Models\User;
use App\Models\Voluntario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CuestionarioVoluntariadoDatos as Datos;
use Tests\TestCase;

/**
 * Compatibilidad del cuestionario de voluntariado V1 con los datos existentes:
 * postulaciones del formulario anterior, conservación de habilidades al reactivar,
 * filtros del administrador, límites de validación, migraciones y estructura
 * del indicador de progreso compartido con el cuestionario de adopción.
 */
class VoluntariadoV1CompatibilidadTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION_V1 = 'database/migrations/2026_10_12_000000_add_cuestionario_v1_to_postulaciones_voluntariado.php';

    private const MIGRACION_HABILIDADES = 'database/migrations/2026_10_12_000100_conservar_habilidades_al_reactivar_voluntario.php';

    // ---------- Postulaciones del formulario anterior (problema A) ----------

    public function test_postulacion_anterior_no_muestra_sus_datos_como_respuestas_v1(): void
    {
        $admin = $this->administrador();
        $p = $this->postulacionAnterior([
            'motivacion' => 'MOTIVACION-ANTIGUA',
            'habilidades' => 'HABILIDADES-ANTIGUAS',
            'disponibilidad' => 'DISPONIBILIDAD-ANTIGUA',
            'zona_preferencia' => 'ZONA-ANTIGUA',
        ]);

        foreach ([
            $this->actingAs($admin)->get(route('admin.postulaciones-voluntariado.show', $p)),
            $this->actingAs($p->usuario)->get(route('postulaciones-voluntariado.show', $p)),
        ] as $respuesta) {
            $respuesta->assertOk();
            [$v1, $anterior] = $this->secciones($respuesta->getContent());

            // Las 10 preguntas V1 aparecen, todas sin respuesta.
            foreach (range(1, 10) as $n) {
                $this->assertStringContainsString(">{$n}.</span>", $v1, "Falta la pregunta {$n}.");
            }
            $this->assertSame(10, substr_count($v1, '>—</dd>'));

            // Ningún dato antiguo dentro del cuestionario V1…
            foreach (['MOTIVACION-ANTIGUA', 'HABILIDADES-ANTIGUAS', 'DISPONIBILIDAD-ANTIGUA', 'ZONA-ANTIGUA'] as $dato) {
                $this->assertStringNotContainsString($dato, $v1);
                // …y todos presentes en el bloque separado.
                $this->assertStringContainsString($dato, $anterior);
            }
            $this->assertStringContainsString('Datos de la postulación anterior', $anterior);
        }

        // Los datos históricos no se modificaron al mostrarlos.
        $this->assertSame('MOTIVACION-ANTIGUA', $p->fresh()->motivacion);
        $this->assertNull($p->fresh()->version_cuestionario);
    }

    public function test_postulacion_anterior_sin_habilidades_muestra_solo_los_datos_existentes(): void
    {
        $admin = $this->administrador();
        $p = $this->postulacionAnterior([
            'motivacion' => 'MOTIVACION-SIN-HABILIDADES',
            'habilidades' => null,
            'disponibilidad' => 'DISPONIBILIDAD-SIN-HABILIDADES',
            'zona_preferencia' => 'ZONA-SIN-HABILIDADES',
        ]);

        foreach ([
            $this->actingAs($admin)->get(route('admin.postulaciones-voluntariado.show', $p)),
            $this->actingAs($p->usuario)->get(route('postulaciones-voluntariado.show', $p)),
        ] as $respuesta) {
            $respuesta->assertOk();
            [$v1, $anterior] = $this->secciones($respuesta->getContent());

            // Cuestionario V1: las 10 preguntas sin respuesta; ninguna inventada (ni "No indicó").
            $this->assertSame(10, substr_count($v1, '>—</dd>'));
            $this->assertStringNotContainsString('No indicó', $v1);
            foreach (['MOTIVACION-SIN-HABILIDADES', 'DISPONIBILIDAD-SIN-HABILIDADES', 'ZONA-SIN-HABILIDADES'] as $dato) {
                $this->assertStringNotContainsString($dato, $v1);
                $this->assertStringContainsString($dato, $anterior);
            }

            // Bloque anterior: aparecen los 3 datos existentes y no aparece el campo vacío.
            $this->assertSame(3, substr_count($anterior, '<dt class="text-gray-500">'));
            $this->assertStringContainsString('>Motivación</dt>', $anterior);
            $this->assertStringContainsString('>Disponibilidad</dt>', $anterior);
            $this->assertStringContainsString('>Zona de preferencia</dt>', $anterior);
            $this->assertStringNotContainsString('>Habilidades</dt>', $anterior);
        }

        $this->assertNull($p->fresh()->habilidades);
    }

    public function test_postulacion_v1_no_muestra_bloque_de_datos_anteriores(): void
    {
        $ciudadano = $this->ciudadano();
        $this->actingAs($ciudadano)->post(route('postulaciones-voluntariado.store'), Datos::valido(['habilidades' => '']));
        $p = PostulacionVoluntariado::sole();

        $html = $this->actingAs($this->administrador())->get(route('admin.postulaciones-voluntariado.show', $p))->assertOk()->getContent();

        $this->assertStringNotContainsString('Datos de la postulación anterior', $html);
        $this->assertStringContainsString('Paseos, Ferias de adopción', $html);
        $this->assertStringContainsString('No indicó (opcional)', $html);
        // Pregunta 3 no aplica (sin experiencia): no se muestra.
        $this->assertStringNotContainsString('>3.</span>', $html);
    }

    // ---------- Habilidades al aprobar (problema B) ----------

    public function test_alta_de_voluntario_nuevo_con_habilidades_vacias(): void
    {
        $perfil = $this->postularYAprobar($this->ciudadano(), '');

        $this->assertNull($perfil->habilidades);
    }

    public function test_reactivacion_con_habilidades_vacias_conserva_las_anteriores(): void
    {
        $ciudadano = $this->ciudadano();
        $this->postularYAprobar($ciudadano, 'Fotografía y primeros auxilios');
        $this->desactivar($ciudadano);

        foreach (['', '   '] as $vacio) {
            $perfil = $this->postularYAprobar($ciudadano->fresh(), $vacio);
            $this->assertSame('Fotografía y primeros auxilios', $perfil->habilidades);
            $this->desactivar($ciudadano);
        }

        $this->assertSame(1, Voluntario::where('usuario_id', $ciudadano->id)->count());
    }

    public function test_reactivacion_con_habilidades_nuevas_las_actualiza(): void
    {
        $ciudadano = $this->ciudadano();
        $this->postularYAprobar($ciudadano, 'Fotografía');
        $this->desactivar($ciudadano);

        $perfil = $this->postularYAprobar($ciudadano->fresh(), 'Diseño gráfico', ['dias_disponibles' => ['lunes'], 'franjas_horarias' => ['noche'], 'zona_preferencia' => 'Zona Sur']);

        $this->assertSame('Diseño gráfico', $perfil->habilidades);
        // El resto del perfil se actualiza como antes.
        $this->assertSame('Lunes · Noche', $perfil->disponibilidad);
        $this->assertSame('Zona Sur', $perfil->zona_preferencia);
        $this->assertSame('activo', $perfil->estado);
        $this->assertSame(PostulacionVoluntariado::where('usuario_id', $ciudadano->id)->latest('id')->value('id'), $perfil->postulacion_id);
    }

    public function test_reactivacion_con_espacios_en_blanco_insertados_en_la_base_conserva_las_habilidades(): void
    {
        $ciudadano = $this->ciudadano();
        $this->postularYAprobar($ciudadano, 'Fotografía');

        // Valores que solo contienen espacios en blanco, insertados directamente en PostgreSQL
        // (sin pasar por el formulario ni por la limpieza de Laravel).
        foreach ([
            'tabulador' => "\t",
            'saltos de línea' => "\n\r\n",
            'mezcla' => " \t\n ",
            'espacio duro' => "\u{00A0}",
            'ancho cero' => "\u{200B}",
            'BOM y tabulador' => "\u{FEFF}\t",
            'marca de dirección' => "\u{200E}",
        ] as $caso => $soloEspacios) {
            $this->desactivar($ciudadano);
            $this->aprobar($this->postulacionDirecta($ciudadano, $soloEspacios));

            $this->assertSame('Fotografía', Voluntario::where('usuario_id', $ciudadano->id)->sole()->habilidades, "Caso: {$caso}");
        }

        // Un texto real con espacios en los bordes sí reemplaza las habilidades (sin los bordes).
        $this->desactivar($ciudadano);
        $this->aprobar($this->postulacionDirecta($ciudadano, "\t  Diseño gráfico\n"));
        $this->assertSame('Diseño gráfico', Voluntario::where('usuario_id', $ciudadano->id)->sole()->habilidades);
    }

    public function test_migracion_de_espacios_unicode_down_vuelve_a_btrim(): void
    {
        $ciudadano = $this->ciudadano();
        $this->postularYAprobar($ciudadano, 'Fotografía');
        $migracion = require base_path('database/migrations/2026_10_12_000200_considerar_espacios_unicode_vacios_en_habilidades.php');

        // Con la versión anterior (btrim), un tabulador no se considera vacío y reemplaza las habilidades.
        $migracion->down();
        $this->desactivar($ciudadano);
        $this->aprobar($this->postulacionDirecta($ciudadano, "\t"));
        $this->assertSame("\t", Voluntario::where('usuario_id', $ciudadano->id)->sole()->habilidades);

        // Con esta migración, el mismo valor se considera vacío y se conservan.
        Voluntario::where('usuario_id', $ciudadano->id)->update(['habilidades' => 'Fotografía']);
        $migracion->up();
        $this->desactivar($ciudadano);
        $this->aprobar($this->postulacionDirecta($ciudadano, "\t"));
        $this->assertSame('Fotografía', Voluntario::where('usuario_id', $ciudadano->id)->sole()->habilidades);
    }

    // ---------- Filtros del administrador ----------

    public function test_filtros_individuales_y_combinados(): void
    {
        $admin = $this->administrador();
        $this->crear('Ana', ['areas_interes' => ['rescate', 'paseos'], 'dias_disponibles' => ['lunes']], 'pendiente');
        $this->crear('Beto', ['areas_interes' => ['rescate'], 'dias_disponibles' => ['sabado']], 'pendiente');
        $this->crear('Cata', ['areas_interes' => ['paseos'], 'dias_disponibles' => ['sabado']], 'rechazada');
        $this->postulacionAnterior(['usuario_id' => User::factory()->create(['rol' => 'ciudadano', 'nombres' => 'Dani'])->id]);

        $nombres = fn (array $filtros) => collect(['Ana', 'Beto', 'Cata', 'Dani'])
            ->filter(fn ($n) => str_contains($this->actingAs($admin)->get(route('admin.postulaciones-voluntariado.index', $filtros))->assertOk()->getContent(), ">{$n} "))
            ->values()->all();

        $this->assertSame(['Ana', 'Beto', 'Cata', 'Dani'], $nombres([]));
        $this->assertSame(['Ana', 'Beto'], $nombres(['area' => 'rescate']));
        $this->assertSame(['Beto', 'Cata'], $nombres(['dia' => 'sabado']));
        $this->assertSame(['Cata'], $nombres(['estado' => 'rechazada']));
        $this->assertSame(['Beto'], $nombres(['area' => 'rescate', 'dia' => 'sabado']));
        $this->assertSame(['Cata'], $nombres(['area' => 'paseos', 'dia' => 'sabado', 'estado' => 'rechazada']));
        $this->assertSame([], $nombres(['area' => 'rescate', 'estado' => 'rechazada']));
    }

    public function test_filtros_rechazan_valores_invalidos_y_no_cambian_datos(): void
    {
        $admin = $this->administrador();
        $this->crear('Eva', ['areas_interes' => ['rescate']], 'pendiente');
        $antes = PostulacionVoluntariado::pluck('estado', 'id')->all();

        foreach ([['area' => 'cocina'], ['dia' => 'feriado'], ['estado' => 'borrada'], ['area' => "rescate' OR '1'='1"], ['dia' => ['lunes']]] as $invalido) {
            $this->actingAs($admin)->get(route('admin.postulaciones-voluntariado.index', $invalido))->assertSessionHasErrors(array_keys($invalido));
        }

        $this->assertSame($antes, PostulacionVoluntariado::pluck('estado', 'id')->all());
        $this->assertSame(0, Voluntario::count());
    }

    // ---------- Validaciones: condicionales, opcionales y límites (problema C) ----------

    public function test_pregunta_3_condicional(): void
    {
        $this->actingAs($this->ciudadano())->post(route('postulaciones-voluntariado.store'), Datos::valido(['experiencia_previa' => 'si', 'experiencia_detalle' => '   ']))
            ->assertSessionHasErrors('experiencia_detalle');

        $this->actingAs($this->ciudadano())->post(route('postulaciones-voluntariado.store'), Datos::valido(['experiencia_previa' => 'si', 'experiencia_detalle' => 'Un año en un refugio']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Un año en un refugio', PostulacionVoluntariado::sole()->experiencia_detalle);
    }

    public function test_pregunta_8_opcional_con_solo_espacios_se_guarda_vacia(): void
    {
        $this->actingAs($this->ciudadano())->post(route('postulaciones-voluntariado.store'), Datos::valido(['habilidades' => '   ']))
            ->assertSessionHasNoErrors();

        $this->assertNull(PostulacionVoluntariado::sole()->habilidades);
    }

    public function test_limites_de_motivacion_y_habilidades_son_los_del_formulario_anterior(): void
    {
        // Límite previo: 5000 caracteres en ambos campos.
        $this->actingAs($this->ciudadano())->post(route('postulaciones-voluntariado.store'), Datos::valido(['motivacion' => str_repeat('a', 5000), 'habilidades' => str_repeat('b', 5000)]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->ciudadano())->post(route('postulaciones-voluntariado.store'), Datos::valido(['motivacion' => str_repeat('a', 5001)]))
            ->assertSessionHasErrors(['motivacion' => 'La motivación no puede superar los 5000 caracteres.']);
        $this->actingAs($this->ciudadano())->post(route('postulaciones-voluntariado.store'), Datos::valido(['habilidades' => str_repeat('b', 5001)]))
            ->assertSessionHasErrors(['habilidades' => 'Las habilidades no pueden superar los 5000 caracteres.']);

        $this->assertSame(1, PostulacionVoluntariado::count());
    }

    // ---------- Migraciones ----------

    public function test_migracion_v1_down_y_up_conservan_los_datos_anteriores(): void
    {
        $anterior = $this->postulacionAnterior(['motivacion' => 'Texto previo']);
        $this->aprobar($anterior);
        $columnasPrevias = ['id', 'usuario_id', 'motivacion', 'habilidades', 'disponibilidad', 'zona_preferencia', 'estado', 'revisado_por'];
        $foto = fn () => [
            DB::table('postulaciones_voluntariado')->orderBy('id')->get($columnasPrevias)->toArray(),
            DB::table('voluntarios')->orderBy('id')->get()->toArray(),
        ];
        $antes = $foto();
        $migracion = require base_path(self::MIGRACION_V1);

        $migracion->down();
        $this->assertFalse(Schema::hasColumn('postulaciones_voluntariado', 'areas_interes'));
        $this->assertEquals($antes, $foto());

        $migracion->up();
        foreach (['experiencia_previa', 'experiencia_detalle', 'areas_interes', 'dias_disponibles', 'franjas_horarias', 'confirma_mayor_edad', 'declara_informacion_veraz', 'version_cuestionario'] as $columna) {
            $this->assertTrue(Schema::hasColumn('postulaciones_voluntariado', $columna), "Falta {$columna}");
        }
        $this->assertEquals($antes, $foto());
        $this->assertNull(PostulacionVoluntariado::find($anterior->id)->version_cuestionario);
    }

    public function test_migracion_de_habilidades_down_restaura_la_regla_original(): void
    {
        $ciudadano = $this->ciudadano();
        $this->postularYAprobar($ciudadano, 'Fotografía');
        $this->desactivar($ciudadano);
        $migracion = require base_path(self::MIGRACION_HABILIDADES);

        // Con la regla original, una postulación sin habilidades las borra del perfil.
        $migracion->down();
        $this->assertNull($this->postularYAprobar($ciudadano->fresh(), '')->habilidades);

        // Con la corrección, se conservan.
        $this->actingAs($this->administrador())->patch(route('admin.voluntarios.deactivate', Voluntario::where('usuario_id', $ciudadano->id)->sole()))->assertRedirect();
        Voluntario::where('usuario_id', $ciudadano->id)->update(['habilidades' => 'Fotografía']);
        $migracion->up();
        $this->assertSame('Fotografía', $this->postularYAprobar($ciudadano->fresh(), '')->habilidades);
    }

    // ---------- Indicador de progreso compartido ----------

    public function test_ambos_cuestionarios_usan_el_mismo_indicador_de_progreso(): void
    {
        $mascota = $this->mascota();
        $ciudadano = $this->ciudadano();
        $voluntariado = $this->actingAs($ciudadano)->get(route('postulaciones-voluntariado.create'))->assertOk()->getContent();
        $adopcion = $this->actingAs($ciudadano)->get(route('solicitudes-adopcion.create', $mascota))->assertOk()->getContent();

        foreach (['voluntariado' => [$voluntariado, 10], 'adopcion' => [$adopcion, 26]] as $nombre => [$html, $total]) {
            // Un solo script compartido, con nonce para la política de seguridad, y el formulario lo usa.
            $this->assertSame(1, substr_count($html, 'window.cuestionarioFormulario ='), $nombre);
            $this->assertMatchesRegularExpression('/<script nonce="[^"]+">\s*window\.cuestionarioFormulario/', $html, $nombre);
            $this->assertStringContainsString('x-data="cuestionarioFormulario(', $html, $nombre);
            $this->assertStringContainsString('x-text="texto"', $html, $nombre);

            preg_match_all('/data-num="(\d+)"/', $html, $numeros);
            $this->assertSame(range(1, $total), array_values(array_unique(array_map('intval', $numeros[1]))), $nombre);
        }

        // Solo la pregunta 8 de voluntariado es opcional por completo; adopción no tiene preguntas opcionales numeradas.
        $this->assertSame(1, preg_match_all('/data-num="8"[^>]*data-opcional|data-opcional[^>]*data-num="8"/', $voluntariado));
        $this->assertSame(0, preg_match_all('/<div[^>]*data-opcional[^>]*data-num=|<div[^>]*data-num="\d+"[^>]*data-opcional/', $adopcion));
    }

    // ---------- Ayudantes ----------

    /** Devuelve [bloque del cuestionario V1, bloque de datos anteriores] del HTML. */
    private function secciones(string $html): array
    {
        $inicioV1 = strpos($html, 'data-seccion="cuestionario-v1"');
        $inicioAnterior = strpos($html, 'data-seccion="postulacion-anterior"');
        $this->assertNotFalse($inicioV1, 'Falta el bloque del cuestionario V1.');
        $this->assertNotFalse($inicioAnterior, 'Falta el bloque "Datos de la postulación anterior".');
        $this->assertGreaterThan($inicioV1, $inicioAnterior);

        return [substr($html, $inicioV1, $inicioAnterior - $inicioV1), substr($html, $inicioAnterior)];
    }

    private function postulacionAnterior(array $datos = []): PostulacionVoluntariado
    {
        return PostulacionVoluntariado::create(array_replace([
            'usuario_id' => $this->ciudadano()->id,
            'estado' => 'pendiente',
            'motivacion' => 'Ayudar',
            'habilidades' => 'Cocinar',
            'disponibilidad' => 'Fines de semana',
            'zona_preferencia' => 'Centro',
        ], $datos));
    }

    private function postularYAprobar(User $ciudadano, string $habilidades, array $cambios = []): Voluntario
    {
        $this->actingAs($ciudadano)->post(route('postulaciones-voluntariado.store'), Datos::valido(['habilidades' => $habilidades, ...$cambios]))
            ->assertSessionHasNoErrors();
        $this->aprobar(PostulacionVoluntariado::where('usuario_id', $ciudadano->id)->latest('id')->firstOrFail());

        return Voluntario::where('usuario_id', $ciudadano->id)->sole();
    }

    /** Inserta una postulación V1 directamente en la base, sin formulario ni limpieza de Laravel. */
    private function postulacionDirecta(User $ciudadano, string $habilidades): PostulacionVoluntariado
    {
        $datos = Datos::registro(['habilidades' => $habilidades]);
        foreach (['areas_interes', 'dias_disponibles', 'franjas_horarias'] as $json) {
            $datos[$json] = json_encode($datos[$json]);
        }
        $id = DB::table('postulaciones_voluntariado')->insertGetId([
            ...$datos,
            'usuario_id' => $ciudadano->id,
            'estado' => 'pendiente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return PostulacionVoluntariado::findOrFail($id);
    }

    private function aprobar(PostulacionVoluntariado $p): void
    {
        $this->actingAs($this->administrador())
            ->patch(route('admin.postulaciones-voluntariado.approve', $p), ['comentarios_admin' => 'Aprobada'])
            ->assertRedirect();
    }

    private function desactivar(User $ciudadano): void
    {
        $this->actingAs($this->administrador())
            ->patch(route('admin.voluntarios.deactivate', Voluntario::where('usuario_id', $ciudadano->id)->sole()))
            ->assertRedirect();
    }

    private function crear(string $nombre, array $cambios, string $estado): void
    {
        $p = PostulacionVoluntariado::create([...Datos::registro($cambios), 'usuario_id' => User::factory()->create(['rol' => 'ciudadano', 'nombres' => $nombre])->id, 'estado' => 'pendiente']);
        if ($estado !== 'pendiente') {
            $p->forceFill(['estado' => $estado, 'revisado_por' => $this->administrador()->id, 'revisado_at' => now()])->save();
        }
    }

    private function ciudadano(): User
    {
        return User::factory()->create(['rol' => 'ciudadano']);
    }

    private function administrador(): User
    {
        return User::factory()->create(['rol' => 'administrador']);
    }

    private function mascota(): Mascota
    {
        $admin = $this->administrador();

        return DB::transaction(function () use ($admin): Mascota {
            $mascota = Mascota::create(['nombre' => 'Canela', 'especie' => 'perro', 'raza' => 'Mestiza', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'hembra', 'tamano' => 'mediano', 'estado' => 'disponible', 'registrado_por' => $admin->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => "mascotas/{$mascota->id}.jpg", 'es_principal' => true, 'orden' => 1]);

            return $mascota;
        });
    }
}
