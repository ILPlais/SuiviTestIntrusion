<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fabrique d'organisations pour les tests et les données de démonstration.
 *
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
	/**
	 * État par défaut d'une organisation fabriquée.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		$name = fake()->unique()->company();

		return [
			'name' => $name,
			'legal_name' => $name.' SAS',
			'presentation' => fake()->sentence(),
		];
	}
}
