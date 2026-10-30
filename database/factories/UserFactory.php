<?php

namespace Database\Factories;

use App\Models\AceptacionLegal;
use App\Models\User;
use App\Support\AceptacionDocumentosLegales;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'telefono' => fake()->optional()->numerify('7#######'),
            'rol' => 'ciudadano',
            'estado' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /** Las cuentas de prueba ya aceptaron los Términos y la Política de privacidad vigentes, como al registrarse. */
    public function configure(): static
    {
        return $this->afterCreating(fn (User $user) => AceptacionDocumentosLegales::registrar($user, null, 'registro'));
    }

    /** Cuenta que todavía no aceptó los documentos legales vigentes (p. ej. creada antes de que existieran). */
    public function sinAceptacionLegal(): static
    {
        return $this->afterCreating(fn (User $user) => AceptacionLegal::query()->where('usuario_id', $user->id)->delete());
    }

    /**
     * Indicate that the user's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
