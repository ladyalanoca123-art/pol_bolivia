<?php

namespace Tests\Feature;

use App\Models\AceptacionLegal;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Términos y condiciones, Política de privacidad y Política de cookies: páginas públicas, dos casillas separadas
 * al crear la cuenta y registro de cuándo y cómo aceptó cada persona (aceptaciones_legales).
 */
class DocumentosLegalesTest extends TestCase
{
    use RefreshDatabase;

    private function datosRegistro(array $cambios = []): array
    {
        return array_merge([
            'nombres' => 'Lucía',
            'apellidos' => 'Rojas',
            'email' => 'lucia.rojas@example.com',
            'telefono' => '71234567',
            'password' => 'ClaveSegura123',
            'password_confirmation' => 'ClaveSegura123',
            'acepta_terminos' => '1',
            'acepta_privacidad' => '1',
        ], $cambios);
    }

    public function test_las_tres_paginas_legales_son_publicas_y_estan_enlazadas_en_el_pie(): void
    {
        $this->get(route('legal.terminos'))->assertOk()->assertSee('Términos y condiciones')->assertSee('no tiene ningún costo');
        $this->get(route('legal.privacidad'))->assertOk()->assertSee('Política de privacidad')->assertSee('zona aproximada');
        $this->get(route('legal.cookies'))->assertOk()->assertSee('Política de cookies')->assertSee(config('session.cookie'));

        $this->get(route('home'))->assertOk()
            ->assertSee(route('legal.terminos'), false)
            ->assertSee(route('legal.privacidad'), false)
            ->assertSee(route('legal.cookies'), false);
    }

    public function test_el_registro_exige_las_dos_casillas_por_separado(): void
    {
        $this->post(route('register'), $this->datosRegistro(['acepta_terminos' => null, 'acepta_privacidad' => null]))
            ->assertSessionHasErrors(['acepta_terminos', 'acepta_privacidad']);
        $this->post(route('register'), $this->datosRegistro(['acepta_privacidad' => null]))
            ->assertSessionHasErrors(['acepta_privacidad'])->assertSessionDoesntHaveErrors(['acepta_terminos']);

        $this->assertDatabaseMissing('usuarios', ['email' => 'lucia.rojas@example.com']);
        $this->assertSame(0, AceptacionLegal::query()->count());
    }

    public function test_al_registrarse_queda_constancia_de_cuando_y_como_acepto_cada_documento(): void
    {
        $this->withHeader('User-Agent', 'NavegadorDePrueba/1.0')
            ->post(route('register'), $this->datosRegistro())->assertRedirect();

        $usuario = User::query()->where('email', 'lucia.rojas@example.com')->firstOrFail();
        $aceptaciones = AceptacionLegal::query()->where('usuario_id', $usuario->id)->orderBy('documento')->get();

        $this->assertSame(['privacidad', 'terminos'], $aceptaciones->pluck('documento')->all());
        foreach ($aceptaciones as $aceptacion) {
            $this->assertSame(config('pol.legal.version'), $aceptacion->version);
            $this->assertSame('registro', $aceptacion->origen);
            $this->assertSame('127.0.0.1', $aceptacion->ip);
            $this->assertSame('NavegadorDePrueba/1.0', $aceptacion->user_agent);
            $this->assertNotNull($aceptacion->aceptado_at);
        }
    }

    public function test_una_cuenta_que_no_acepto_debe_aceptar_antes_de_seguir(): void
    {
        $usuario = User::factory()->sinAceptacionLegal()->create(['rol' => 'ciudadano']);

        $this->actingAs($usuario)->get(route('inicio'))->assertRedirect(route('legal.aceptar'));
        // Las páginas públicas y las legales siguen libres: solo se pide aceptar al entrar a la cuenta.
        $this->actingAs($usuario)->get(route('home'))->assertOk();
        $this->actingAs($usuario)->get(route('mascotas.catalogo'))->assertOk();
        $this->actingAs($usuario)->get(route('legal.privacidad'))->assertOk();
        $this->actingAs($usuario)->get(route('legal.aceptar'))->assertOk()->assertSee('Antes de continuar');

        $this->actingAs($usuario)->post(route('legal.aceptar.guardar'), ['acepta_terminos' => '1'])
            ->assertSessionHasErrors(['acepta_privacidad']);

        $this->actingAs($usuario)->post(route('legal.aceptar.guardar'), ['acepta_terminos' => '1', 'acepta_privacidad' => '1'])
            ->assertRedirect(route('inicio'));
        $this->assertSame(2, AceptacionLegal::query()->where('usuario_id', $usuario->id)->where('origen', 'actualizacion')->count());
        $this->actingAs($usuario)->get(route('inicio'))->assertOk();
    }

    public function test_una_version_nueva_se_vuelve_a_pedir_y_la_anterior_se_conserva(): void
    {
        $usuario = User::factory()->create(['rol' => 'voluntario']);
        $this->actingAs($usuario)->get(route('inicio'))->assertOk();

        config(['pol.legal.version' => '2027-01-01']);
        $this->actingAs($usuario)->get(route('inicio'))->assertRedirect(route('legal.aceptar'));
        $this->actingAs($usuario)->post(route('legal.aceptar.guardar'), ['acepta_terminos' => '1', 'acepta_privacidad' => '1']);

        $this->assertSame(['2026-10-29', '2027-01-01'], AceptacionLegal::query()->where('usuario_id', $usuario->id)
            ->distinct()->orderBy('version')->pluck('version')->all());
    }

    public function test_una_aceptacion_registrada_no_se_puede_modificar(): void
    {
        $usuario = User::factory()->create();
        $aceptacion = AceptacionLegal::query()->where('usuario_id', $usuario->id)->firstOrFail();

        $this->expectException(QueryException::class);
        $aceptacion->update(['version' => 'otra']);
    }

    public function test_el_administrador_ve_la_constancia_en_el_detalle_del_usuario(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $conConstancia = User::factory()->create(['rol' => 'ciudadano']);
        $sinConstancia = User::factory()->sinAceptacionLegal()->create(['rol' => 'ciudadano']);

        $this->actingAs($admin)->get(route('admin.usuarios.show', $conConstancia))->assertOk()
            ->assertSee('Aceptaciones legales')->assertSee('Versión vigente aceptada')
            ->assertSee('Términos y condiciones')->assertSee('Política de privacidad')
            ->assertSee('Al crear la cuenta')->assertSee(config('pol.legal.version'));

        $this->actingAs($admin)->get(route('admin.usuarios.show', $sinConstancia))->assertOk()
            ->assertSee('Todavía no aceptó ningún documento.')->assertSee('Pendiente: se le pedirá al iniciar sesión');
    }

    public function test_el_administrador_tambien_debe_aceptar(): void
    {
        $admin = User::factory()->sinAceptacionLegal()->create(['rol' => 'administrador']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('legal.aceptar'));
    }
}
