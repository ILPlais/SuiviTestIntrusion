<?php

namespace Tests\Feature\Engagement;

use App\Enums\TenantRole;
use App\Models\PenetrationTest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Création d'un test d'intrusion et cycle de ses statuts (engagement-2, 11.05,
 * 11.08, 14.01) : type et exposition, cinq phases fixes, transitions libres
 * tracées dans l'historique, droits du chef de projet, isolation par organisation.
 */
class PenetrationTestsTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * Organisation cliente de test.
	 *
	 * @var Tenant
	 */
	private Tenant $client;

	/**
	 * Organisation étrangère à l'utilisateur.
	 *
	 * @var Tenant
	 */
	private Tenant $foreign;

	/**
	 * Crée deux organisations clientes.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->client = Tenant::factory()->create(['name' => 'Alpha']);
		$this->foreign = Tenant::factory()->create(['name' => 'Bêta']);
	}

	/**
	 * Crée un administrateur d'instance.
	 *
	 * @return User
	 */
	private function admin(): User
	{
		$user = User::factory()->create();
		$user->forceFill(['is_instance_admin' => true])->save();

		return $user;
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
	 * Données valides d'un nouveau test dans l'organisation cliente.
	 *
	 * @param array<string, mixed> $overrides Champs à remplacer.
	 * @return array<string, mixed>
	 */
	private function payload(array $overrides = []): array
	{
		return array_merge([
			'tenant_id' => $this->client->id,
			'reference' => 'PT-2026-001',
			'title' => 'Test interne du siège',
			'type' => 'grey_box',
			'exposure' => 'internal',
			'planned_start' => '2026-12-01T08:00:00Z',
			'planned_end' => '2026-12-12T18:00:00Z',
		], $overrides);
	}

	/**
	 * Crée un test dans une organisation, sans passer par l'API.
	 *
	 * @param Tenant|null $tenant Organisation ; par défaut, l'organisation cliente.
	 * @param array<string, mixed> $attributes Champs à remplacer.
	 * @return PenetrationTest
	 */
	private function existingTest(?Tenant $tenant = null, array $attributes = []): PenetrationTest
	{
		return PenetrationTest::withoutTenantRestriction(fn () => PenetrationTest::factory()->create(array_merge(
			['tenant_id' => ($tenant ?? $this->client)->id],
			$attributes,
		)));
	}

	/**
	 * Le chef de projet crée un test typé, planifié, avec ses cinq phases et la
	 * première entrée de l'historique.
	 *
	 * @return void
	 */
	public function test_a_project_manager_creates_a_test_with_type_and_exposure(): void
	{
		$manager = $this->member(TenantRole::ProjectManager);
		Sanctum::actingAs($manager);

		$response = $this->postJson('/api/v1/tests', $this->payload())
			->assertCreated()
			->assertJsonPath('data.reference', 'PT-2026-001')
			->assertJsonPath('data.title', 'Test interne du siège')
			->assertJsonPath('data.type', 'grey_box')
			->assertJsonPath('data.exposure', 'internal')
			->assertJsonPath('data.status', 'planned')
			->assertJsonPath('data.tenant.id', $this->client->id)
			->assertJsonPath('data.tenant.name', 'Alpha')
			->assertJsonPath('data.planned_start', '2026-12-01T08:00:00Z')
			->assertJsonPath('data.planned_end', '2026-12-12T18:00:00Z')
			->assertJsonPath('data.created_by', $manager->name)
			->assertJsonPath('data.can_manage', true);

		$this->assertSame(
			['reconnaissance', 'enumeration', 'exploitation', 'post_exploitation', 'cleanup'],
			array_column($response->json('data.phases'), 'code'),
		);
		$response->assertJsonCount(1, 'data.status_changes')
			->assertJsonPath('data.status_changes.0.from', null)
			->assertJsonPath('data.status_changes.0.to', 'planned')
			->assertJsonPath('data.status_changes.0.author', $manager->name);
	}

	/**
	 * Les quatre types de 11.05 et les deux expositions sont acceptés ; toute autre
	 * valeur est refusée.
	 *
	 * @return void
	 */
	public function test_type_and_exposure_are_closed_values(): void
	{
		Sanctum::actingAs($this->member(TenantRole::ProjectManager));

		foreach (['red_team', 'black_box', 'grey_box', 'white_box'] as $index => $type) {
			$this->postJson('/api/v1/tests', $this->payload(['reference' => "PT-{$index}", 'type' => $type, 'exposure' => $index % 2 === 0 ? 'internal' : 'external']))
				->assertCreated();
		}

		$this->postJson('/api/v1/tests', $this->payload(['reference' => 'PT-X', 'type' => 'purple_team']))
			->assertUnprocessable()
			->assertJsonValidationErrors('type', 'details');
		$this->postJson('/api/v1/tests', $this->payload(['reference' => 'PT-Y', 'exposure' => 'hybrid']))
			->assertUnprocessable()
			->assertJsonValidationErrors('exposure', 'details');
	}

	/**
	 * Champs obligatoires, dates cohérentes, statut imposé à la création.
	 *
	 * @return void
	 */
	public function test_creation_is_validated(): void
	{
		Sanctum::actingAs($this->member(TenantRole::ProjectManager));

		$this->postJson('/api/v1/tests', [])
			->assertUnprocessable()
			->assertJsonValidationErrors(['tenant_id', 'reference', 'title', 'type', 'exposure'], 'details');

		$this->postJson('/api/v1/tests', $this->payload(['planned_end' => '2026-11-30T08:00:00Z']))
			->assertUnprocessable()
			->assertJsonValidationErrors('planned_end', 'details');

		// Un test naît toujours « Planifié » : le statut ne se choisit pas à la création.
		$this->postJson('/api/v1/tests', $this->payload(['status' => 'validated']))
			->assertCreated()
			->assertJsonPath('data.status', 'planned');
	}

	/**
	 * La référence est unique dans une organisation, mais deux organisations
	 * peuvent employer la même.
	 *
	 * @return void
	 */
	public function test_the_reference_is_unique_within_an_organisation(): void
	{
		$this->existingTest($this->foreign, ['reference' => 'PT-2026-001']);
		Sanctum::actingAs($this->member(TenantRole::ProjectManager));

		$this->postJson('/api/v1/tests', $this->payload())->assertCreated();
		$this->postJson('/api/v1/tests', $this->payload(['title' => 'Doublon']))
			->assertUnprocessable()
			->assertJsonValidationErrors('reference', 'details');
	}

	/**
	 * Testeurs et clients finaux ne créent pas de test.
	 *
	 * @return void
	 */
	public function test_testers_and_clients_cannot_create_a_test(): void
	{
		foreach ([TenantRole::Tester, TenantRole::Client] as $role) {
			Sanctum::actingAs($this->member($role));

			$this->postJson('/api/v1/tests', $this->payload())->assertForbidden();
		}

		$this->assertSame(0, PenetrationTest::withoutTenantRestriction(fn () => PenetrationTest::query()->count()));
	}

	/**
	 * Une organisation étrangère est refusée comme une organisation inconnue,
	 * sans révéler son existence.
	 *
	 * @return void
	 */
	public function test_a_test_cannot_be_created_in_a_foreign_organisation(): void
	{
		$this->member(TenantRole::ProjectManager, $this->foreign);
		Sanctum::actingAs($this->member(TenantRole::ProjectManager));

		$foreign = $this->postJson('/api/v1/tests', $this->payload(['tenant_id' => $this->foreign->id]))
			->assertUnprocessable()
			->assertJsonValidationErrors('tenant_id', 'details');
		$unknown = $this->postJson('/api/v1/tests', $this->payload(['tenant_id' => '01JD0000000000000000000000']))
			->assertUnprocessable();

		$this->assertSame($unknown->json('details.tenant_id'), $foreign->json('details.tenant_id'));
	}

	/**
	 * L'administrateur d'instance crée un test dans toute organisation.
	 *
	 * @return void
	 */
	public function test_an_administrator_creates_a_test_anywhere(): void
	{
		Sanctum::actingAs($this->admin());

		$this->postJson('/api/v1/tests', $this->payload(['tenant_id' => $this->foreign->id]))
			->assertCreated()
			->assertJsonPath('data.tenant.name', 'Bêta')
			->assertJsonPath('data.can_manage', true);
	}

	/**
	 * Chaque membre ne voit que les tests de ses organisations ; un test étranger
	 * répond 404 comme un test inconnu.
	 *
	 * @return void
	 */
	public function test_members_see_only_the_tests_of_their_organisations(): void
	{
		$own = $this->existingTest(null, ['reference' => 'PT-A']);
		$foreign = $this->existingTest($this->foreign, ['reference' => 'PT-B']);

		foreach ([TenantRole::ProjectManager, TenantRole::Tester, TenantRole::Client] as $role) {
			Sanctum::actingAs($this->member($role));

			$this->getJson('/api/v1/tests?page=1')
				->assertOk()
				->assertJsonCount(1, 'data')
				->assertJsonPath('data.0.reference', 'PT-A')
				->assertJsonPath('meta.total', 1);
			$this->getJson("/api/v1/tests/{$own->id}")
				->assertOk()
				->assertJsonPath('data.can_manage', $role === TenantRole::ProjectManager);
			$this->getJson("/api/v1/tests/{$foreign->id}")->assertNotFound();
			$this->getJson('/api/v1/tests/01JD0000000000000000000000')->assertNotFound();
		}
	}

	/**
	 * La liste se filtre par organisation, statut et type.
	 *
	 * @return void
	 */
	public function test_the_list_is_filtered_by_organisation_status_and_type(): void
	{
		$second = Tenant::factory()->create();
		$this->existingTest(null, ['reference' => 'PT-1', 'status' => 'planned', 'type' => 'red_team']);
		$this->existingTest(null, ['reference' => 'PT-2', 'status' => 'in_progress', 'type' => 'black_box']);
		$this->existingTest($second, ['reference' => 'PT-3', 'status' => 'in_progress', 'type' => 'red_team']);
		Sanctum::actingAs($this->admin());

		$this->getJson('/api/v1/tests?filter[status]=in_progress&sort=reference&order=asc')
			->assertOk()
			->assertJsonPath('data.*.reference', ['PT-2', 'PT-3']);
		$this->getJson('/api/v1/tests?filter[type]=red_team&sort=reference&order=asc')
			->assertJsonPath('data.*.reference', ['PT-1', 'PT-3']);
		$this->getJson("/api/v1/tests?filter[tenant_id]={$second->id}")
			->assertJsonPath('data.*.reference', ['PT-3']);
	}

	/**
	 * Le chef de projet modifie la fiche ; le statut ne change que par sa route
	 * dédiée, et l'organisation ne change jamais.
	 *
	 * @return void
	 */
	public function test_a_project_manager_updates_the_test(): void
	{
		$test = $this->existingTest(null, ['reference' => 'PT-A']);
		Sanctum::actingAs($this->member(TenantRole::ProjectManager));

		$this->patchJson("/api/v1/tests/{$test->id}", [
			'title' => 'Nouveau titre',
			'type' => 'white_box',
			'exposure' => 'external',
			'status' => 'validated',
			'tenant_id' => $this->foreign->id,
		])->assertOk()
			->assertJsonPath('data.title', 'Nouveau titre')
			->assertJsonPath('data.type', 'white_box')
			->assertJsonPath('data.exposure', 'external')
			->assertJsonPath('data.status', 'planned')
			->assertJsonPath('data.tenant.id', $this->client->id);
	}

	/**
	 * Testeurs et clients finaux ne modifient ni la fiche ni le statut.
	 *
	 * @return void
	 */
	public function test_testers_and_clients_have_read_only_access(): void
	{
		$test = $this->existingTest();

		foreach ([TenantRole::Tester, TenantRole::Client] as $role) {
			Sanctum::actingAs($this->member($role));

			$this->patchJson("/api/v1/tests/{$test->id}", ['title' => 'Piraté'])->assertForbidden();
			$this->postJson("/api/v1/tests/{$test->id}/status", ['status' => 'in_progress'])->assertForbidden();
			$this->deleteJson("/api/v1/tests/{$test->id}")->assertForbidden();
		}

		$this->assertSame('planned', PenetrationTest::withoutTenantRestriction(fn () => $test->fresh()?->status->value));
	}

	/**
	 * Les transitions sont libres entre les quatre statuts, et chacune est tracée
	 * avec son auteur, sa date et son commentaire.
	 *
	 * @return void
	 */
	public function test_status_transitions_are_free_and_audited(): void
	{
		$test = $this->existingTest();
		$manager = $this->member(TenantRole::ProjectManager);
		Sanctum::actingAs($manager);

		$this->travelTo('2026-12-01 08:30:00');
		$this->postJson("/api/v1/tests/{$test->id}/status", ['status' => 'in_progress'])
			->assertOk()
			->assertJsonPath('data.status', 'in_progress');

		$this->travelTo('2026-12-12 17:00:00');
		$this->postJson("/api/v1/tests/{$test->id}/status", ['status' => 'validated', 'comment' => 'Rapport accepté par le client.'])
			->assertOk();

		// Retour en arrière : autorisé, et tracé comme les autres.
		$this->travelTo('2026-12-15 09:00:00');
		$response = $this->postJson("/api/v1/tests/{$test->id}/status", ['status' => 'in_progress', 'comment' => 'Retest demandé.'])
			->assertOk()
			->assertJsonPath('data.status', 'in_progress');

		$changes = $response->json('data.status_changes');
		$this->assertSame(
			[['validated', 'in_progress'], ['in_progress', 'validated'], ['planned', 'in_progress']],
			array_map(fn (array $change) => [$change['from'], $change['to']], $changes),
			'Historique du plus récent au plus ancien.',
		);
		$this->assertSame('Retest demandé.', $changes[0]['comment']);
		$this->assertSame($manager->name, $changes[0]['author']);
		$this->assertSame('2026-12-12T17:00:00Z', $changes[1]['changed_at']);
		$this->assertNull($changes[2]['comment']);
	}

	/**
	 * Un « changement » vers le statut courant, ou vers un statut inconnu, est
	 * refusé et n'entre pas dans l'historique.
	 *
	 * @return void
	 */
	public function test_an_invalid_transition_is_refused(): void
	{
		$test = $this->existingTest();
		Sanctum::actingAs($this->member(TenantRole::ProjectManager));

		$this->postJson("/api/v1/tests/{$test->id}/status", ['status' => 'planned'])
			->assertUnprocessable()
			->assertJsonValidationErrors('status', 'details');
		$this->postJson("/api/v1/tests/{$test->id}/status", ['status' => 'archived'])
			->assertUnprocessable()
			->assertJsonValidationErrors('status', 'details');
		$this->postJson("/api/v1/tests/{$test->id}/status", ['status' => 'done', 'comment' => str_repeat('a', 2001)])
			->assertUnprocessable()
			->assertJsonValidationErrors('comment', 'details');

		$this->assertSame(0, $test->statusChanges()->count());
	}

	/**
	 * Le statut d'un test étranger ne change pas : 404, comme pour un test inconnu.
	 *
	 * @return void
	 */
	public function test_the_status_of_a_foreign_test_cannot_change(): void
	{
		$foreign = $this->existingTest($this->foreign);
		Sanctum::actingAs($this->member(TenantRole::ProjectManager));

		$this->postJson("/api/v1/tests/{$foreign->id}/status", ['status' => 'in_progress'])->assertNotFound();
		$this->patchJson("/api/v1/tests/{$foreign->id}", ['title' => 'Piraté'])->assertNotFound();
	}

	/**
	 * La suppression, définitive, est réservée à l'administrateur d'instance et
	 * emporte phases et historique.
	 *
	 * @return void
	 */
	public function test_only_an_administrator_deletes_a_test(): void
	{
		$test = $this->existingTest();
		Sanctum::actingAs($this->member(TenantRole::ProjectManager));
		$this->deleteJson("/api/v1/tests/{$test->id}")->assertForbidden();

		Sanctum::actingAs($this->admin());
		$this->postJson("/api/v1/tests/{$test->id}/status", ['status' => 'in_progress'])->assertOk();
		$this->deleteJson("/api/v1/tests/{$test->id}")->assertNoContent();

		$this->assertDatabaseMissing('tests', ['id' => $test->id]);
		$this->assertDatabaseMissing('phases', ['test_id' => $test->id]);
		$this->assertDatabaseMissing('test_status_changes', ['test_id' => $test->id]);
	}

	/**
	 * Supprimer l'organisation supprime ses tests.
	 *
	 * @return void
	 */
	public function test_deleting_the_organisation_deletes_its_tests(): void
	{
		$test = $this->existingTest();
		Sanctum::actingAs($this->admin());

		$this->deleteJson("/api/v1/tenants/{$this->client->id}")->assertNoContent();

		$this->assertDatabaseMissing('tests', ['id' => $test->id]);
	}

	/**
	 * Les routes des tests exigent une session.
	 *
	 * @return void
	 */
	public function test_tests_require_authentication(): void
	{
		$this->getJson('/api/v1/tests')->assertUnauthorized();
		$this->postJson('/api/v1/tests', $this->payload())->assertUnauthorized();
	}
}
