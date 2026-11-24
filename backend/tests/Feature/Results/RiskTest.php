<?php

namespace Tests\Feature\Results;

use App\Enums\TenantRole;
use App\Models\PenetrationTest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Risque global d'un test (resultats-1, 11.12) : calculé à la lecture sur les
 * vulnérabilités ouvertes (remédiation non vérifiée) ; gravité technique,
 * score moyen pondéré par la priorité, profil d'impact business, compteurs.
 */
class RiskTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * Organisation cliente.
	 *
	 * @var Tenant
	 */
	private Tenant $tenant;

	/**
	 * Test ouvert dans l'organisation.
	 *
	 * @var PenetrationTest
	 */
	private PenetrationTest $test;

	/**
	 * Crée l'organisation, un chef de projet connecté et un test.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->tenant = Tenant::factory()->create();
		$manager = User::factory()->create();
		$manager->tenants()->attach($this->tenant->id, ['role' => TenantRole::ProjectManager->value]);
		Sanctum::actingAs($manager);

		$this->test = PenetrationTest::open([
			'tenant_id' => $this->tenant->id,
			'reference' => 'PT-1',
			'title' => 'Test',
			'type' => 'grey_box',
			'exposure' => 'external',
		], $manager);
	}

	/**
	 * Documente une vulnérabilité et renvoie son identifiant.
	 *
	 * @param string|null $vector Vecteur CVSS v3.1, ou null.
	 * @param string $phase Code de la phase.
	 * @param array<string, int> $impact Impact business.
	 * @return string
	 */
	private function vulnerability(?string $vector, string $phase = 'exploitation', array $impact = []): string
	{
		return $this->postJson("/api/v1/tests/{$this->test->id}/vulnerabilities", [
			'title' => 'Constat',
			'phase_id' => $this->test->phases()->where('code', $phase)->firstOrFail()->id,
			'cvss_vector' => $vector,
			'business_impact' => $impact,
		])->assertCreated()->json('data.id');
	}

	/**
	 * Un test sans vulnérabilité n'a ni gravité ni score moyen.
	 *
	 * @return void
	 */
	public function test_a_test_without_vulnerabilities_has_no_risk(): void
	{
		$this->getJson("/api/v1/tests/{$this->test->id}/risk")
			->assertOk()
			->assertJsonPath('data.max_score', null)
			->assertJsonPath('data.max_severity', null)
			->assertJsonPath('data.weighted_average', null)
			->assertJsonPath('data.open_count', 0)
			->assertJsonPath('data.business_profile', ['operational' => 0, 'legal' => 0, 'financial' => 0, 'reputation' => 0]);
	}

	/**
	 * Gravité technique, score moyen pondéré, profil d'impact et compteurs ne
	 * portent que sur les vulnérabilités ouvertes ; une vulnérabilité vérifiée
	 * ne compte plus que dans les statuts de remédiation.
	 *
	 * @return void
	 */
	public function test_the_global_risk_follows_open_vulnerabilities(): void
	{
		// Critique 9,8 (P1), Important 5,9 (P3), Mineur 1,6 (P4), et une non cotée (P4).
		$critical = $this->vulnerability('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', 'exploitation', ['operational' => 3, 'reputation' => 2]);
		$this->vulnerability('CVSS:3.1/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:N/A:N', 'enumeration', ['legal' => 2]);
		$this->vulnerability('CVSS:3.1/AV:P/AC:H/PR:H/UI:R/S:U/C:L/I:N/A:N', 'enumeration', ['financial' => 1]);
		$this->vulnerability(null);

		$risk = $this->getJson("/api/v1/tests/{$this->test->id}/risk")->assertOk();
		$risk->assertJsonPath('data.max_score', 9.8)
			->assertJsonPath('data.max_severity', 'critical')
			->assertJsonPath('data.cvss_version', '3.1')
			// (4 × 9,8 + 2 × 5,9 + 1 × 1,6) / (4 + 2 + 1) = 52,6 / 7 = 7,51… → 7,5
			->assertJsonPath('data.weighted_average', 7.5)
			->assertJsonPath('data.open_count', 4)
			->assertJsonPath('data.total_count', 4)
			->assertJsonPath('data.business_profile', ['operational' => 3, 'legal' => 2, 'financial' => 1, 'reputation' => 2])
			->assertJsonPath('data.by_severity', ['none' => 0, 'minor' => 1, 'important' => 1, 'major' => 0, 'critical' => 1, 'unrated' => 1])
			->assertJsonPath('data.by_priority', ['P1' => 1, 'P2' => 0, 'P3' => 1, 'P4' => 2])
			->assertJsonPath('data.by_phase.exploitation', 2)
			->assertJsonPath('data.by_phase.enumeration', 2)
			->assertJsonPath('data.by_phase.reconnaissance', 0)
			->assertJsonPath('data.by_remediation_status', ['todo' => 4, 'in_progress' => 0, 'fixed' => 0, 'verified' => 0]);

		// La critique est corrigée et vérifiée : elle sort des indicateurs.
		$this->patchJson("/api/v1/vulnerabilities/{$critical}/remediation", ['status' => 'verified', 'retest_result' => 'Correction efficace'])->assertOk();

		$this->getJson("/api/v1/tests/{$this->test->id}/risk")
			->assertJsonPath('data.max_score', 5.9)
			->assertJsonPath('data.max_severity', 'important')
			// (2 × 5,9 + 1 × 1,6) / 3 = 4,46… → 4,5
			->assertJsonPath('data.weighted_average', 4.5)
			->assertJsonPath('data.open_count', 3)
			->assertJsonPath('data.business_profile.operational', 0)
			->assertJsonPath('data.by_severity.critical', 0)
			->assertJsonPath('data.by_remediation_status.verified', 1);
	}

	/**
	 * La liste et la fiche des tests portent le risque en résumé (score maximal
	 * et niveau des vulnérabilités ouvertes), pour la colonne « Risque » et le
	 * bandeau du test (maquettes 01 et 02 de 15.01).
	 *
	 * @return void
	 */
	public function test_tests_carry_a_risk_summary(): void
	{
		$this->getJson('/api/v1/tests')->assertJsonPath('data.0.risk', ['max_score' => null, 'max_severity' => null]);

		$this->vulnerability('CVSS:3.1/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:N/A:N');
		$this->vulnerability('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H');

		$this->getJson('/api/v1/tests')->assertJsonPath('data.0.risk', ['max_score' => 9.8, 'max_severity' => 'critical']);
		$this->getJson("/api/v1/tests/{$this->test->id}")->assertJsonPath('data.risk.max_severity', 'critical');
	}

	/**
	 * Le risque d'un test étranger est introuvable.
	 *
	 * @return void
	 */
	public function test_the_risk_of_a_foreign_test_is_not_found(): void
	{
		$stranger = User::factory()->create();
		$stranger->tenants()->attach(Tenant::factory()->create()->id, ['role' => TenantRole::ProjectManager->value]);
		Sanctum::actingAs($stranger);

		$this->getJson("/api/v1/tests/{$this->test->id}/risk")->assertNotFound();
	}
}
