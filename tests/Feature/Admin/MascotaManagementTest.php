<?php

namespace Tests\Feature\Admin;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MascotaManagementTest extends TestCase
{
    use RefreshDatabase;

    private ?User $registrant = null;

    public function test_active_administrator_can_access_pet_management(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'administrador']))
            ->get('/admin/mascotas')->assertOk()->assertSee('Gestión de mascotas')->assertDontSee('Borrador');
    }

    public function test_citizen_volunteer_and_inactive_administrator_cannot_access_pet_management(): void
    {
        foreach ([['rol' => 'ciudadano'], ['rol' => 'voluntario'], ['rol' => 'administrador', 'estado' => false]] as $attributes) {
            $this->actingAs(User::factory()->create($attributes))->get('/admin/mascotas')->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login_from_pet_management(): void
    {
        $this->get('/admin/mascotas')->assertRedirect(route('login'));
    }

    public function test_active_administrator_can_access_pet_registration_form(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'administrador']))
            ->get('/admin/mascotas/create')->assertOk()->assertSee('Registrar mascota')->assertSee('Foto principal')
            ->assertDontSee('name="estado"', false)->assertDontSee('name="registrado_por"', false);
    }

    public function test_non_administrators_cannot_access_pet_registration_form(): void
    {
        foreach ([['rol' => 'ciudadano'], ['rol' => 'voluntario'], ['rol' => 'administrador', 'estado' => false]] as $attributes) {
            $this->actingAs(User::factory()->create($attributes))->get('/admin/mascotas/create')->assertForbidden();
        }
    }

    public function test_administrator_registers_an_available_pet_with_a_principal_photo_atomically(): void
    {
        Storage::fake('public');
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $otherUser = User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($administrator)->post('/admin/mascotas', [
            ...$this->registrationData(),
            'foto_principal' => UploadedFile::fake()->image('canela.jpg'),
            'estado' => 'adoptada',
            'registrado_por' => $otherUser->id,
        ])->assertRedirect(route('admin.mascotas.index'))
            ->assertSessionHas('status', 'La mascota fue registrada correctamente.');

        $mascota = Mascota::where('nombre', 'Canela')->firstOrFail();
        $this->assertSame('disponible', $mascota->estado);
        $this->assertSame($administrator->id, $mascota->registrado_por);
        $foto = $mascota->fotos()->firstOrFail();
        $this->assertTrue($foto->es_principal);
        $this->assertSame('foto', $foto->tipo);
        Storage::disk('public')->assertExists($foto->url);
    }

    public function test_pet_registration_requires_a_principal_photo_and_validates_fields(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $this->actingAs($administrator)->post('/admin/mascotas', [])->assertSessionHasErrors(['nombre', 'especie', 'edad', 'sexo', 'tamano', 'foto_principal']);
        $this->actingAs($administrator)->post('/admin/mascotas', [
            ...$this->registrationData(), 'nombre' => '   ', 'especie' => 'conejo', 'edad' => -1,
            'sexo' => 'indefinido', 'tamano' => 'gigante',
            'foto_principal' => UploadedFile::fake()->create('documento.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors(['nombre', 'especie', 'edad', 'sexo', 'tamano', 'foto_principal']);
    }

    public function test_guest_cannot_register_a_pet(): void
    {
        $this->post('/admin/mascotas', $this->registrationData())->assertRedirect(route('login'));
        $this->assertDatabaseMissing('mascotas', ['nombre' => 'Canela']);
    }

    public function test_administrator_can_access_and_edit_pet_information_without_changing_sensitive_fields(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $otherUser = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createMascota(['nombre' => 'Luna detalle']);
        $this->actingAs($administrator)->get(route('admin.mascotas.show', $mascota))->assertOk()->assertSee('Luna detalle');
        $this->actingAs($administrator)->get(route('admin.mascotas.edit', $mascota))->assertOk()->assertSee('Editar mascota');
        $this->actingAs($administrator)->patch(route('admin.mascotas.update', $mascota), [
            ...$this->registrationData(['nombre' => 'Luna editada']), 'estado' => 'adoptada', 'registrado_por' => $otherUser->id,
        ])->assertRedirect(route('admin.mascotas.show', $mascota));
        $mascota->refresh();
        $this->assertSame('Luna editada', $mascota->nombre);
        $this->assertSame('disponible', $mascota->estado);
        $this->assertNotSame($otherUser->id, $mascota->registrado_por);
    }

    public function test_citizen_and_volunteer_cannot_access_pet_detail_or_edit(): void
    {
        $mascota = $this->createMascota();
        foreach (['ciudadano', 'voluntario'] as $role) {
            $user = User::factory()->create(['rol' => $role]);
            $this->actingAs($user)->get(route('admin.mascotas.show', $mascota))->assertForbidden();
            $this->actingAs($user)->get(route('admin.mascotas.edit', $mascota))->assertForbidden();
        }
    }

    public function test_administrator_can_upload_and_delete_a_non_principal_pet_photo(): void
    {
        Storage::fake('public');
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createMascota();
        $this->actingAs($administrator)->post(route('admin.mascotas.fotos.store', $mascota), ['foto' => UploadedFile::fake()->image('luna.jpg')])
            ->assertRedirect(route('admin.mascotas.show', $mascota));
        $foto = $mascota->fotos()->where('es_principal', false)->firstOrFail();
        Storage::disk('public')->assertExists($foto->url);
        $this->actingAs($administrator)->delete(route('admin.mascotas.fotos.destroy', [$mascota, $foto]))
            ->assertRedirect(route('admin.mascotas.show', $mascota));
        $this->assertDatabaseMissing('fotos_mascota', ['id' => $foto->id]);
        Storage::disk('public')->assertMissing($foto->url);
        $this->assertSame(1, $mascota->fotos()->where('es_principal', true)->count());
    }

    public function test_photo_upload_rejects_non_images_and_oversized_files(): void
    {
        Storage::fake('public');
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createMascota();
        $this->actingAs($administrator)->post(route('admin.mascotas.fotos.store', $mascota), ['foto' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf')])->assertSessionHasErrors('foto');
        $this->actingAs($administrator)->post(route('admin.mascotas.fotos.store', $mascota), ['foto' => UploadedFile::fake()->image('grande.jpg')->size(5121)])->assertSessionHasErrors('foto');
    }

    public function test_administrator_can_set_a_non_principal_photo_as_principal(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createMascota();
        $second = $this->createFoto($mascota, ['orden' => 2]);
        $this->actingAs($administrator)->patch(route('admin.mascotas.fotos.principal', [$mascota, $second]))->assertRedirect(route('admin.mascotas.show', $mascota));
        $this->assertTrue($second->fresh()->es_principal);
        $this->assertSame(1, $mascota->fotos()->where('es_principal', true)->count());
    }

    public function test_administrator_cannot_manipulate_a_photo_from_another_pet(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createMascota();
        $otherPhoto = $this->createFoto($this->createMascota(), ['orden' => 2]);
        $this->actingAs($administrator)->patch(route('admin.mascotas.fotos.principal', [$mascota, $otherPhoto]))->assertNotFound();
        $this->actingAs($administrator)->delete(route('admin.mascotas.fotos.destroy', [$mascota, $otherPhoto]))->assertNotFound();
    }

    public function test_administrator_sees_pet_data_and_principal_photo(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createMascota(['nombre' => 'Luna', 'especie' => 'gato', 'raza' => 'Criolla', 'edad' => 4, 'sexo' => 'hembra', 'tamano' => 'pequeno']);
        $this->actingAs($administrator)->get('/admin/mascotas')->assertOk()->assertSee('Luna')->assertSee('Gato')->assertSee('Criolla')->assertSee('4 años')->assertSee('Hembra')->assertSee('Pequeno')->assertSee('Disponible')->assertSee("mascotas/{$mascota->id}/principal.jpg");
    }

    public function test_administrator_can_search_and_filter_pets(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $match = $this->createMascota(['nombre' => 'Luna combinada', 'especie' => 'gato', 'sexo' => 'hembra', 'tamano' => 'pequeno']);
        $other = $this->createMascota(['nombre' => 'Luna distinta', 'especie' => 'perro', 'sexo' => 'macho', 'tamano' => 'grande']);
        $this->actingAs($administrator)->get('/admin/mascotas?buscar=lUnA&especie=gato&sexo=hembra&tamano=pequeno&estado=disponible')->assertSee($match->nombre)->assertDontSee($other->nombre);
    }

    public function test_pagination_preserves_pet_filters(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        foreach (range(1, 16) as $number) { $this->createMascota(['nombre' => "Perro {$number}", 'especie' => 'perro']); }
        $this->actingAs($administrator)->get('/admin/mascotas?especie=perro')->assertOk()->assertSee('especie=perro')->assertSee('page=2');
    }

    public function test_available_pets_cannot_be_persisted_without_a_principal_photo(): void
    {
        $this->registrant = User::factory()->create(['rol' => 'administrador']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::transaction(function (): void {
            Mascota::create([...$this->petData(), 'estado' => 'disponible', 'registrado_por' => $this->registrant->id]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    }

    public function test_pet_management_defines_expected_routes_without_state_transition_routes(): void
    {
        $petRoutes = collect(Route::getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'admin/mascotas'));
        $this->assertCount(9, $petRoutes);
        $this->assertSame(['PATCH'], Route::getRoutes()->getByName('admin.mascotas.update')->methods());
        $this->assertNull(Route::getRoutes()->getByName('admin.mascotas.estado.update'));
    }

    private function createMascota(array $attributes = []): Mascota
    {
        $this->registrant ??= User::factory()->create(['rol' => 'administrador']);
        return DB::transaction(function () use ($attributes): Mascota {
            $mascota = Mascota::create([...$this->petData(), ...$attributes, 'estado' => 'disponible', 'registrado_por' => $this->registrant->id]);
            FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => "mascotas/{$mascota->id}/principal.jpg", 'es_principal' => true, 'orden' => 1]);
            return $mascota;
        });
    }

    private function createFoto(Mascota $mascota, array $attributes = []): FotoMascota
    {
        return FotoMascota::create(['mascota_id' => $mascota->id, 'tipo' => 'foto', 'url' => "mascotas/{$mascota->id}/foto.jpg", 'es_principal' => false, 'orden' => 2, ...$attributes]);
    }

    private function registrationData(array $attributes = []): array { return [...$this->petData(), ...$attributes]; }

    private function petData(): array
    {
        return ['nombre' => 'Canela', 'especie' => 'perro', 'raza' => 'Mestiza', 'edad' => 2, 'sexo' => 'hembra', 'color' => 'Canela', 'tamano' => 'mediano', 'descripcion' => 'Mascota registrada desde el formulario.'];
    }
}
