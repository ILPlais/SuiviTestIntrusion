<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Fabrique de comptes utilisateur pour les tests.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
	/**
	 * Empreinte du mot de passe commune aux comptes fabriqués (calculée une fois).
	 *
	 * @var ?string
	 */
	protected static ?string $password;

	/**
	 * État par défaut d'un compte fabriqué.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		return [
			'name' => fake()->name(),
			'email' => fake()->unique()->safeEmail(),
			'email_verified_at' => now(),
			'password' => static::$password ??= Hash::make('password'),
			'remember_token' => Str::random(10),
		];
	}

	/**
	 * Variante : adresse e-mail non vérifiée.
	 *
	 * @return static
	 */
	public function unverified(): static
	{
		return $this->state(fn (array $attributes) => [
			'email_verified_at' => null,
		]);
	}
}
