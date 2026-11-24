<?php

namespace Tests\Feature\Vulnerabilities;

use App\Enums\TenantRole;
use App\Models\PenetrationTest;
use App\Models\ScopeObject;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vulnerability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fiche de vulnérabilité (vulnerabilites-3, 11.09, 11.10, 14.01) : référence
 * automatique par test, CVE et CWE, description, phase, cotation CVSS v3.1
 * calculée par le serveur (version et vecteur gardés), composants du
 * périmètre ; écriture par l'équipe du test, lecture pour l'organisation.
 */
class VulnerabilitiesTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * Organisation cliente.
	 *
	 * @var Tenant
	 */
	private Tenant $client;

	/**
	 * Test ouvert dans l'organisation cliente, avec ses cinq phases.
	 *
	 * @var PenetrationTest
	 */
	private PenetrationTest $test;

	/**
	 * Testeur de l'organisation cliente.
	 *
	 * @var User
	 */
	private User $tester;

	/**
	 * Crée l'organisation, un testeur et un test ouvert avec ses phases.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->client = Tenant::factory()->create(['name' => 'Alpha']);
		$this->tester = $this->member(TenantRole::Tester);
		$this->test = $this->openTest($this->client, $this->tester);
	}

	/**
	 * Crée un membre d'une organisation avec un rôle.
	 *
	 * @param TenantRole $role Rôle dans l'organisation.
	 * @param Tenant|null $tenant Organisation ; par défaut, l'organisation cliente.
	 * @return User
	 */
	private function member(TenantRole $role, ?Tenant $tenant = null): User
	{
		$user = User::factory()->create();
		$user->tenants()->attach(($tenant ?? $this->client)->id, ['role' => $role->value]);

		return $user;
	}

	/**
	 * Ouvre un test (avec ses phases) sans passer par l'API.
	 *
	 * @param Tenant $tenant Organisation du test.
	 * @param User $author Auteur.
	 * @return PenetrationTest
	 */
	private function openTest(Tenant $tenant, User $author): PenetrationTest
	{
		return PenetrationTest::withoutTenantRestriction(fn () => PenetrationTest::open([
			'tenant_id' => $tenant->id,
			'reference' => 'PT-'.fake()->unique()->numerify('####'),
			'title' => 'Test',
			'type' => 'grey_box',
			'exposure' => 'external',
		], $author));
	}

	/**
	 * Identifiant d'une phase du test.
	 *
	 * @param PenetrationTest $test Test.
	 * @param string $code Code de la phase.
	 * @return string
	 */
	private function phase(PenetrationTest $test, string $code = 'exploitation'): string
	{
		return PenetrationTest::withoutTenantRestriction(fn () => $test->phases()->where('code', $code)->firstOrFail()->id);
	}

	/**
	 * Données valides d'une nouvelle vulnérabilité.
	 *
	 * @param array<string, mixed> $overrides Champs à remplacer.
	 * @return array<string, mixed>
	 */
	private function payload(array $overrides = []): array
	{
		return array_merge([
			'title' => 'Exécution de code à distance sur le CMS',
			'description' => 'Désérialisation dans le module d\'**aperçu**.',
			'phase_id' => $this->phase($this->test),
			'cve_ids' => ['CVE-2026-0042'],
			'cwe_ids' => ['CWE-502'],
			'cvss_vector' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H',
		], $overrides);
	}

	/**
	 * Un testeur documente un constat : référence V-001, score et niveau
	 * calculés par le serveur, version et vecteur gardés.
	 *
	 * @return void
	 */
	public function test_a_tester_documents_a_rated_vulnerability(): void
	{
		Sanctum::actingAs($this->tester);

		$this->postJson("/api/v1/tests/{$this->test->id}/vulnerabilities", $this->payload())
			->assertCreated()
			->assertJsonPath('data.reference', 'V-001')
			->assertJsonPath('data.title', 'Exécution de code à distance sur le CMS')
			->assertJsonPath('data.phase.code', 'exploitation')
			->assertJsonPath('data.cve_ids', ['CVE-2026-0042'])
			->assertJsonPath('data.cwe_ids', ['CWE-502'])
			->assertJsonPath('data.cvss_version', '3.1')
			->assertJsonPath('data.cvss_vector', 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H')
			->assertJsonPath('data.cvss_score', 9.8)
			->assertJsonPath('data.severity', 'critical')
			->assertJsonPath('data.created_by', $this->tester->name);

		// Les références se suivent dans le test.
		$this->postJson("/api/v1/tests/{$this->test->id}/vulnerabilities", $this->payload(['title' => 'Second constat']))
			->assertJsonPath('data.reference', 'V-002');
	}

	/**
	 * Un score envoyé par le client est ignoré ; un vecteur dans le désordre est
	 * remis dans l'ordre ; un constat peut attendre sa cotation.
	 *
	 * @return void
	 */
	public function test_the_server_alone_computes_the_score(): void
	{
		Sanctum::actingAs($this->tester);
		$url = "/api/v1/tests/{$this->test->id}/vulnerabilities";

		$this->postJson($url, $this->payload(['cvss_vector' => 'CVSS:3.1/C:L/I:L/A:N/AV:N/AC:L/PR:N/UI:R/S:C', 'cvss_score' => 10, 'severity' => 'critical']))
			->assertCreated()
			->assertJsonPath('data.cvss_vector', 'CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:L/A:N')
			->assertJsonPath('data.cvss_score', 6.1)
			->assertJsonPath('data.severity', 'important');

		$unrated = $this->postJson($url, $this->payload(['cvss_vector' => null]))
			->assertCreated()
			->assertJsonPath('data.cvss_score', null)
			->assertJsonPath('data.severity', null)
			->json('data.id');

		$this->patchJson("/api/v1/vulnerabilities/{$unrated}", ['cvss_vector' => 'CVSS:3.1/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:N/A:N'])
			->assertOk()
			->assertJsonPath('data.cvss_score', 5.9)
			->assertJsonPath('data.severity', 'important');
	}

	/**
	 * Vecteur, identifiants CVE et CWE, titre et phase sont contrôlés.
	 *
	 * @return void
	 */
	public function test_invalid_fields_are_rejected(): void
	{
		Sanctum::actingAs($this->tester);
		$url = "/api/v1/tests/{$this->test->id}/vulnerabilities";
		$otherPhase = $this->phase($this->openTest($this->client, $this->tester));

		$this->postJson($url, $this->payload(['cvss_vector' => 'CVSS:3.0/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H']))
			->assertUnprocessable()->assertJsonValidationErrors('cvss_vector', 'details');
		$this->postJson($url, $this->payload(['cve_ids' => ['2026-0042']]))
			->assertUnprocessable()->assertJsonValidationErrors('cve_ids.0', 'details');
		$this->postJson($url, $this->payload(['cwe_ids' => ['XSS']]))
			->assertUnprocessable()->assertJsonValidationErrors('cwe_ids.0', 'details');
		$this->postJson($url, $this->payload(['phase_id' => $otherPhase]))
			->assertUnprocessable()->assertJsonValidationErrors('phase_id', 'details');
		$this->postJson($url, $this->payload(['phase_id' => null]))
			->assertUnprocessable()->assertJsonValidationErrors('phase_id', 'details');
		$this->postJson($url, $this->payload(['title' => '']))
			->assertUnprocessable()->assertJsonValidationErrors('title', 'details');
	}

	/**
	 * Les composants concernés sont des objets du périmètre du même test.
	 *
	 * @return void
	 */
	public function test_affected_components_belong_to_the_scope_of_the_test(): void
	{
		Sanctum::actingAs($this->tester);
		$host = ScopeObject::declare($this->test, ['type' => 'host', 'address' => '203.0.113.10', 'hostname' => 'srv-web01'])->id;
		$otherHost = ScopeObject::declare($this->openTest($this->client, $this->tester), ['type' => 'host', 'address' => '192.0.2.1'])->id;
		$url = "/api/v1/tests/{$this->test->id}/vulnerabilities";

		$id = $this->postJson($url, $this->payload(['scope_object_ids' => [$host]]))
			->assertCreated()
			->assertJsonPath('data.scope_objects.0.id', $host)
			->assertJsonPath('data.scope_objects.0.label', 'srv-web01')
			->json('data.id');

		$this->postJson($url, $this->payload(['scope_object_ids' => [$otherHost]]))
			->assertUnprocessable()->assertJsonValidationErrors('scope_object_ids.0', 'details');

		$this->patchJson("/api/v1/vulnerabilities/{$id}", ['scope_object_ids' => []])
			->assertOk()
			->assertJsonCount(0, 'data.scope_objects');
	}

	/**
	 * La liste ne montre que les vulnérabilités du test, filtrables par sévérité
	 * et triables par score.
	 *
	 * @return void
	 */
	public function test_the_list_shows_the_vulnerabilities_of_the_test(): void
	{
		Sanctum::actingAs($this->tester);
		$url = "/api/v1/tests/{$this->test->id}/vulnerabilities";
		$this->postJson($url, $this->payload());
		$this->postJson($url, $this->payload(['cvss_vector' => 'CVSS:3.1/AV:P/AC:H/PR:H/UI:R/S:U/C:L/I:N/A:N']));
		$other = $this->openTest($this->client, $this->tester);
		$this->postJson("/api/v1/tests/{$other->id}/vulnerabilities", $this->payload(['phase_id' => $this->phase($other)]))->assertCreated();

		$this->getJson("{$url}?page=1")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
		$this->getJson("{$url}?filter[severity]=minor")->assertJsonCount(1, 'data');
		$this->getJson("{$url}?sort=cvss_score&order=desc")->assertJsonPath('data.0.cvss_score', 9.8);
	}

	/**
	 * Le client lit sans écrire ; une organisation étrangère est introuvable ;
	 * la suppression est définitive.
	 *
	 * @return void
	 */
	public function test_rights_and_isolation(): void
	{
		Sanctum::actingAs($this->tester);
		$id = $this->postJson("/api/v1/tests/{$this->test->id}/vulnerabilities", $this->payload())->json('data.id');

		Sanctum::actingAs($this->member(TenantRole::Client));
		$this->getJson("/api/v1/vulnerabilities/{$id}")->assertOk()->assertJsonPath('data.can_manage', false);
		$this->postJson("/api/v1/tests/{$this->test->id}/vulnerabilities", $this->payload())->assertForbidden();
		$this->patchJson("/api/v1/vulnerabilities/{$id}", ['title' => 'Autre'])->assertForbidden();
		$this->deleteJson("/api/v1/vulnerabilities/{$id}")->assertForbidden();

		$foreign = Tenant::factory()->create();
		Sanctum::actingAs($this->member(TenantRole::ProjectManager, $foreign));
		$this->getJson("/api/v1/vulnerabilities/{$id}")->assertNotFound();
		$this->getJson("/api/v1/tests/{$this->test->id}/vulnerabilities")->assertNotFound();

		Sanctum::actingAs($this->tester);
		$this->deleteJson("/api/v1/vulnerabilities/{$id}")->assertNoContent();
		$this->assertSame(0, Vulnerability::withoutTenantRestriction(fn () => Vulnerability::query()->count()));
	}
}
