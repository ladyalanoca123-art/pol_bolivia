<?php

namespace Tests\Feature;

use App\Support\ZonaHoraria\AnexoColumnas;
use App\Support\ZonaHoraria\PlanReparacion;
use App\Support\ZonaHoraria\RegistroCambios;
use App\Support\ZonaHoraria\ReparacionZonaHoraria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Comandos zona-horaria:reparar y zona-horaria:revertir, con datos propios de pol_bolivia_test
 * que reproducen el desfase (+4 h, sin fracción de segundo) y su evidencia de auditoría.
 * El plan de prueba se construye igual que el plan de octubre de 2026 y se inyecta en el contenedor.
 */
class RepararZonaHorariaCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $carpeta;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Además del freno de Tests\TestCase: los datos solo se escriben en la base de pruebas.
        $this->assertSame('pol_bolivia_test', DB::connection()->getDatabaseName());

        $this->carpeta = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pol-zona-horaria-'.uniqid();
        $this->escenario();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);

        parent::tearDown();
    }

    public function test_por_defecto_simula_y_no_modifica_nada(): void
    {
        $antes = $this->huella();

        [$codigo, $salida] = $this->reparar();

        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('SIMULACIÓN (no modifica nada)', $salida);
        $this->assertStringContainsString('No se modificó ningún registro', $salida);
        $this->assertSame(6, preg_match_all('/\|\s*ok\s*\|/', $salida));
        $this->assertSame($antes, $this->huella());
        $this->assertDirectoryDoesNotExist($this->carpeta);
        $this->assertTriggersActivos();
    }

    public function test_sin_confirmar_el_plan_o_con_otro_hash_no_aplica(): void
    {
        $antes = $this->huella();

        foreach ([null, str_repeat('0', 64), substr($this->plan()->hash(), 0, 12)] as $hash) {
            [$codigo, $salida] = $this->reparar(['--aplicar' => true, '--confirmar-plan' => $hash, '--manifiesto' => $this->manifiesto()]);

            $this->assertSame(1, $codigo);
            $this->assertStringContainsString('--confirmar-plan no coincide', $salida);
        }

        $this->assertSame($antes, $this->huella());
        $this->assertDirectoryDoesNotExist($this->carpeta);
    }

    public function test_rechaza_una_base_distinta_de_la_conectada_o_sin_indicar(): void
    {
        $antes = $this->huella();

        [$codigo, $salida] = $this->reparar(['--base' => 'pol_bolivia', '--confirmar-base' => 'pol_bolivia'] + $this->opcionesAplicar());
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('no coincide con la base conectada (pol_bolivia_test)', $salida);

        [$codigo, $salida] = $this->reparar(['--base' => null] + $this->opcionesAplicar());
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('Indica la base', $salida);

        $this->assertSame($antes, $this->huella());
        $this->assertDirectoryDoesNotExist($this->carpeta);
    }

    public function test_no_existe_ninguna_opcion_para_incluir_filas_en_el_plan(): void
    {
        $opciones = fn (string $comando) => collect(Artisan::all()[$comando]->getDefinition()->getOptions())
            ->keys()->reject(fn ($o) => in_array($o, ['help', 'silent', 'quiet', 'verbose', 'version', 'ansi', 'no-interaction', 'env'], true))
            ->sort()->values()->all();

        $this->assertSame(['aplicar', 'base', 'confirmar-base', 'confirmar-plan', 'manifiesto'], $opciones('zona-horaria:reparar'));
        // --anexo solo aporta la lista firmada de columnas originales; no permite elegir filas.
        $this->assertSame(['anexo', 'aplicar', 'base', 'confirmar-base', 'confirmar-plan', 'manifiesto'], $opciones('zona-horaria:revertir'));
        $this->assertSame([], Artisan::all()['zona-horaria:reparar']->getDefinition()->getArguments());
    }

    public function test_aplica_solo_las_entradas_confirmadas_y_deja_registro(): void
    {
        $plan = $this->plan();

        [$codigo, $salida] = $this->reparar($this->opcionesAplicar());

        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('6 fechas corregidas', $salida);

        // Valores corregidos (−4 h exactas).
        $this->assertSame('2026-09-01 14:00:00.000000', $this->valor('usuarios', 'created_at', $this->ids['usuario']));
        $this->assertSame('2026-09-01 14:00:00.000000', $this->valor('usuarios', 'updated_at', $this->ids['usuario']));
        $this->assertSame('2026-09-04 16:00:00.000000', $this->valor('mascotas', 'created_at', $this->ids['mascota']));
        $this->assertSame('2026-09-04 16:00:00.000000', $this->valor('mascotas', 'updated_at', $this->ids['mascota']));
        $this->assertSame('2026-09-05 11:00:00.000000', $this->valor('postulaciones_voluntariado', 'created_at', $this->ids['postulacion']));
        $this->assertSame('2026-09-05 15:30:00.000000', $this->valor('postulaciones_voluntariado', 'revisado_at', $this->ids['postulacion']));

        // El trigger de updated_at estuvo desactivado: el updated_at que no está en el plan no cambió.
        $this->assertSame('2026-09-05 19:30:00.654321', $this->valor('postulaciones_voluntariado', 'updated_at', $this->ids['postulacion']));
        // Exclusiones y demás filas intactas.
        $this->assertSame('2026-09-03 12:00:00.000000', $this->valor('usuarios', 'created_at', $this->ids['excluido']));
        $this->assertSame('2026-09-03 12:00:00.000000', $this->valor('usuarios', 'updated_at', $this->ids['excluido']));
        $this->assertSame('2026-09-01 10:00:00.123456', $this->valor('usuarios', 'created_at', $this->ids['admin']));
        $this->assertTriggersActivos();

        // Auditoría: un registro por fila con los valores anterior y nuevo.
        $auditorias = DB::table('auditorias')->where('user_agent', 'zona-horaria:reparar plan:'.substr($plan->hash(), 0, 12))->orderBy('id')->get();
        $this->assertCount(3, $auditorias);
        $usuario = $auditorias->firstWhere('modelo', 'User');
        $this->assertSame('actualizado', $usuario->accion);
        $this->assertSame($this->ids['usuario'], (int) $usuario->modelo_id);
        $this->assertSame(['created_at' => '2026-09-01 18:00:00', 'updated_at' => '2026-09-01 18:00:00'], json_decode($usuario->valores_anteriores, true));
        $this->assertSame(['created_at' => '2026-09-01 14:00:00', 'updated_at' => '2026-09-01 14:00:00'], json_decode($usuario->valores_nuevos, true));

        // Registro de cambios firmado con los valores originales exactos.
        $registro = RegistroCambios::leer($this->manifiesto());
        $this->assertSame('aplicado', $registro['estado']);
        $this->assertSame('pol_bolivia_test', $registro['base']);
        $this->assertSame($plan->hash(), $registro['plan_hash']);
        $this->assertCount(6, $registro['cambios']);
        $this->assertSame(
            ['tabla' => 'postulaciones_voluntariado', 'columna' => 'revisado_at', 'id' => $this->ids['postulacion'], 'original' => '2026-09-05 19:30:00', 'nuevo' => '2026-09-05 15:30:00'],
            array_diff_key(end($registro['cambios']), ['fila_tras_cambio' => true])
        );
    }

    public function test_un_valor_distinto_del_esperado_aborta_sin_modificar_nada(): void
    {
        $this->plan();
        $this->sinTriggerUpdatedAt('usuarios', fn () => DB::update("update usuarios set created_at = '2026-09-01 18:00:01+00' where id = ?", [$this->ids['usuario']]));
        $antes = $this->huella();

        [$codigo, $salida] = $this->reparar();
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('DISCREPANCIA', $salida);

        [$codigo, $salida] = $this->reparar($this->opcionesAplicar());
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('valor actual 2026-09-01 18:00:01.000000 distinto del esperado 2026-09-01 18:00:00', $salida);
        $this->assertStringContainsString('Abortado sin modificar registros', $salida);

        $this->assertSame($antes, $this->huella());
        $this->assertTriggersActivos();
        $this->assertSame('fallido', RegistroCambios::leer($this->manifiesto())['estado']);
    }

    public function test_una_evidencia_de_auditoria_distinta_o_inexistente_aborta(): void
    {
        // La tabla auditorias no admite modificaciones (proteger_auditoria): el plan apunta a otra auditoría.
        $entradas = $this->plan()->entradas();
        $otra = DB::table('auditorias')->insertGetId(['accion' => 'creado', 'modelo' => 'Mascota', 'modelo_id' => $this->ids['mascota'], 'created_at' => '2026-09-04 16:00:05']);
        $antes = $this->huella();

        $casos = [
            [2, $otra, "la auditoría #{$otra} indica 2026-09-04 16:00:05, no 2026-09-04 16:00:00"],
            [2, $this->ids['auditoria_usuario'], "la auditoría #{$this->ids['auditoria_usuario']} no corresponde a este registro"],
            [0, 999999, 'no existe la auditoría #999999'],
        ];
        foreach ($casos as [$indice, $auditoriaId, $mensaje]) {
            $alterado = $entradas;
            $alterado[$indice]['evidencia']['auditoria_id'] = $auditoriaId;
            File::delete($this->manifiesto()); // registro del intento anterior
            $this->usarPlan(new PlanReparacion($alterado, $this->exclusiones(), 6));

            [$codigo, $salida] = $this->reparar($this->opcionesAplicar());

            $this->assertSame(1, $codigo);
            $this->assertStringContainsString($mensaje, $salida);
            $this->assertSame($antes, $this->huella());
        }
        $this->assertTriggersActivos();
    }

    public function test_un_campo_de_integridad_que_cambio_aborta(): void
    {
        $this->plan();
        $this->sinTriggerUpdatedAt('usuarios', fn () => DB::update("update usuarios set rol = 'voluntario' where id = ?", [$this->ids['usuario']]));
        $antes = $this->huella();

        [$codigo, $salida] = $this->reparar($this->opcionesAplicar());

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString("campo rol cambió ('voluntario' en lugar de 'ciudadano')", $salida);
        $this->assertSame($antes, $this->huella());
    }

    public function test_un_cambio_fuera_de_los_campos_comparados_lo_detecta_la_huella(): void
    {
        $this->plan();
        $this->sinTriggerUpdatedAt('usuarios', fn () => DB::update("update usuarios set nombres = 'Cambiado' where id = ?", [$this->ids['usuario']]));
        $antes = $this->huella();

        [$codigo, $salida] = $this->reparar($this->opcionesAplicar());

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('la fila cambió desde el diagnóstico (huella distinta)', $salida);
        $this->assertStringNotContainsString('campo ', $salida);
        $this->assertSame($antes, $this->huella());
    }

    public function test_un_trigger_de_updated_at_ya_desactivado_impide_empezar(): void
    {
        $this->plan();
        $antes = $this->huella();
        DB::statement('ALTER TABLE incidencias DISABLE TRIGGER trigger_incidencias_updated_at');

        try {
            [$codigo, $salida] = $this->reparar();
            $this->assertSame(1, $codigo);
            $this->assertStringContainsString('NO activos: trigger_incidencias_updated_at', $salida);

            [$codigo, $salida] = $this->reparar($this->opcionesAplicar());
            $this->assertSame(1, $codigo);
            $this->assertStringContainsString('Triggers no utilizables antes de empezar: Triggers de updated_at no activos: trigger_incidencias_updated_at', $salida);
            $this->assertSame($antes, $this->huella());
        } finally {
            DB::statement('ALTER TABLE incidencias ENABLE TRIGGER trigger_incidencias_updated_at');
        }
    }

    public function test_si_un_trigger_de_validacion_rechaza_el_cambio_se_deshace_todo(): void
    {
        $plan = $this->plan();
        // El revisor deja de estar activo: validar_revisor_postulacion (que sigue activo) rechaza
        // la actualización de revisado_at, que es la última entrada del plan.
        DB::update('update usuarios set estado = false where id = ?', [$this->ids['admin']]);
        $antes = $this->huella();

        [$codigo, $salida] = $this->reparar($this->opcionesAplicar());

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('administrador con cuenta activa', $salida);
        $this->assertStringContainsString('Triggers de updated_at tras deshacer: los 8 activos', $salida);
        // Las entradas anteriores sí llegaron a escribirse y se deshicieron.
        $this->assertSame('2026-09-01 18:00:00.000000', $this->valor('usuarios', 'created_at', $this->ids['usuario']));
        $this->assertSame($antes, $this->huella());
        $this->assertTriggersActivos();
        $this->assertSame(0, DB::table('auditorias')->where('user_agent', 'like', 'zona-horaria:%')->count());

        $registro = RegistroCambios::leer($this->manifiesto());
        $this->assertSame('fallido', $registro['estado']);
        $this->assertSame($plan->hash(), $registro['plan_hash']);
        $this->assertSame([], $registro['cambios']);
    }

    public function test_las_validaciones_diferidas_siguen_activas_durante_la_reparacion(): void
    {
        $this->plan();
        // La mascota disponible se queda sin foto: trigger_validar_minimo_fotos (diferido) debe
        // rechazarlo dentro de la reparación, no después.
        DB::table('fotos_mascota')->where('mascota_id', $this->ids['mascota'])->delete();
        $antes = $this->huella();

        [$codigo, $salida] = $this->reparar($this->opcionesAplicar());

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('Una mascota disponible debe conservar al menos una foto principal', $salida);
        $this->assertSame('2026-09-04 20:00:00.000000', $this->valor('mascotas', 'created_at', $this->ids['mascota']));
        $this->assertSame($antes, $this->huella());
        $this->assertTriggersActivos();
    }

    public function test_una_fila_excluida_no_puede_repararse(): void
    {
        $entradas = $this->plan()->entradas();
        $antes = $this->huella();

        DB::beginTransaction();
        DB::statement("SET LOCAL TimeZone = 'UTC'");
        $excluida = PlanReparacion::entradaDesdeBase('usuarios', 'created_at', $this->ids['excluido'], $this->ids['auditoria_usuario'], 'instante');
        DB::rollBack();

        $this->usarPlan(new PlanReparacion([...$entradas, $excluida], $this->exclusiones(), 7));
        [$codigo, $salida] = $this->reparar($this->opcionesAplicar());

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('está en la lista de exclusiones y no puede repararse', $salida);
        $this->assertSame('2026-09-03 12:00:00.000000', $this->valor('usuarios', 'created_at', $this->ids['excluido']));

        // Un plan con un número de entradas distinto del fijado tampoco se aplica.
        File::delete($this->manifiesto()); // registro del intento anterior
        $this->usarPlan(new PlanReparacion(array_slice($entradas, 1), $this->exclusiones(), 6));
        [$codigo, $salida] = $this->reparar($this->opcionesAplicar());
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('debe tener exactamente 6', $salida);

        $this->assertSame($antes, $this->huella());
    }

    public function test_la_reversion_simula_por_defecto_y_restaura_los_valores_originales(): void
    {
        $plan = $this->plan();
        $this->assertSame(0, $this->reparar($this->opcionesAplicar())[0]);
        $reparado = $this->huella();

        [$codigo, $salida] = $this->revertir();
        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('SIMULACIÓN DE REVERSIÓN (no modifica nada)', $salida);
        $this->assertSame($reparado, $this->huella());

        [$codigo, $salida] = $this->revertir(['--aplicar' => true, '--confirmar-plan' => str_repeat('0', 64)]);
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('--confirmar-plan no coincide', $salida);
        $this->assertSame($reparado, $this->huella());

        [$codigo, $salida] = $this->revertir(['--aplicar' => true, '--confirmar-plan' => $plan->hash()]);
        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('6 fechas restauradas', $salida);

        $this->assertSame('2026-09-01 18:00:00.000000', $this->valor('usuarios', 'created_at', $this->ids['usuario']));
        $this->assertSame('2026-09-01 18:00:00.000000', $this->valor('usuarios', 'updated_at', $this->ids['usuario']));
        $this->assertSame('2026-09-04 20:00:00.000000', $this->valor('mascotas', 'created_at', $this->ids['mascota']));
        $this->assertSame('2026-09-04 20:00:00.000000', $this->valor('mascotas', 'updated_at', $this->ids['mascota']));
        $this->assertSame('2026-09-05 15:00:00.000000', $this->valor('postulaciones_voluntariado', 'created_at', $this->ids['postulacion']));
        $this->assertSame('2026-09-05 19:30:00.000000', $this->valor('postulaciones_voluntariado', 'revisado_at', $this->ids['postulacion']));
        $this->assertSame('2026-09-05 19:30:00.654321', $this->valor('postulaciones_voluntariado', 'updated_at', $this->ids['postulacion']));
        $this->assertTriggersActivos();

        // Las filas vuelven a ser idénticas a las de antes de la reparación.
        foreach ($plan->entradas() as $e) {
            $this->assertSame($e['fila']['md5'], ReparacionZonaHoraria::estadoFila($e['tabla'], $e['id'])['md5'], PlanReparacion::clave($e));
        }

        $this->assertSame(3, DB::table('auditorias')->where('user_agent', 'zona-horaria:revertir plan:'.substr($plan->hash(), 0, 12))->count());
        $reversion = RegistroCambios::leer($this->registroDeReversion());
        $this->assertSame('aplicado', $reversion['estado']);
        $this->assertCount(6, $reversion['cambios']);
    }

    public function test_no_revierte_si_una_fila_cambio_despues_de_la_reparacion(): void
    {
        $plan = $this->plan();
        $this->assertSame(0, $this->reparar($this->opcionesAplicar())[0]);
        // Edición posterior normal: el trigger actualiza updated_at.
        DB::update("update usuarios set nombres = 'Editado' where id = ?", [$this->ids['usuario']]);
        $antes = $this->huella();

        [$codigo, $salida] = $this->revertir();
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('el valor actual no es el que dejó la reparación', $salida);

        [$codigo, $salida] = $this->revertir(['--aplicar' => true, '--confirmar-plan' => $plan->hash()]);
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('Abortado sin modificar registros', $salida);
        $this->assertSame($antes, $this->huella());
        $this->assertTriggersActivos();
    }

    public function test_rechaza_un_registro_alterado_de_otra_base_o_no_aplicado(): void
    {
        $plan = $this->plan();
        $this->assertSame(0, $this->reparar($this->opcionesAplicar())[0]);
        $antes = $this->huella();
        $original = File::get($this->manifiesto());
        $aplicar = ['--aplicar' => true, '--confirmar-plan' => $plan->hash()];

        // Edición manual: la firma deja de coincidir.
        File::put($this->manifiesto(), str_replace('"original": "2026-09-01 18:00:00"', '"original": "2026-09-01 10:00:00"', $original));
        [$codigo, $salida] = $this->revertir($aplicar);
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('La firma del registro no coincide', $salida);

        // Registro (bien firmado) de otra base.
        $datos = json_decode($original, true);
        RegistroCambios::guardar($this->manifiesto(), [...$datos, 'base' => 'pol_bolivia']);
        [$codigo, $salida] = $this->revertir($aplicar);
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('el registro es de la base pol_bolivia', $salida);

        // Registro de un intento fallido.
        RegistroCambios::guardar($this->manifiesto(), [...$datos, 'estado' => 'fallido']);
        [$codigo, $salida] = $this->revertir($aplicar);
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('no figura como aplicada', $salida);

        // Registros falsificados con la firma recalculada: la firma no basta, deben ser los cambios del plan.
        $falsificaciones = [
            'valor original distinto' => function (array $d) {
                $d['cambios'][0]['original'] = '2026-09-01 10:00:00';

                return $d;
            },
            'otra tabla y columna' => function (array $d) {
                $d['cambios'][0]['tabla'] = 'auditorias';
                $d['cambios'][0]['columna'] = 'created_at';

                return $d;
            },
            'cambio de menos' => fn (array $d) => [...$d, 'cambios' => array_slice($d['cambios'], 1)],
            'sin estado de fila' => function (array $d) {
                unset($d['cambios'][0]['fila_tras_cambio']);

                return $d;
            },
        ];
        foreach ($falsificaciones as $caso => $falsificar) {
            RegistroCambios::guardar($this->manifiesto(), $falsificar($datos));
            [$codigo, $salida] = $this->revertir($aplicar);
            $this->assertSame(1, $codigo, $caso);
            $this->assertMatchesRegularExpression('/no coinciden con las entradas del plan|no tiene el estado de la fila/', $salida, $caso);
        }

        // Registro de otro plan.
        RegistroCambios::guardar($this->manifiesto(), [...$datos, 'plan_hash' => str_repeat('a', 64)]);
        [$codigo, $salida] = $this->revertir(['--aplicar' => true, '--confirmar-plan' => str_repeat('a', 64)]);
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('no corresponde al plan fijo actual', $salida);

        // Registro inexistente y base distinta de la conectada.
        $this->assertSame(1, $this->revertir(['--manifiesto' => $this->carpeta.'/no-existe.json'])[0]);
        [$codigo, $salida] = $this->revertir(['--base' => 'pol_bolivia', '--confirmar-base' => 'pol_bolivia'] + $aplicar);
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('no coincide con la base conectada', $salida);

        $this->assertSame($antes, $this->huella());
    }

    public function test_no_sobrescribe_un_registro_de_cambios_existente(): void
    {
        File::ensureDirectoryExists($this->carpeta);
        File::put($this->manifiesto(), 'registro anterior');
        $antes = $this->huella();

        [$codigo, $salida] = $this->reparar($this->opcionesAplicar());

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('Ya existe el registro', $salida);
        $this->assertSame('registro anterior', File::get($this->manifiesto()));
        $this->assertSame($antes, $this->huella());
    }

    public function test_un_trigger_de_validacion_desactivado_impide_empezar(): void
    {
        $antes = $this->huella();
        DB::statement('ALTER TABLE postulaciones_voluntariado DISABLE TRIGGER trigger_validar_revisor_postulacion');

        try {
            [$codigo, $salida] = $this->reparar();
            $this->assertSame(1, $codigo);
            $this->assertStringContainsString('Triggers de validación no activos: postulaciones_voluntariado.trigger_validar_revisor_postulacion', $salida);

            [$codigo, $salida] = $this->reparar($this->opcionesAplicar());
            $this->assertSame(1, $codigo);
            $this->assertStringContainsString('Triggers no utilizables antes de empezar', $salida);
            $this->assertSame($antes, $this->huella());
        } finally {
            DB::statement('ALTER TABLE postulaciones_voluntariado ENABLE TRIGGER trigger_validar_revisor_postulacion');
        }
    }

    public function test_una_sesion_en_modo_replica_impide_empezar(): void
    {
        // En modo réplica PostgreSQL no dispara los triggers normales, incluidos los de validación.
        $antes = $this->huella();
        DB::statement('SET LOCAL session_replication_role = replica');

        try {
            [$codigo, $salida] = $this->reparar($this->opcionesAplicar());
            $this->assertSame(1, $codigo);
            $this->assertStringContainsString('session_replication_role es replica', $salida);
        } finally {
            DB::statement('SET LOCAL session_replication_role = origin');
        }
        $this->assertSame($antes, $this->huella());
    }

    public function test_las_auditorias_historicas_no_cambian_y_los_registros_no_tienen_datos_personales(): void
    {
        $plan = $this->plan();
        $ultima = DB::table('auditorias')->max('id');
        $historicas = fn () => DB::selectOne('select md5(string_agg(t::text, chr(10) order by t.id)) h from auditorias t where id <= ?', [$ultima])->h;
        $antes = $historicas();

        $this->assertSame(0, $this->reparar($this->opcionesAplicar())[0]);
        $this->assertSame(0, $this->revertir(['--aplicar' => true, '--confirmar-plan' => $plan->hash()])[0]);

        // Solo se añadieron auditorías nuevas (3 de la reparación y 3 de la reversión).
        $this->assertSame($antes, $historicas());
        $this->assertSame(6, DB::table('auditorias')->where('id', '>', $ultima)->count());

        // Escritura atómica: no quedan temporales. Sin nombres, correos ni contraseñas.
        $archivos = File::files($this->carpeta);
        $this->assertCount(2, $archivos);
        foreach ($archivos as $archivo) {
            $this->assertStringEndsWith('.json', $archivo->getFilename());
            $contenido = $archivo->getContents();
            foreach (['@example.test', 'Desfasado', 'Postulante', 'Admin', 'password'] as $dato) {
                $this->assertStringNotContainsString($dato, $contenido);
            }
        }
    }

    public function test_un_registro_interrumpido_antes_de_marcarse_aplicado_permite_revertir(): void
    {
        // Simula una interrupción tras el COMMIT: el registro quedó en 'confirmando' con los cambios.
        $plan = $this->plan();
        $this->assertSame(0, $this->reparar($this->opcionesAplicar())[0]);
        RegistroCambios::guardar($this->manifiesto(), [...RegistroCambios::leer($this->manifiesto()), 'estado' => 'confirmando']);

        [$codigo, $salida] = $this->revertir(['--aplicar' => true, '--confirmar-plan' => $plan->hash()]);

        $this->assertSame(0, $codigo, $salida);
        $this->assertSame('2026-09-01 18:00:00.000000', $this->valor('usuarios', 'created_at', $this->ids['usuario']));
    }

    // ---------- Anexo de columnas originales (reversión tras añadir columnas) ----------

    public function test_el_anexo_reproduce_las_huellas_originales_sin_modificar_datos_ni_el_registro(): void
    {
        $this->assertSame(0, $this->reparar($this->opcionesAplicar())[0]);
        $antes = [$this->huella(), hash_file('sha256', $this->manifiesto())];

        [$codigo, $salida] = $this->anexo();

        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('6 huellas comprobadas', $salida);
        $this->assertSame($antes, [$this->huella(), hash_file('sha256', $this->manifiesto())]);
        $this->assertSame('aplicado', RegistroCambios::leer($this->manifiesto())['estado']);

        $anexo = AnexoColumnas::leer(AnexoColumnas::rutaPara($this->manifiesto()));
        $this->assertSame(AnexoColumnas::columnasActuales('usuarios'), $anexo['columnas']['usuarios']);
        $this->assertSame(['usuarios', 'mascotas', 'postulaciones_voluntariado'], array_keys($anexo['columnas']));

        // Huella con las columnas originales = huella original del registro (sesión en UTC, como el comando).
        DB::statement("SET LOCAL TimeZone = 'UTC'");
        foreach (RegistroCambios::leer($this->manifiesto())['cambios'] as $c) {
            $this->assertSame($c['fila_tras_cambio']['md5'], ReparacionZonaHoraria::estadoFila($c['tabla'], $c['id'], $anexo['columnas'][$c['tabla']])['md5'], PlanReparacion::clave($c));
        }

        // Nunca sobrescribe un anexo existente.
        [$codigo, $salida] = $this->anexo();
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('Ya existe el registro', $salida);
    }

    public function test_una_columna_nueva_no_invalida_por_si_sola_la_reversion_si_se_usa_el_anexo(): void
    {
        $plan = $this->plan();
        $this->assertSame(0, $this->reparar($this->opcionesAplicar())[0]);
        $this->assertSame(0, $this->anexo()[0]);
        DB::statement('ALTER TABLE usuarios ADD COLUMN columna_posterior text');
        $registro = hash_file('sha256', $this->manifiesto());
        $antes = $this->huella();

        // Sin el anexo, la huella de la fila completa ya no coincide: la reversión se bloquea.
        [$codigo, $salida] = $this->revertir();
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('huella distinta', $salida);

        // Con el anexo, se comparan las columnas originales y coinciden.
        [$codigo, $salida] = $this->revertir(['--anexo' => $this->rutaAnexo()]);
        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('Simulación correcta', $salida);
        $this->assertSame($antes, $this->huella(), 'la simulación no modifica datos');
        $this->assertSame($registro, hash_file('sha256', $this->manifiesto()), 'el registro original no cambia');

        [$codigo, $salida] = $this->revertir(['--anexo' => $this->rutaAnexo(), '--aplicar' => true, '--confirmar-plan' => $plan->hash()]);
        $this->assertSame(0, $codigo, $salida);
        $this->assertSame('2026-09-01 18:00:00.000000', $this->valor('usuarios', 'created_at', $this->ids['usuario']));
        $this->assertSame('2026-09-05 19:30:00.000000', $this->valor('postulaciones_voluntariado', 'revisado_at', $this->ids['postulacion']));
        $this->assertSame(hash_file('sha256', $this->rutaAnexo()), RegistroCambios::leer($this->registroDeReversion())['anexo_sha256']);
        $this->assertSame($registro, hash_file('sha256', $this->manifiesto()));
    }

    public function test_con_el_anexo_una_modificacion_real_de_una_columna_original_sigue_bloqueando(): void
    {
        $this->assertSame(0, $this->reparar($this->opcionesAplicar())[0]);
        $this->assertSame(0, $this->anexo()[0]);
        DB::statement('ALTER TABLE usuarios ADD COLUMN columna_posterior text');

        // Cambiar solo la columna nueva no afecta a los datos reparados.
        $this->sinTriggerUpdatedAt('usuarios', fn () => DB::update("update usuarios set columna_posterior = 'x' where id = ?", [$this->ids['usuario']]));
        $this->assertSame(0, $this->revertir(['--anexo' => $this->rutaAnexo()])[0]);

        // Cambiar una columna original (nombres, fuera de los campos comparados) la bloquea por la huella.
        $this->sinTriggerUpdatedAt('usuarios', fn () => DB::update("update usuarios set nombres = 'Cambiado' where id = ?", [$this->ids['usuario']]));
        $antes = $this->huella();

        [$codigo, $salida] = $this->revertir(['--anexo' => $this->rutaAnexo()]);
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('la fila cambió después de la reparación (huella distinta)', $salida);

        [$codigo, $salida] = $this->revertir(['--anexo' => $this->rutaAnexo(), '--aplicar' => true, '--confirmar-plan' => $this->plan()->hash()]);
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('Abortado sin modificar registros', $salida);
        $this->assertSame($antes, $this->huella());
    }

    public function test_un_anexo_alterado_ajeno_o_incoherente_con_el_esquema_se_rechaza(): void
    {
        $this->assertSame(0, $this->reparar($this->opcionesAplicar())[0]);
        $this->assertSame(0, $this->anexo()[0]);
        DB::statement('ALTER TABLE usuarios ADD COLUMN columna_posterior text');
        $original = AnexoColumnas::leer($this->rutaAnexo());
        $antes = $this->huella();
        $firmar = fn (array $datos) => [...$datos, 'hmac' => (new \ReflectionMethod(AnexoColumnas::class, 'hmac'))->invoke(null, $datos)];
        $usar = function (array $anexo) {
            RegistroCambios::guardar($this->rutaAnexo(), $anexo);

            return $this->revertir(['--anexo' => $this->rutaAnexo()]);
        };

        // 1. Editado sin la clave (SHA-256 recalculado, HMAC antiguo): se detecta.
        $editado = $firmar($original);
        $editado['columnas']['usuarios'] = AnexoColumnas::columnasActuales('usuarios');
        [$codigo, $salida] = $usar($editado);
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('El HMAC del anexo no es válido', $salida);

        // 2. Firmado pero de otro registro.
        [$codigo, $salida] = $usar($firmar([...$original, 'manifiesto_sha256' => str_repeat('0', 64)]));
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('no corresponde a este registro de cambios', $salida);

        // 3. Firmado pero con las columnas reordenadas: no es un prefijo del esquema actual.
        $reordenado = $original;
        $reordenado['columnas']['usuarios'] = array_reverse($original['columnas']['usuarios']);
        [$codigo, $salida] = $usar($firmar($reordenado));
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('ya no están, en el mismo orden', $salida);

        // 4. Firmado y prefijo válido pero incompleto: no reproduce la huella original.
        $incompleto = $original;
        $incompleto['columnas']['usuarios'] = array_slice($original['columnas']['usuarios'], 0, -1);
        [$codigo, $salida] = $usar($firmar($incompleto));
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('huella distinta', $salida);

        $this->assertSame($antes, $this->huella());
        $this->assertSame('aplicado', RegistroCambios::leer($this->manifiesto())['estado']);
    }

    public function test_el_anexo_no_se_genera_si_las_huellas_ya_no_coinciden(): void
    {
        $this->assertSame(0, $this->reparar($this->opcionesAplicar())[0]);
        $this->sinTriggerUpdatedAt('usuarios', fn () => DB::update("update usuarios set nombres = 'Cambiado' where id = ?", [$this->ids['usuario']]));

        [$codigo, $salida] = $this->anexo();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('ya no coincide con la huella del registro', $salida);
        $this->assertFileDoesNotExist($this->rutaAnexo());
    }

    /**
     * Datos que reproducen el fallo: valores escritos por Laravel (sin fracción) 4 h por delante
     * de su auditoría (UTC); valores de PostgreSQL con microsegundos; una fila sin evidencia.
     */
    private function escenario(): void
    {
        $usuario = fn (array $datos) => DB::table('usuarios')->insertGetId($datos + ['apellidos' => 'Prueba', 'password' => 'x', 'rol' => 'ciudadano']);
        $auditoria = fn (array $datos) => DB::table('auditorias')->insertGetId($datos);

        $this->ids['admin'] = $usuario(['nombres' => 'Admin', 'email' => 'admin.zona@example.test', 'rol' => 'administrador', 'created_at' => '2026-09-01 10:00:00.123456+00', 'updated_at' => '2026-09-01 10:00:00.123456+00']);
        $this->ids['usuario'] = $usuario(['nombres' => 'Desfasado', 'email' => 'desfasado@example.test', 'created_at' => '2026-09-01 18:00:00+00', 'updated_at' => '2026-09-01 18:00:00+00']);
        $this->ids['postulante'] = $usuario(['nombres' => 'Postulante', 'email' => 'postulante.zona@example.test', 'created_at' => '2026-09-02 10:00:00.5+00', 'updated_at' => '2026-09-02 10:00:00.5+00']);
        $this->ids['excluido'] = $usuario(['nombres' => 'Sin', 'email' => 'sin.evidencia@example.test', 'created_at' => '2026-09-03 12:00:00+00', 'updated_at' => '2026-09-03 12:00:00+00']);
        $this->ids['auditoria_usuario'] = $auditoria(['accion' => 'creado', 'modelo' => 'User', 'modelo_id' => $this->ids['usuario'], 'created_at' => '2026-09-01 14:00:00']);

        DB::transaction(function () {
            $this->ids['mascota'] = DB::table('mascotas')->insertGetId([
                'nombre' => 'Zona', 'especie' => 'perro', 'edad' => 2, 'edad_en' => 'años', 'sexo' => 'macho', 'tamano' => 'mediano',
                'estado' => 'disponible', 'registrado_por' => $this->ids['admin'], 'created_at' => '2026-09-04 20:00:00+00', 'updated_at' => '2026-09-04 20:00:00+00',
            ]);
            DB::table('fotos_mascota')->insert(['mascota_id' => $this->ids['mascota'], 'tipo' => 'foto', 'url' => 'mascotas/zona.jpg', 'es_principal' => true, 'orden' => 1]);
        });
        $this->ids['auditoria_mascota'] = $auditoria(['accion' => 'creado', 'modelo' => 'Mascota', 'modelo_id' => $this->ids['mascota'], 'created_at' => '2026-09-04 16:00:00']);

        $this->ids['postulacion'] = DB::table('postulaciones_voluntariado')->insertGetId([
            'usuario_id' => $this->ids['postulante'], 'motivacion' => 'Ayudar', 'disponibilidad' => 'fines de semana', 'zona_preferencia' => 'Centro',
            'estado' => 'rechazada', 'revisado_por' => $this->ids['admin'], 'revisado_at' => '2026-09-05 19:30:00+00',
            'created_at' => '2026-09-05 15:00:00+00', 'updated_at' => '2026-09-05 19:30:00.654321+00',
        ]);
        $this->ids['auditoria_postulacion'] = $auditoria(['accion' => 'creado', 'modelo' => 'PostulacionVoluntariado', 'modelo_id' => $this->ids['postulacion'], 'created_at' => '2026-09-05 11:00:00']);
        $this->ids['auditoria_revision'] = $auditoria([
            'accion' => 'actualizado', 'modelo' => 'PostulacionVoluntariado', 'modelo_id' => $this->ids['postulacion'],
            'valores_nuevos' => json_encode(['estado' => 'rechazada', 'revisado_at' => '2026-09-05 15:30:00']), 'created_at' => '2026-09-05 15:30:00',
        ]);

        $this->usarPlan($this->construirPlan());
    }

    private function construirPlan(): PlanReparacion
    {
        // Como al generar el plan de octubre de 2026: sesión en UTC solo durante la lectura.
        DB::beginTransaction();
        DB::statement("SET LOCAL TimeZone = 'UTC'");
        $entradas = [
            PlanReparacion::entradaDesdeBase('usuarios', 'created_at', $this->ids['usuario'], $this->ids['auditoria_usuario'], 'instante'),
            PlanReparacion::entradaDesdeBase('usuarios', 'updated_at', $this->ids['usuario'], $this->ids['auditoria_usuario'], 'instante'),
            PlanReparacion::entradaDesdeBase('mascotas', 'created_at', $this->ids['mascota'], $this->ids['auditoria_mascota'], 'instante'),
            PlanReparacion::entradaDesdeBase('mascotas', 'updated_at', $this->ids['mascota'], $this->ids['auditoria_mascota'], 'instante'),
            PlanReparacion::entradaDesdeBase('postulaciones_voluntariado', 'created_at', $this->ids['postulacion'], $this->ids['auditoria_postulacion'], 'instante'),
            PlanReparacion::entradaDesdeBase('postulaciones_voluntariado', 'revisado_at', $this->ids['postulacion'], $this->ids['auditoria_revision'], 'valor'),
        ];
        DB::rollBack();

        $plan = new PlanReparacion($entradas, $this->exclusiones(), 6);
        $this->assertSame([], $plan->errores());

        return $plan;
    }

    private function exclusiones(): array
    {
        return [
            ['tabla' => 'usuarios', 'columna' => 'created_at', 'id' => $this->ids['excluido'], 'categoria' => 'sospechoso'],
            ['tabla' => 'usuarios', 'columna' => 'updated_at', 'id' => $this->ids['excluido'], 'categoria' => 'sospechoso'],
        ];
    }

    private function usarPlan(PlanReparacion $plan): void
    {
        $this->app->instance(PlanReparacion::class, $plan);
    }

    private function plan(): PlanReparacion
    {
        return $this->app->make(PlanReparacion::class);
    }

    private function manifiesto(): string
    {
        return $this->carpeta.DIRECTORY_SEPARATOR.'reparacion.json';
    }

    private function registroDeReversion(): string
    {
        $registros = glob($this->carpeta.DIRECTORY_SEPARATOR.'reparacion-reversion-*.json');
        $this->assertCount(1, $registros);

        return $registros[0];
    }

    private function opcionesAplicar(): array
    {
        return ['--aplicar' => true, '--confirmar-plan' => $this->plan()->hash(), '--manifiesto' => $this->manifiesto()];
    }

    /** @return array{0:int, 1:string} */
    private function reparar(array $opciones = []): array
    {
        $codigo = Artisan::call('zona-horaria:reparar', array_filter($opciones + ['--base' => 'pol_bolivia_test'], fn ($v) => $v !== null));

        return [$codigo, Artisan::output()];
    }

    /** @return array{0:int, 1:string} */
    /** @return array{0:int, 1:string} */
    private function anexo(): array
    {
        $codigo = Artisan::call('zona-horaria:anexo-columnas', ['--manifiesto' => $this->manifiesto(), '--base' => 'pol_bolivia_test']);

        return [$codigo, Artisan::output()];
    }

    private function rutaAnexo(): string
    {
        return AnexoColumnas::rutaPara($this->manifiesto());
    }

    private function revertir(array $opciones = []): array
    {
        $codigo = Artisan::call('zona-horaria:revertir', array_filter($opciones + ['--base' => 'pol_bolivia_test', '--manifiesto' => $this->manifiesto()], fn ($v) => $v !== null));

        return [$codigo, Artisan::output()];
    }

    private function sinTriggerUpdatedAt(string $tabla, callable $cambio): void
    {
        DB::statement("ALTER TABLE {$tabla} DISABLE TRIGGER ".ReparacionZonaHoraria::TRIGGERS_UPDATED_AT[$tabla]);
        $cambio();
        DB::statement("ALTER TABLE {$tabla} ENABLE TRIGGER ".ReparacionZonaHoraria::TRIGGERS_UPDATED_AT[$tabla]);
    }

    private function valor(string $tabla, string $columna, int $id): ?string
    {
        return DB::selectOne("select to_char({$columna} at time zone 'UTC', 'YYYY-MM-DD HH24:MI:SS.US') v from {$tabla} where id = ?", [$id])->v;
    }

    private function assertTriggersActivos(): void
    {
        $this->assertSame(array_fill_keys(ReparacionZonaHoraria::TRIGGERS_UPDATED_AT, 'O'), ReparacionZonaHoraria::estadoTriggers());
    }

    private function huella(): string
    {
        return collect(['usuarios', 'mascotas', 'fotos_mascota', 'postulaciones_voluntariado', 'auditorias'])
            ->map(fn ($tabla) => DB::selectOne("select md5(coalesce(string_agg(t::text, chr(10) order by t::text), '')) h from {$tabla} t")->h)
            ->implode('|');
    }
}
