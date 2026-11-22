<?php

namespace Database\Factories;

use App\Enums\TestExposure;
use App\Enums\TestStatus;
use App\Enums\TestType;
use App\Models\PenetrationTest;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fabrique de tests d'intrusion pour les tests et les données de démonstration.
 *
 * Elle écrit la ligne seule, sans phases ni historique : un vrai test se crée
 * par PenetrationTest::open().
 *
 * @extends Factory<PenetrationTest>
 */
class PenetrationTestFactory extends Factory
{
	/**
	 * État par défaut d'un test fabriqué.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		return [
			'tenant_id' => Tenant::factory(),
			'reference' => 'PT-'.fake()->unique()->numerify('####-###'),
			'title' => 'Test '.fake()->words(3, true),
			'type' => fake()->randomElement(TestType::cases()),
			'exposure' => fake()->randomElement(TestExposure::cases()),
			'status' => TestStatus::Planned,
			'planned_start' => null,
			'planned_end' => null,
		];
	}
}
