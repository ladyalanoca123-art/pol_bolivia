<?php

namespace Tests\Feature\Admin;

use App\Models\FotoMascota;
use App\Models\Mascota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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

    public function test_pet_management_defines_only_a_read_listing_route(): void
    {
        $petRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'admin/mascotas'));

        $this->assertCount(1, $petRoutes);
        $this->assertSame(['GET', 'HEAD'], $petRoutes->first()->methods());
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
}
