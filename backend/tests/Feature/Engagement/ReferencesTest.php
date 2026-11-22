<?php

namespace Tests\Feature\Engagement;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Références d'un test (engagement-1, 11.05) : organisations clientes, entreprise
 * prestataire et profil du testeur ; présentations en Markdown.
 */
class ReferencesTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * Organisation cliente de test.
	 *
	 * @var Tenant
	 */
	private Tenant $client;

	/**
	 * Crée une organisation cliente.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->client = Tenant::factory()->create(['name' => 'Alpha']);
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
	 * Crée un membre de l'organisation de test avec un rôle.
	 *
	 * @param TenantRole $role Rôle dans l'organisation.
	 * @return User
	 */
	private function member(TenantRole $role): User
	{
		$user = User::factory()->create();
		$user->tenants()->attach($this->client->id, ['role' => $role->value]);

		return $user;
	}

	/**
	 * Un administrateur crée une organisation, présentation en Markdown comprise.
	 *
	 * @return void
	 */
	public function test_an_administrator_creates_an_organisation(): void
	{
		Sanctum::actingAs($this->admin());

		$response = $this->postJson('/api/v1/tenants', [
			'name' => 'Bêta',
			'legal_name' => 'Bêta SAS',
			'presentation' => "## Activité\n\nBanque **régionale**.",
		])->assertCreated()
			->assertJsonPath('data.name', 'Bêta')
			->assertJsonPath('data.presentation', "## Activité\n\nBanque **régionale**.");

		$this->assertDatabaseHas('tenants', ['id' => $response->json('data.id'), 'legal_name' => 'Bêta SAS']);
	}

	/**
	 * Seul un administrateur crée ou supprime une organisation.
	 *
	 * @return void
	 */
	public function test_only_an_administrator_creates_or_deletes_an_organisation(): void
	{
		Sanctum::actingAs($this->member(TenantRole::ProjectManager));

		$this->postJson('/api/v1/tenants', ['name' => 'Bêta'])->assertForbidden();
		$this->deleteJson('/api/v1/tenants/'.$this->client->id)->assertForbidden();
	}

	/**
	 * Le chef de projet modifie la fiche de son organisation ; testeur et client
	 * n'y ont qu'un accès en lecture.
	 *
	 * @return void
	 */
	public function test_the_project_manager_updates_their_organisation(): void
	{
		Sanctum::actingAs($this->member(TenantRole::ProjectManager));
		$this->patchJson('/api/v1/tenants/'.$this->client->id, ['presentation' => 'Nouvelle présentation'])
			->assertOk()
			->assertJsonPath('data.presentation', 'Nouvelle présentation')
			->assertJsonPath('data.name', 'Alpha');

		foreach ([TenantRole::Tester, TenantRole::Client] as $role) {
			Sanctum::actingAs($this->member($role));
			$this->patchJson('/api/v1/tenants/'.$this->client->id, ['name' => 'Piraté'])->assertForbidden();
		}

		$this->assertDatabaseHas('tenants', ['id' => $this->client->id, 'name' => 'Alpha']);
	}

	/**
	 * Une organisation étrangère reste introuvable, même en écriture.
	 *
	 * @return void
	 */
	public function test_a_foreign_organisation_cannot_be_updated(): void
	{
		$foreign = Tenant::factory()->create();
		Sanctum::actingAs($this->member(TenantRole::ProjectManager));

		$this->patchJson('/api/v1/tenants/'.$foreign->id, ['name' => 'Piraté'])->assertNotFound();
		$this->deleteJson('/api/v1/tenants/'.$foreign->id)->assertNotFound();
		$this->assertDatabaseHas('tenants', ['id' => $foreign->id, 'name' => $foreign->name]);
	}

	/**
	 * Un administrateur supprime une organisation et ses rattachements.
	 *
	 * @return void
	 */
	public function test_an_administrator_deletes_an_organisation(): void
	{
		$this->member(TenantRole::Tester);
		Sanctum::actingAs($this->admin());

		$this->deleteJson('/api/v1/tenants/'.$this->client->id)->assertNoContent();

		$this->assertDatabaseMissing('tenants', ['id' => $this->client->id]);
		$this->assertDatabaseMissing('tenant_user', ['tenant_id' => $this->client->id]);
	}

	/**
	 * Les champs sont validés : nom obligatoire, longueurs bornées.
	 *
	 * @return void
	 */
	public function test_organisation_fields_are_validated(): void
	{
		Sanctum::actingAs($this->admin());

		$this->postJson('/api/v1/tenants', ['name' => ''])->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
		$this->postJson('/api/v1/tenants', ['name' => str_repeat('a', 256)])->assertUnprocessable();
		$this->postJson('/api/v1/tenants', ['name' => 'Bêta', 'presentation' => str_repeat('a', 20001)])->assertUnprocessable();
	}

	/**
	 * Tout utilisateur lit la fiche de l'entreprise prestataire ; seul un
	 * administrateur la modifie.
	 *
	 * @return void
	 */
	public function test_the_company_profile_is_read_by_all_and_written_by_administrators(): void
	{
		Sanctum::actingAs($this->member(TenantRole::Client));
		$this->getJson('/api/v1/company-profile')->assertOk()->assertJsonPath('data.name', null);
		$this->putJson('/api/v1/company-profile', ['name' => 'Audit'])->assertForbidden();

		Sanctum::actingAs($this->admin());
		$this->putJson('/api/v1/company-profile', ['name' => 'Audit', 'legal_name' => 'Audit SARL', 'presentation' => '*Pentest*'])
			->assertOk()
			->assertJsonPath('data.legal_name', 'Audit SARL');
		$this->putJson('/api/v1/company-profile', ['name' => 'Audit & Co'])->assertOk();

		$this->assertDatabaseCount('company_profile', 1);
		$this->getJson('/api/v1/company-profile')->assertJsonPath('data.name', 'Audit & Co')->assertJsonPath('data.legal_name', null);
	}

	/**
	 * Chacun complète son profil de testeur : nom, poste, présentation ; les
	 * autres champs sont ignorés.
	 *
	 * @return void
	 */
	public function test_a_user_updates_their_own_tester_profile(): void
	{
		$user = $this->member(TenantRole::Tester);
		Sanctum::actingAs($user);

		$this->patchJson('/api/v1/user/profile', [
			'name' => 'Alice Martin',
			'job_title' => 'Auditrice senior',
			'presentation' => 'OSCP, 8 ans d\'expérience.',
			'is_instance_admin' => true,
			'email' => 'autre@example.org',
		])->assertOk()
			->assertJsonPath('job_title', 'Auditrice senior')
			->assertJsonPath('presentation', 'OSCP, 8 ans d\'expérience.');

		$user->refresh();
		$this->assertSame('Alice Martin', $user->name);
		$this->assertFalse((bool) $user->is_instance_admin);
		$this->assertNotSame('autre@example.org', $user->email);
	}
}
