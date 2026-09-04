<?php

namespace Tests\Feature\Admin;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MascotaManagementTest extends TestCase
{
    use RefreshDatabase;

    private ?User $registrant = null;

    public function test_active_administrator_can_access_pet_management(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($administrator)
            ->get('/admin/mascotas')
            ->assertOk()
            ->assertSee('Gestión de mascotas');
    }

    public function test_citizen_volunteer_and_inactive_administrator_cannot_access_pet_management(): void
    {
        foreach ([
            ['rol' => 'ciudadano', 'estado' => true],
            ['rol' => 'voluntario', 'estado' => true],
            ['rol' => 'administrador', 'estado' => false],
        ] as $attributes) {
            $user = User::factory()->create($attributes);

            $this->actingAs($user)
                ->get('/admin/mascotas')
                ->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login_from_pet_management(): void
    {
        $this->get('/admin/mascotas')->assertRedirect(route('login'));
    }

    public function test_active_administrator_can_access_pet_registration_form(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($administrator)
            ->get('/admin/mascotas/create')
            ->assertOk()
            ->assertSee('Registrar mascota')
            ->assertDontSee('name="estado"', false)
            ->assertDontSee('name="registrado_por"', false);
    }

    public function test_citizen_volunteer_and_inactive_administrator_cannot_access_pet_registration_form(): void
    {
        foreach ([
            ['rol' => 'ciudadano', 'estado' => true],
            ['rol' => 'voluntario', 'estado' => true],
            ['rol' => 'administrador', 'estado' => false],
        ] as $attributes) {
            $user = User::factory()->create($attributes);

            $this->actingAs($user)
                ->get('/admin/mascotas/create')
                ->assertForbidden();
        }
    }

    public function test_administrator_can_register_a_valid_pet_as_a_draft(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $anotherUser = User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($administrator)
            ->post('/admin/mascotas', [
                ...$this->registrationData(),
                'estado' => 'adoptada',
                'registrado_por' => $anotherUser->id,
            ])
            ->assertRedirect(route('admin.mascotas.index'))
            ->assertSessionHas('status', 'La mascota fue registrada como borrador.');

        $this->assertDatabaseHas('mascotas', [
            'nombre' => 'Canela',
            'estado' => 'borrador',
            'registrado_por' => $administrator->id,
        ]);
    }

    public function test_pet_registration_validates_required_fields(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($administrator)
            ->from('/admin/mascotas/create')
            ->post('/admin/mascotas', [])
            ->assertRedirect('/admin/mascotas/create')
            ->assertSessionHasErrors(['nombre', 'especie', 'edad', 'sexo', 'tamano']);
    }

    public function test_pet_registration_rejects_invalid_values_and_blank_name(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);

        $this->actingAs($administrator)
            ->post('/admin/mascotas', [
                ...$this->registrationData(),
                'nombre' => '   ',
                'especie' => 'conejo',
                'edad' => -1,
                'sexo' => 'indefinido',
                'tamano' => 'gigante',
            ])
            ->assertSessionHasErrors(['nombre', 'especie', 'edad', 'sexo', 'tamano']);
    }

    public function test_guest_cannot_register_a_pet(): void
    {
        $this->post('/admin/mascotas', $this->registrationData())
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('mascotas', ['nombre' => 'Canela']);
    }

    public function test_administrator_can_access_pet_detail(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createMascota(['nombre' => 'Luna detalle']);

        $this->actingAs($administrator)
            ->get(route('admin.mascotas.show', $mascota))
            ->assertOk()
            ->assertSee('Luna detalle')
            ->assertSee('Esta mascota todavía no tiene fotos.');
    }

    public function test_citizen_and_volunteer_cannot_access_pet_detail(): void
    {
        $mascota = $this->createMascota();

        foreach (['ciudadano', 'voluntario'] as $role) {
            $user = User::factory()->create(['rol' => $role]);

            $this->actingAs($user)
                ->get(route('admin.mascotas.show', $mascota))
                ->assertForbidden();
        }
    }

    public function test_administrator_can_upload_a_valid_photo_for_a_pet(): void
    {
        Storage::fake('public');
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createMascota();
        $file = UploadedFile::fake()->image('luna.jpg');

        $this->actingAs($administrator)
            ->post(route('admin.mascotas.fotos.store', $mascota), ['foto' => $file])
            ->assertRedirect(route('admin.mascotas.show', $mascota));

        $foto = FotoMascota::query()->where('mascota_id', $mascota->id)->firstOrFail();

        $this->assertSame('foto', $foto->tipo);
        $this->assertFalse($foto->es_principal);
        $this->assertSame(1, $foto->orden);
        Storage::disk('public')->assertExists($foto->url);
    }

    public function test_non_image_file_is_rejected_when_uploading_a_pet_photo(): void
    {
        Storage::fake('public');
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createMascota();

        $this->actingAs($administrator)
            ->post(route('admin.mascotas.fotos.store', $mascota), [
                'foto' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('foto');

        $this->assertDatabaseMissing('fotos_mascota', ['mascota_id' => $mascota->id]);
    }

    public function test_oversized_image_is_rejected_when_uploading_a_pet_photo(): void
    {
        Storage::fake('public');
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createMascota();

        $this->actingAs($administrator)
            ->post(route('admin.mascotas.fotos.store', $mascota), [
                'foto' => UploadedFile::fake()->image('grande.jpg')->size(5121),
            ])
            ->assertSessionHasErrors('foto');

        $this->assertDatabaseMissing('fotos_mascota', ['mascota_id' => $mascota->id]);
    }

    public function test_administrator_can_set_a_photo_as_principal_without_leaving_two_principal_photos(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createMascota();
        $first = $this->createFoto($mascota, ['orden' => 1]);
        $second = $this->createFoto($mascota, ['orden' => 2]);

        $this->actingAs($administrator)
            ->patch(route('admin.mascotas.fotos.principal', [$mascota, $first]))
            ->assertRedirect(route('admin.mascotas.show', $mascota));

        $this->actingAs($administrator)
            ->patch(route('admin.mascotas.fotos.principal', [$mascota, $second]))
            ->assertRedirect(route('admin.mascotas.show', $mascota));

        $this->assertDatabaseHas('fotos_mascota', ['id' => $first->id, 'es_principal' => false]);
        $this->assertDatabaseHas('fotos_mascota', ['id' => $second->id, 'es_principal' => true]);
        $this->assertSame(1, FotoMascota::query()
            ->where('mascota_id', $mascota->id)
            ->where('es_principal', true)
            ->count());
    }

    public function test_administrator_can_delete_a_pet_photo_and_its_file(): void
    {
        Storage::fake('public');
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createMascota();
        $path = 'mascotas/prueba/eliminar.jpg';
        Storage::disk('public')->put($path, 'archivo de prueba');
        $foto = $this->createFoto($mascota, ['url' => $path]);

        $this->actingAs($administrator)
            ->delete(route('admin.mascotas.fotos.destroy', [$mascota, $foto]))
            ->assertRedirect(route('admin.mascotas.show', $mascota));

        $this->assertDatabaseMissing('fotos_mascota', ['id' => $foto->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_administrator_cannot_manipulate_a_photo_from_another_pet(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createMascota();
        $otherMascota = $this->createMascota();
        $otherPhoto = $this->createFoto($otherMascota);

        $this->actingAs($administrator)
            ->patch(route('admin.mascotas.fotos.principal', [$mascota, $otherPhoto]))
            ->assertNotFound();

        $this->actingAs($administrator)
            ->delete(route('admin.mascotas.fotos.destroy', [$mascota, $otherPhoto]))
            ->assertNotFound();

        $this->assertDatabaseHas('fotos_mascota', ['id' => $otherPhoto->id]);
    }

    public function test_administrator_sees_pet_data_and_principal_photo(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $mascota = $this->createMascota([
            'nombre' => 'Luna',
            'especie' => 'gato',
            'raza' => 'Criolla',
            'edad' => 4,
            'sexo' => 'hembra',
            'tamano' => 'pequeno',
        ]);

        FotoMascota::create([
            'mascota_id' => $mascota->id,
            'tipo' => 'foto',
            'url' => 'mascotas/luna-principal.jpg',
            'es_principal' => true,
            'orden' => 1,
        ]);

        $this->actingAs($administrator)
            ->get('/admin/mascotas')
            ->assertOk()
            ->assertSee('Luna')
            ->assertSee('Gato')
            ->assertSee('Criolla')
            ->assertSee('4 años')
            ->assertSee('Hembra')
            ->assertSee('Pequeno')
            ->assertSee('Borrador')
            ->assertSee('mascotas/luna-principal.jpg');
    }

    public function test_administrator_can_search_pets_by_name(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $match = $this->createMascota(['nombre' => 'Milo']);
        $other = $this->createMascota(['nombre' => 'Nala']);

        $this->actingAs($administrator)
            ->get('/admin/mascotas?buscar=mIlO')
            ->assertSee($match->nombre)
            ->assertDontSee($other->nombre);
    }

    public function test_administrator_can_filter_pets_by_species(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $dog = $this->createMascota(['nombre' => 'Perro filtro', 'especie' => 'perro']);
        $cat = $this->createMascota(['nombre' => 'Gato filtro', 'especie' => 'gato']);

        $this->actingAs($administrator)
            ->get('/admin/mascotas?especie=perro')
            ->assertSee($dog->nombre)
            ->assertDontSee($cat->nombre);
    }

    public function test_administrator_can_filter_pets_by_sex(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $male = $this->createMascota(['nombre' => 'Macho filtro', 'sexo' => 'macho']);
        $female = $this->createMascota(['nombre' => 'Hembra filtro', 'sexo' => 'hembra']);

        $this->actingAs($administrator)
            ->get('/admin/mascotas?sexo=macho')
            ->assertSee($male->nombre)
            ->assertDontSee($female->nombre);
    }

    public function test_administrator_can_filter_pets_by_size(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $large = $this->createMascota(['nombre' => 'Grande filtro', 'tamano' => 'grande']);
        $small = $this->createMascota(['nombre' => 'Pequeno filtro', 'tamano' => 'pequeno']);

        $this->actingAs($administrator)
            ->get('/admin/mascotas?tamano=grande')
            ->assertSee($large->nombre)
            ->assertDontSee($small->nombre);
    }

    public function test_administrator_can_filter_pets_by_status(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $draft = $this->createMascota(['nombre' => 'Borrador filtro', 'estado' => 'borrador']);
        $available = $this->createAvailableMascota(['nombre' => 'Disponible filtro']);

        $this->actingAs($administrator)
            ->get('/admin/mascotas?estado=disponible')
            ->assertSee($available->nombre)
            ->assertDontSee($draft->nombre);
    }

    public function test_administrator_can_combine_pet_filters(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);
        $match = $this->createMascota([
            'nombre' => 'Luna combinada',
            'especie' => 'gato',
            'sexo' => 'hembra',
            'tamano' => 'pequeno',
        ]);
        $other = $this->createMascota([
            'nombre' => 'Luna distinta',
            'especie' => 'perro',
            'sexo' => 'macho',
            'tamano' => 'grande',
        ]);

        $this->actingAs($administrator)
            ->get('/admin/mascotas?buscar=Luna&especie=gato&sexo=hembra&tamano=pequeno&estado=borrador')
            ->assertSee($match->nombre)
            ->assertDontSee($other->nombre);
    }

    public function test_pagination_preserves_pet_filters(): void
    {
        $administrator = User::factory()->create(['rol' => 'administrador']);

        foreach (range(1, 16) as $number) {
            $this->createMascota([
                'nombre' => "Perro {$number}",
                'especie' => 'perro',
            ]);
        }

        $this->actingAs($administrator)
            ->get('/admin/mascotas?especie=perro')
            ->assertOk()
            ->assertSee('especie=perro')
            ->assertSee('page=2');
    }

    public function test_pet_management_defines_only_expected_registration_and_photo_routes(): void
    {
        $petRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'admin/mascotas'));

        $this->assertCount(7, $petRoutes);
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('admin.mascotas.index')->methods());
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('admin.mascotas.create')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('admin.mascotas.store')->methods());
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('admin.mascotas.show')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('admin.mascotas.fotos.store')->methods());
        $this->assertSame(['PATCH'], Route::getRoutes()->getByName('admin.mascotas.fotos.principal')->methods());
        $this->assertSame(['DELETE'], Route::getRoutes()->getByName('admin.mascotas.fotos.destroy')->methods());
    }

