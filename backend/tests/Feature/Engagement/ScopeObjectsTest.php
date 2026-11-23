<?php

namespace Tests\Feature\Engagement;

use App\Enums\TenantRole;
use App\Models\PenetrationTest;
use App\Models\ScopeObject;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Périmètre réseau d'un test (engagement-4, 11.07, 14.01) : machines, services et
 * comptes de test, inclus ou exclus avec commentaire et contraintes horaires ;
 * écriture réservée à l'équipe (testeur, chef de projet, administrateur), lecture
 * pour tous les membres de l'organisation, aucun secret stocké en clair.
 */
class ScopeObjectsTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * Organisation cliente du test.
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
	 * Test d'intrusion de l'organisation cliente.
	 *
	 * @var PenetrationTest
	 */
	private PenetrationTest $test;

	/**
	 * Crée deux organisations et un test dans la première.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->client = Tenant::factory()->create(['name' => 'Alpha']);
		$this->foreign = Tenant::factory()->create(['name' => 'Bêta']);
		$this->test = $this->existingTest($this->client);
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
	 * Crée un test dans une organisation, sans passer par l'API.
	 *
	 * @param Tenant $tenant Organisation du test.
	 * @return PenetrationTest
	 */
	private function existingTest(Tenant $tenant): PenetrationTest
	{
		return PenetrationTest::withoutTenantRestriction(fn () => PenetrationTest::factory()->create(['tenant_id' => $tenant->id]));
	}

	/**
	 * Déclare une machine par l'API et renvoie son identifiant.
	 *
	 * @param PenetrationTest $test Test visé.
	 * @param array<string, mixed> $overrides Champs à remplacer.
	 * @return string
	 */
	private function createHost(PenetrationTest $test, array $overrides = []): string
	{
		$response = $this->postJson("/api/v1/tests/{$test->id}/scope-objects", array_merge([
			'type' => 'host',
			'address' => '203.0.113.10',
			'hostname' => 'srv-web01',
		], $overrides))->assertCreated();

		return $response->json('data.id');
	}

	/**
	 * Un testeur déclare une machine : adresse, nom, système, inclusion,
	 * commentaire et contraintes ; le libellé reprend le nom de la machine.
	 *
	 * @return void
	 */
	public function test_a_tester_declares_a_host(): void
	{
		Sanctum::actingAs($this->member(TenantRole::Tester));

		$this->postJson("/api/v1/tests/{$this->test->id}/scope-objects", [
			'type' => 'host',
			'address' => '203.0.113.10',
			'hostname' => 'srv-web01',
			'operating_system' => 'Debian 12',
			'comment' => 'Serveur du site vitrine',
			'constraints' => 'Pas de test de 12 h à 14 h',
		])
			->assertCreated()
			->assertJsonPath('data.type', 'host')
			->assertJsonPath('data.label', 'srv-web01')
			->assertJsonPath('data.included', true)
			->assertJsonPath('data.comment', 'Serveur du site vitrine')
			->assertJsonPath('data.constraints', 'Pas de test de 12 h à 14 h')
			->assertJsonPath('data.host.address', '203.0.113.10')
			->assertJsonPath('data.host.hostname', 'srv-web01')
			->assertJsonPath('data.host.operating_system', 'Debian 12')
			->assertJsonPath('data.parent_id', null);

		$this->assertDatabaseHas('scope_objects', ['test_id' => $this->test->id, 'tenant_id' => $this->client->id, 'type' => 'host']);
	}

	/**
	 * Une machine se déclare par une adresse IPv4, IPv6 ou une plage CIDR ; une
	 * adresse invalide est refusée, et le libellé reprend l'adresse sans nom.
	 *
	 * @return void
	 */
	public function test_host_addresses_accept_ip_and_cidr_only(): void
	{
		Sanctum::actingAs($this->member(TenantRole::Tester));
		$url = "/api/v1/tests/{$this->test->id}/scope-objects";

		$this->postJson($url, ['type' => 'host', 'address' => '203.0.113.0/26'])
			->assertCreated()
			->assertJsonPath('data.label', '203.0.113.0/26');
		$this->postJson($url, ['type' => 'host', 'address' => '2001:db8::1'])->assertCreated();
		$this->postJson($url, ['type' => 'host', 'address' => '2001:db8::/48'])->assertCreated();

		foreach (['203.0.113.300', '203.0.113.0/33', 'srv-web01', '2001:db8::/129'] as $address) {
			$this->postJson($url, ['type' => 'host', 'address' => $address])->assertUnprocessable()->assertJsonValidationErrors('address', 'details');
		}
		$this->postJson($url, ['type' => 'host'])->assertUnprocessable()->assertJsonValidationErrors('address', 'details');
	}

	/**
	 * Un service appartient à une machine du même test : protocole, port, nom et
	 * version ; un même port ne se déclare pas deux fois sur une machine.
	 *
	 * @return void
	 */
	public function test_a_service_belongs_to_a_host_of_the_same_test(): void
	{
		Sanctum::actingAs($this->member(TenantRole::Tester));
		$host = $this->createHost($this->test);
		$url = "/api/v1/tests/{$this->test->id}/scope-objects";

		$this->postJson($url, ['type' => 'service', 'parent_id' => $host, 'protocol' => 'tcp', 'port' => 443, 'name' => 'https', 'version' => 'nginx 1.26'])
			->assertCreated()
			->assertJsonPath('data.parent_id', $host)
			->assertJsonPath('data.label', '443/tcp https')
			->assertJsonPath('data.service.port', 443)
			->assertJsonPath('data.service.protocol', 'tcp')
			->assertJsonPath('data.service.version', 'nginx 1.26');

		// Même port et même protocole sur la même machine : refusé ; en UDP : accepté.
		$this->postJson($url, ['type' => 'service', 'parent_id' => $host, 'protocol' => 'tcp', 'port' => 443])
			->assertUnprocessable()->assertJsonValidationErrors('port', 'details');
		$this->postJson($url, ['type' => 'service', 'parent_id' => $host, 'protocol' => 'udp', 'port' => 443])->assertCreated();

		// Port hors bornes, protocole inconnu, parent absent.
		$this->postJson($url, ['type' => 'service', 'parent_id' => $host, 'protocol' => 'tcp', 'port' => 70000])
			->assertUnprocessable()->assertJsonValidationErrors('port', 'details');
		$this->postJson($url, ['type' => 'service', 'parent_id' => $host, 'protocol' => 'sctp', 'port' => 22])
			->assertUnprocessable()->assertJsonValidationErrors('protocol', 'details');
		$this->postJson($url, ['type' => 'service', 'protocol' => 'tcp', 'port' => 22])
			->assertUnprocessable()->assertJsonValidationErrors('parent_id', 'details');
	}

	/**
	 * Le parent d'un service est une machine du même test : un service, une
	 * machine d'un autre test ou d'une autre organisation sont refusés.
	 *
	 * @return void
	 */
	public function test_a_service_parent_must_be_a_host_of_the_same_test(): void
	{
		Sanctum::actingAs($this->member(TenantRole::Tester));
		$host = $this->createHost($this->test);
		$url = "/api/v1/tests/{$this->test->id}/scope-objects";
		$service = $this->postJson($url, ['type' => 'service', 'parent_id' => $host, 'protocol' => 'tcp', 'port' => 22])->json('data.id');

		$otherHost = $this->createHost($this->existingTest($this->client));

		$this->postJson($url, ['type' => 'service', 'parent_id' => $service, 'protocol' => 'tcp', 'port' => 80])
			->assertUnprocessable()->assertJsonValidationErrors('parent_id', 'details');
		$this->postJson($url, ['type' => 'service', 'parent_id' => $otherHost, 'protocol' => 'tcp', 'port' => 80])
			->assertUnprocessable()->assertJsonValidationErrors('parent_id', 'details');

		$foreignHost = ScopeObject::withoutTenantRestriction(
			fn () => ScopeObject::declare($this->existingTest($this->foreign), ['type' => 'host', 'address' => '198.51.100.1'])->id,
		);
		$this->postJson($url, ['type' => 'service', 'parent_id' => $foreignHost, 'protocol' => 'tcp', 'port' => 80])
			->assertUnprocessable()->assertJsonValidationErrors('parent_id', 'details');
	}

	/**
	 * Un compte de test porte un identifiant et un rôle, rattachable à une
	 * machine ; aucun secret n'est accepté ni stocké (14.01 : il ira dans la
	 * GED chiffrée).
	 *
	 * @return void
	 */
	public function test_an_account_never_stores_a_secret(): void
	{
		Sanctum::actingAs($this->member(TenantRole::Tester));
		$host = $this->createHost($this->test);

		$response = $this->postJson("/api/v1/tests/{$this->test->id}/scope-objects", [
			'type' => 'account',
			'parent_id' => $host,
			'username' => 'client.test1',
			'role_description' => 'Client particulier',
			'password' => 'Secret-en-clair-2026',
		])
			->assertCreated()
			->assertJsonPath('data.label', 'client.test1')
			->assertJsonPath('data.account.username', 'client.test1')
			->assertJsonPath('data.account.role_description', 'Client particulier');

		$this->assertStringNotContainsString('Secret-en-clair-2026', (string) $response->getContent());
		$this->assertFalse(Schema::hasColumn('scope_accounts', 'password'));
		foreach (['scope_objects', 'scope_accounts', 'scope_hosts'] as $table) {
			$this->assertStringNotContainsString('Secret-en-clair-2026', json_encode(DB::table($table)->get(), JSON_THROW_ON_ERROR));
		}

		$this->postJson("/api/v1/tests/{$this->test->id}/scope-objects", ['type' => 'account'])
			->assertUnprocessable()->assertJsonValidationErrors('username', 'details');
	}

	/**
	 * Une exclusion se déclare avec son commentaire et se modifie ; le type d'un
	 * objet ne change pas.
	 *
	 * @return void
	 */
	public function test_an_object_is_excluded_with_a_comment_and_constraints(): void
	{
		Sanctum::actingAs($this->member(TenantRole::ProjectManager));
		$host = $this->createHost($this->test, ['address' => '203.0.113.30', 'hostname' => 'srv-paiement']);

		$this->patchJson("/api/v1/scope-objects/{$host}", [
			'included' => false,
			'comment' => 'Tiers (prestataire de paiement) : hors contrat',
			'constraints' => null,
			'operating_system' => 'Windows Server 2022',
		])
			->assertOk()
			->assertJsonPath('data.included', false)
			->assertJsonPath('data.comment', 'Tiers (prestataire de paiement) : hors contrat')
			->assertJsonPath('data.host.address', '203.0.113.30')
			->assertJsonPath('data.host.operating_system', 'Windows Server 2022');

		$this->patchJson("/api/v1/scope-objects/{$host}", ['type' => 'account'])
			->assertOk()
			->assertJsonPath('data.type', 'host');
		$this->patchJson("/api/v1/scope-objects/{$host}", ['address' => 'pas-une-adresse'])
			->assertUnprocessable()->assertJsonValidationErrors('address', 'details');
	}

	/**
	 * La liste du périmètre ne montre que les objets du test, filtrables par type
	 * et par inclusion ; la fiche d'un objet se lit seule.
	 *
	 * @return void
	 */
	public function test_the_scope_lists_the_objects_of_the_test(): void
	{
		Sanctum::actingAs($this->member(TenantRole::Tester));
		$host = $this->createHost($this->test);
		$this->createHost($this->test, ['address' => '203.0.113.30', 'included' => false]);
		$this->postJson("/api/v1/tests/{$this->test->id}/scope-objects", ['type' => 'service', 'parent_id' => $host, 'protocol' => 'tcp', 'port' => 22]);
		$this->createHost($this->existingTest($this->client), ['address' => '192.0.2.1']);

		$this->getJson("/api/v1/tests/{$this->test->id}/scope-objects?limit=100&page=1")
			->assertOk()
			->assertJsonCount(3, 'data')
			->assertJsonPath('meta.total', 3);
		$this->getJson("/api/v1/tests/{$this->test->id}/scope-objects?filter[type]=host")->assertJsonCount(2, 'data');
		$this->getJson("/api/v1/tests/{$this->test->id}/scope-objects?filter[included]=0")->assertJsonCount(1, 'data');

		$this->getJson("/api/v1/scope-objects/{$host}")
			->assertOk()
			->assertJsonPath('data.host.hostname', 'srv-web01')
			->assertJsonPath('data.can_manage', true);
	}

	/**
	 * Supprimer une machine supprime ses services ; un compte qui y était
	 * rattaché reste, détaché.
	 *
	 * @return void
	 */
	public function test_deleting_a_host_removes_its_services(): void
	{
		Sanctum::actingAs($this->member(TenantRole::Tester));
		$host = $this->createHost($this->test);
		$url = "/api/v1/tests/{$this->test->id}/scope-objects";
		$service = $this->postJson($url, ['type' => 'service', 'parent_id' => $host, 'protocol' => 'tcp', 'port' => 22])->json('data.id');
		$account = $this->postJson($url, ['type' => 'account', 'parent_id' => $host, 'username' => 'admin'])->json('data.id');

		$this->deleteJson("/api/v1/scope-objects/{$host}")->assertNoContent();

		$this->assertDatabaseMissing('scope_objects', ['id' => $service]);
		$this->assertDatabaseMissing('scope_services', ['scope_object_id' => $service]);
		$this->assertDatabaseHas('scope_objects', ['id' => $account, 'parent_id' => null]);
	}

	/**
	 * Le client final lit le périmètre de son organisation sans pouvoir le
	 * modifier.
	 *
	 * @return void
	 */
	public function test_a_client_reads_but_cannot_write_the_scope(): void
	{
		Sanctum::actingAs($this->member(TenantRole::Tester));
		$host = $this->createHost($this->test);

		Sanctum::actingAs($this->member(TenantRole::Client));
		$this->getJson("/api/v1/tests/{$this->test->id}/scope-objects")->assertOk()->assertJsonCount(1, 'data');
		$this->getJson("/api/v1/scope-objects/{$host}")->assertOk()->assertJsonPath('data.can_manage', false);
		$this->postJson("/api/v1/tests/{$this->test->id}/scope-objects", ['type' => 'host', 'address' => '203.0.113.11'])->assertForbidden();
		$this->patchJson("/api/v1/scope-objects/{$host}", ['included' => false])->assertForbidden();
		$this->deleteJson("/api/v1/scope-objects/{$host}")->assertForbidden();
	}

	/**
	 * Le périmètre d'une organisation étrangère est introuvable, en lecture comme
	 * en écriture.
	 *
	 * @return void
	 */
	public function test_the_scope_of_a_foreign_organisation_is_not_found(): void
	{
		$foreignTest = $this->existingTest($this->foreign);
		$foreignHost = ScopeObject::withoutTenantRestriction(
			fn () => ScopeObject::declare($foreignTest, ['type' => 'host', 'address' => '198.51.100.1'])->id,
		);

		Sanctum::actingAs($this->member(TenantRole::ProjectManager));
		$this->getJson("/api/v1/tests/{$foreignTest->id}/scope-objects")->assertNotFound();
		$this->postJson("/api/v1/tests/{$foreignTest->id}/scope-objects", ['type' => 'host', 'address' => '198.51.100.2'])->assertNotFound();
		$this->getJson("/api/v1/scope-objects/{$foreignHost}")->assertNotFound();
		$this->patchJson("/api/v1/scope-objects/{$foreignHost}", ['included' => false])->assertNotFound();
		$this->deleteJson("/api/v1/scope-objects/{$foreignHost}")->assertNotFound();
	}

	/**
	 * Déplacer un service vers une machine qui a déjà son couple protocole/port,
	 * ou changer seulement son protocole vers un couple pris, est refusé.
	 *
	 * @return void
	 */
	public function test_moving_a_service_keeps_ports_unique(): void
	{
		Sanctum::actingAs($this->member(TenantRole::Tester));
		$url = "/api/v1/tests/{$this->test->id}/scope-objects";
		$first = $this->createHost($this->test);
		$second = $this->createHost($this->test, ['address' => '203.0.113.11', 'hostname' => 'srv-api01']);
		$this->postJson($url, ['type' => 'service', 'parent_id' => $second, 'protocol' => 'tcp', 'port' => 443])->assertCreated();
		$this->postJson($url, ['type' => 'service', 'parent_id' => $first, 'protocol' => 'udp', 'port' => 443])->assertCreated();
		$service = $this->postJson($url, ['type' => 'service', 'parent_id' => $first, 'protocol' => 'tcp', 'port' => 443])->json('data.id');

		$this->patchJson("/api/v1/scope-objects/{$service}", ['parent_id' => $second])
			->assertUnprocessable()->assertJsonValidationErrors('port', 'details');
		$this->patchJson("/api/v1/scope-objects/{$service}", ['protocol' => 'udp'])
			->assertUnprocessable()->assertJsonValidationErrors('port', 'details');

		// Son propre couple ne le bloque pas, et un port libre passe.
		$this->patchJson("/api/v1/scope-objects/{$service}", ['port' => 443, 'version' => 'nginx 1.26'])->assertOk();
		$this->patchJson("/api/v1/scope-objects/{$service}", ['parent_id' => $second, 'port' => 8443])
			->assertOk()
			->assertJsonPath('data.parent_id', $second)
			->assertJsonPath('data.label', '8443/tcp');
	}

	/**
	 * Un libellé déduit suit le détail ; un libellé saisi reste, et un libellé
	 * vidé redevient déduit.
	 *
	 * @return void
	 */
	public function test_a_derived_label_follows_the_detail(): void
	{
		Sanctum::actingAs($this->member(TenantRole::Tester));
		$host = $this->createHost($this->test);

		$this->patchJson("/api/v1/scope-objects/{$host}", ['hostname' => 'srv-web02'])->assertJsonPath('data.label', 'srv-web02');
		$this->patchJson("/api/v1/scope-objects/{$host}", ['label' => 'Site vitrine'])->assertJsonPath('data.label', 'Site vitrine');
		$this->patchJson("/api/v1/scope-objects/{$host}", ['hostname' => 'srv-web03'])->assertJsonPath('data.label', 'Site vitrine');
		$this->patchJson("/api/v1/scope-objects/{$host}", ['label' => ''])->assertJsonPath('data.label', 'srv-web03');
	}

	/**
	 * L'administrateur d'instance tient le périmètre de toute organisation.
	 *
	 * @return void
	 */
	public function test_an_instance_administrator_manages_any_scope(): void
	{
		$admin = User::factory()->create();
		$admin->forceFill(['is_instance_admin' => true])->save();
		Sanctum::actingAs($admin);

		$host = $this->createHost($this->test);
		$this->getJson("/api/v1/scope-objects/{$host}")->assertOk()->assertJsonPath('data.can_manage', true);
		$this->getJson("/api/v1/tests/{$this->test->id}")->assertOk()->assertJsonPath('data.can_manage_scope', true);
		$this->deleteJson("/api/v1/scope-objects/{$host}")->assertNoContent();
	}

	/**
	 * Le filtre d'inclusion n'accepte que 0 ou 1.
	 *
	 * @return void
	 */
	public function test_the_inclusion_filter_accepts_booleans_only(): void
	{
		Sanctum::actingAs($this->member(TenantRole::Tester));

		$this->getJson("/api/v1/tests/{$this->test->id}/scope-objects?filter[included]=abc")
			->assertUnprocessable()->assertJsonValidationErrors('filter.included', 'details');
		$this->getJson("/api/v1/tests/{$this->test->id}/scope-objects?filter[included]=1")->assertOk();
	}

	/**
	 * Le périmètre exige une session.
	 *
	 * @return void
	 */
	public function test_the_scope_requires_authentication(): void
	{
		$this->getJson("/api/v1/tests/{$this->test->id}/scope-objects")->assertUnauthorized();
	}
}
