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
 * Fiche de remédiation (vulnerabilites-5, 11.09, 14.01) : créée avec la
 * vulnérabilité, priorité suggérée depuis le niveau CVSS et surchargeable par
 * le chef de projet, recommandations et ressources de l'équipe, statut jusqu'au
 * retest daté ; le client peut déclarer la correction.
 */
class RemediationTest extends TestCase
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
	 * Crée l'organisation et un test ouvert.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->tenant = Tenant::factory()->create();
		$this->test = PenetrationTest::withoutTenantRestriction(fn () => PenetrationTest::open([
			'tenant_id' => $this->tenant->id,
			'reference' => 'PT-1',
			'title' => 'Test',
			'type' => 'grey_box',
			'exposure' => 'external',
		], User::factory()->create()));
	}

	/**
	 * Crée un membre de l'organisation avec un rôle.
	 *
	 * @param TenantRole $role Rôle dans l'organisation.
	 * @return User
	 */
	private function member(TenantRole $role): User
	{
		$user = User::factory()->create(['name' => ucfirst($role->value)]);
		$user->tenants()->attach($this->tenant->id, ['role' => $role->value]);

		return $user;
	}

	/**
	 * Documente une vulnérabilité (en testeur) et renvoie son identifiant.
	 *
	 * @param string|null $vector Vecteur CVSS v3.1, ou null.
	 * @return string
	 */
	private function vulnerability(?string $vector): string
	{
		Sanctum::actingAs($this->member(TenantRole::Tester));
		$phase = PenetrationTest::withoutTenantRestriction(fn () => $this->test->phases()->firstOrFail()->id);

		return $this->postJson("/api/v1/tests/{$this->test->id}/vulnerabilities", [
			'title' => 'Constat',
			'phase_id' => $phase,
			'cvss_vector' => $vector,
		])->assertCreated()->json('data.id');
	}

	/**
	 * La fiche naît avec la vulnérabilité, à faire, avec la priorité suggérée
	 * par le niveau CVSS ; la suggestion suit la cotation.
	 *
	 * @return void
	 */
	public function test_the_priority_is_suggested_from_the_severity(): void
	{
		$critical = $this->vulnerability('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H');
		$this->getJson("/api/v1/vulnerabilities/{$critical}")
			->assertJsonPath('data.remediation.status', 'todo')
			->assertJsonPath('data.remediation.priority', 'P1')
			->assertJsonPath('data.remediation.suggested_priority', 'P1')
			->assertJsonPath('data.remediation.priority_overridden', false);

		$this->patchJson("/api/v1/vulnerabilities/{$critical}", ['cvss_vector' => 'CVSS:3.1/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:N/A:N'])
			->assertJsonPath('data.severity', 'important')
			->assertJsonPath('data.remediation.priority', 'P3');

		$unrated = $this->vulnerability(null);
		$this->getJson("/api/v1/vulnerabilities/{$unrated}")->assertJsonPath('data.remediation.priority', 'P4');

		$major = $this->vulnerability('CVSS:3.1/AV:L/AC:L/PR:L/UI:N/S:U/C:H/I:H/A:H');
		$this->getJson("/api/v1/vulnerabilities/{$major}")->assertJsonPath('data.remediation.priority', 'P2');
	}

	/**
	 * Le chef de projet surcharge la priorité, qui ne suit plus la cotation ;
	 * une priorité nulle revient à la suggestion. Un testeur ne la surcharge pas.
	 *
	 * @return void
	 */
	public function test_the_project_manager_overrides_the_priority(): void
	{
		$id = $this->vulnerability('CVSS:3.1/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:N/A:N');
		$url = "/api/v1/vulnerabilities/{$id}/remediation";

		$this->patchJson($url, ['priority' => 'P1'])->assertForbidden();

		Sanctum::actingAs($this->member(TenantRole::ProjectManager));
		$this->patchJson($url, ['priority' => 'P2'])
			->assertOk()
			->assertJsonPath('data.remediation.priority', 'P2')
			->assertJsonPath('data.remediation.suggested_priority', 'P3')
			->assertJsonPath('data.remediation.priority_overridden', true);

		$this->patchJson("/api/v1/vulnerabilities/{$id}", ['cvss_vector' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H'])
			->assertJsonPath('data.remediation.priority', 'P2');

		$this->patchJson($url, ['priority' => null])
			->assertOk()
			->assertJsonPath('data.remediation.priority', 'P1')
			->assertJsonPath('data.remediation.priority_overridden', false);

		$this->patchJson($url, ['priority' => 'P5'])->assertUnprocessable()->assertJsonValidationErrors('priority', 'details');
	}

	/**
	 * L'équipe rédige les recommandations et les ressources, puis suit le statut
	 * jusqu'au retest daté ; « Vérifié » exige un résultat.
	 *
	 * @return void
	 */
	public function test_the_team_follows_the_remediation_until_the_retest(): void
	{
		$id = $this->vulnerability('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H');
		$tester = $this->member(TenantRole::Tester);
		Sanctum::actingAs($tester);
		$url = "/api/v1/vulnerabilities/{$id}/remediation";

		$this->patchJson($url, [
			'recommendations' => 'Mettre à jour le CMS en **4.2.3**.',
			'resources' => ['https://cve.example.test/CVE-2026-0042'],
			'status' => 'in_progress',
		])
			->assertOk()
			->assertJsonPath('data.remediation.recommendations', 'Mettre à jour le CMS en **4.2.3**.')
			->assertJsonPath('data.remediation.resources', ['https://cve.example.test/CVE-2026-0042'])
			->assertJsonPath('data.remediation.status', 'in_progress');

		$this->patchJson($url, ['resources' => ['javascript:alert(1)']])
			->assertUnprocessable()->assertJsonValidationErrors('resources.0', 'details');
		$this->patchJson($url, ['status' => 'verified'])
			->assertUnprocessable()->assertJsonValidationErrors('retest_result', 'details');

		$this->patchJson($url, ['status' => 'verified', 'retest_result' => 'Correction efficace'])
			->assertOk()
			->assertJsonPath('data.remediation.status', 'verified')
			->assertJsonPath('data.remediation.retest_result', 'Correction efficace')
			->assertJsonPath('data.remediation.verified_by', $tester->name);
		$this->assertNotNull($this->getJson("/api/v1/vulnerabilities/{$id}")->json('data.remediation.verified_at'));

		// Rouvrir efface le retest.
		$this->patchJson($url, ['status' => 'in_progress'])
			->assertJsonPath('data.remediation.verified_at', null)
			->assertJsonPath('data.remediation.retest_result', null);
	}

	/**
	 * Une vérification ne se défait que par l'équipe : le client ne peut pas
	 * redéclarer « Corrigé », et réenregistrer la fiche garde la date et l'auteur
	 * du retest d'origine.
	 *
	 * @return void
	 */
	public function test_a_verification_is_kept(): void
	{
		$id = $this->vulnerability('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H');
		$url = "/api/v1/vulnerabilities/{$id}/remediation";
		$verifier = $this->member(TenantRole::Tester);
		Sanctum::actingAs($verifier);
		$verifiedAt = $this->patchJson($url, ['status' => 'verified', 'retest_result' => 'Correction efficace'])->json('data.remediation.verified_at');

		Sanctum::actingAs($this->member(TenantRole::Client));
		$this->patchJson($url, ['status' => 'fixed'])->assertUnprocessable()->assertJsonValidationErrors('status', 'details');

		$this->travel(2)->hours();
		$other = $this->member(TenantRole::Tester);
		$other->forceFill(['name' => 'Autre testeur'])->save();
		Sanctum::actingAs($other);
		$this->patchJson($url, ['recommendations' => 'Texte corrigé.', 'status' => 'verified', 'retest_result' => 'Correction efficace, revérifiée'])
			->assertOk()
			->assertJsonPath('data.remediation.verified_at', $verifiedAt)
			->assertJsonPath('data.remediation.verified_by', $verifier->name)
			->assertJsonPath('data.remediation.retest_result', 'Correction efficace, revérifiée');
	}

	/**
	 * La remédiation d'une vulnérabilité d'une autre organisation est introuvable.
	 *
	 * @return void
	 */
	public function test_a_foreign_remediation_is_not_found(): void
	{
		$id = $this->vulnerability('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H');
		$stranger = User::factory()->create();
		$stranger->tenants()->attach(Tenant::factory()->create()->id, ['role' => TenantRole::ProjectManager->value]);
		Sanctum::actingAs($stranger);

		$this->patchJson("/api/v1/vulnerabilities/{$id}/remediation", ['status' => 'fixed'])->assertNotFound();
	}

	/**
	 * Le client déclare la correction, sans pouvoir vérifier ni rédiger.
	 *
	 * @return void
	 */
	public function test_the_client_declares_the_fix_only(): void
	{
		$id = $this->vulnerability('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H');
		Sanctum::actingAs($this->member(TenantRole::Client));
		$url = "/api/v1/vulnerabilities/{$id}/remediation";

		$this->getJson("/api/v1/vulnerabilities/{$id}")
			->assertJsonPath('data.remediation.can_edit', false)
			->assertJsonPath('data.remediation.can_declare_fixed', true);
		$this->patchJson($url, ['status' => 'fixed'])->assertOk()->assertJsonPath('data.remediation.status', 'fixed');
		$this->patchJson($url, ['status' => 'verified', 'retest_result' => 'OK'])->assertForbidden();
		$this->patchJson($url, ['recommendations' => 'Autre'])->assertForbidden();
		$this->patchJson($url, ['status' => 'fixed', 'priority' => 'P4'])->assertForbidden();
	}
}