    private function createMascota(array $attributes = []): Mascota
    {
        $this->registrant ??= User::factory()->create(['rol' => 'administrador']);

        return Mascota::create(array_merge([
            'nombre' => fake()->unique()->firstName(),
            'especie' => 'perro',
            'raza' => 'Mestiza',
            'edad' => 3,
            'sexo' => 'macho',
            'color' => 'Marrón',
            'tamano' => 'mediano',
            'descripcion' => 'Mascota de prueba',
            'estado' => 'borrador',
            'registrado_por' => $this->registrant->id,
        ], $attributes));
    }

    private function createAvailableMascota(array $attributes = []): Mascota
    {
        $mascota = $this->createMascota($attributes);

        FotoMascota::create([
            'mascota_id' => $mascota->id,
            'tipo' => 'foto',
            'url' => "mascotas/{$mascota->id}.jpg",
            'es_principal' => true,
            'orden' => 1,
        ]);

        $mascota->update(['estado' => 'disponible']);

        return $mascota;
    }

    private function createFoto(Mascota $mascota, array $attributes = []): FotoMascota
    {
        return FotoMascota::create(array_merge([
            'mascota_id' => $mascota->id,
            'tipo' => 'foto',
            'url' => "mascotas/{$mascota->id}/foto.jpg",
            'es_principal' => false,
            'orden' => 1,
        ], $attributes));
    }

    private function registrationData(): array
    {
        return [
            'nombre' => 'Canela',
            'especie' => 'perro',
            'raza' => 'Mestiza',
            'edad' => 2,
            'sexo' => 'hembra',
            'color' => 'Canela',
            'tamano' => 'mediano',
            'descripcion' => 'Mascota registrada desde el formulario.',
        ];
    }
}
