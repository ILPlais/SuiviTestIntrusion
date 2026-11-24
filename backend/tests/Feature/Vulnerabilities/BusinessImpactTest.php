<?php

namespace Tests\Feature\Vulnerabilities;

use App\Enums\TenantRole;
use App\Models\PenetrationTest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Impact business d'une vulnérabilité (vulnerabilites-4, 11.09) : quatre axes
 * (opérationnel, légal, financier, image) notés de 0 (non applicable) à 4.
 */
class BusinessImpactTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * Crée un testeur, un test et une vulnérabilité, puis renvoie l'URL de la fiche.
	 *
	 * @return string
	 */
	private function vulnerabilityUrl(): string
	{
		$tenant = Tenant::factory()->create();
		$tester = User::factory()->create();
		$tester->tenants()->attach($tenant->id, ['role' => TenantRole::Tester->value]);
		Sanctum::actingAs($tester);

		$test = PenetrationTest::open([
			'tenant_id' => $tenant->id,
			'reference' => 'PT-1',
			'title' => 'Test',
			'type' => 'grey_box',
			'exposure' => 'external',
		], $tester);
		$phase = $test->phases()->firstOrFail()->id;

		$id = $this->postJson("/api/v1/tests/{$test->id}/vulnerabilities", ['title' => 'Constat', 'phase_id' => $phase])
			->assertCreated()
			->json('data.id');

		return "/api/v1/vulnerabilities/{$id}";
	}

	/**
	 * Une nouvelle fiche part de « non applicable » sur les quatre axes, puis
	 * chaque axe se note de 0 à 4.
	 *
	 * @return void
	 */
	public function test_the_four_axes_are_rated_from_zero_to_four(): void
	{
		$url = $this->vulnerabilityUrl();

		$this->getJson($url)
			->assertJsonPath('data.business_impact', ['operational' => 0, 'legal' => 0, 'financial' => 0, 'reputation' => 0]);

		$this->patchJson($url, ['business_impact' => ['operational' => 3, 'legal' => 2, 'financial' => 1, 'reputation' => 4]])
			->assertOk()
			->assertJsonPath('data.business_impact', ['operational' => 3, 'legal' => 2, 'financial' => 1, 'reputation' => 4]);

		// Un axe seul se modifie sans toucher aux autres.
		$this->patchJson($url, ['business_impact' => ['legal' => 0]])
			->assertOk()
			->assertJsonPath('data.business_impact.legal', 0)
			->assertJsonPath('data.business_impact.operational', 3);
	}

	/**
	 * Une note hors de 0 à 4, un axe inconnu ou une note non entière sont refusés.
	 *
	 * @return void
	 */
	public function test_invalid_ratings_are_rejected(): void
	{
		$url = $this->vulnerabilityUrl();

		$this->patchJson($url, ['business_impact' => ['operational' => 5]])
			->assertUnprocessable()->assertJsonValidationErrors('business_impact.operational', 'details');
		$this->patchJson($url, ['business_impact' => ['legal' => -1]])
			->assertUnprocessable()->assertJsonValidationErrors('business_impact.legal', 'details');
		$this->patchJson($url, ['business_impact' => ['financial' => 'élevé']])
			->assertUnprocessable()->assertJsonValidationErrors('business_impact.financial', 'details');
		$this->patchJson($url, ['business_impact' => ['commercial' => 2]])
			->assertUnprocessable()->assertJsonValidationErrors('business_impact', 'details');
	}
}
