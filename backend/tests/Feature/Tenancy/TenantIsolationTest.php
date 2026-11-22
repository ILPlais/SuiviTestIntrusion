<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantMismatch;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\Models\TenantProbe;
use Tests\TestCase;

/**
 * Rôles et isolation multi-tenant (identite-5, 11.06, 14.01) : chaque requête est
 * filtrée par les organisations de l'utilisateur ; un objet hors de ses
 * organisations renvoie 404, comme un identifiant inconnu.
 */
class TenantIsolationTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * Organisation A.
	 *
	 * @var Tenant
	 */
	private Tenant $alpha;

	/**
	 * Organisation B.
	 *
	 * @var Tenant
	 */
	private Tenant $beta;

	/**
	 * Crée deux organisations et la table de l'objet métier d'essai.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->alpha = Tenant::factory()->create(['name' => 'Alpha']);
		$this->beta = Tenant::factory()->create(['name' => 'Bêta']);

		Schema::create('tenant_probes', function (Blueprint $table) {
			$table->ulid('id')->primary();
			$table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
			$table->string('label');
			$table->timestamps();
		});
	}

	/**
	 * Crée un utilisateur rattaché à une organisation avec un rôle.
	 *
	 * @param Tenant $tenant Organisation de rattachement.
	 * @param TenantRole $role Rôle dans cette organisation.
	 * @return User
	 */
	private function member(Tenant $tenant, TenantRole $role): User
	{
		$user = User::factory()->create();
		$user->tenants()->attach($tenant->id, ['role' => $role->value]);

		return $user;
	}

	/**
	 * Crée un objet métier d'essai dans une organisation, sans filtre.
	 *
	 * @param Tenant $tenant Organisation de l'objet.
	 * @param string $label Libellé de l'objet.
	 * @return TenantProbe
	 */
	private function probe(Tenant $tenant, string $label): TenantProbe
	{
		return TenantProbe::withoutTenantRestriction(fn () => TenantProbe::create(['tenant_id' => $tenant->id, 'label' => $label]));
	}

	/**
	 * Les rôles par organisation sont ceux de 11.06 ; root et administrateur sont
	 * des administrateurs d'instance.
	 *
	 * @return void
	 */
	public function test_the_roles_follow_the_specification(): void
	{
		$this->assertSame(['project_manager', 'tester', 'client'], array_map(fn (TenantRole $role) => $role->value, TenantRole::cases()));

		$tester = $this->member($this->alpha, TenantRole::Tester);
		$this->assertSame(TenantRole::Tester, $tester->roleIn($this->alpha));
		$this->assertNull($tester->roleIn($this->beta));
	}

	/**
	 * Un membre ne liste que ses organisations, avec son rôle.
	 *
	 * @return void
	 */
	public function test_a_member_lists_only_their_organisations(): void
	{
		Sanctum::actingAs($this->member($this->alpha, TenantRole::ProjectManager));

		$this->getJson('/api/v1/tenants')
			->assertOk()
			->assertJsonCount(1, 'data')
			->assertJsonPath('data.0.id', $this->alpha->id)
			->assertJsonPath('data.0.name', 'Alpha')
			->assertJsonPath('data.0.role', 'project_manager');
	}

	/**
	 * L'administrateur d'instance voit toutes les organisations.
	 *
	 * @return void
	 */
	public function test_the_instance_administrator_sees_every_organisation(): void
	{
		$admin = User::factory()->create();
		$admin->forceFill(['is_instance_admin' => true])->save();
		Sanctum::actingAs($admin);

		$this->getJson('/api/v1/tenants')->assertOk()->assertJsonCount(2, 'data');
		$this->getJson('/api/v1/tenants/'.$this->beta->id)->assertOk()->assertJsonPath('data.role', null);
	}

	/**
	 * Une organisation étrangère renvoie 404, comme un identifiant inconnu.
	 *
	 * @return void
	 */
	public function test_a_foreign_organisation_answers_404_like_an_unknown_one(): void
	{
		Sanctum::actingAs($this->member($this->alpha, TenantRole::Client));

		$this->getJson('/api/v1/tenants/'.$this->alpha->id)->assertOk()->assertJsonPath('data.role', 'client');
		$foreign = $this->getJson('/api/v1/tenants/'.$this->beta->id)->assertNotFound();
		$unknown = $this->getJson('/api/v1/tenants/01JZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();

		$this->assertSame($unknown->json(), $foreign->json());
	}

	/**
	 * Les routes des organisations exigent une session.
	 *
	 * @return void
	 */
	public function test_organisations_require_authentication(): void
	{
		$this->getJson('/api/v1/tenants')->assertUnauthorized();
	}

	/**
	 * Un objet métier n'est lu que dans les organisations de l'utilisateur.
	 *
	 * @return void
	 */
	public function test_business_objects_are_filtered_by_organisation(): void
	{
		$own = $this->probe($this->alpha, 'à moi');
		$foreign = $this->probe($this->beta, 'à eux');
		Auth::setUser($this->member($this->alpha, TenantRole::Tester));

		$this->assertSame(['à moi'], TenantProbe::query()->pluck('label')->all());
		$this->assertNotNull(TenantProbe::find($own->id));
		$this->assertNull(TenantProbe::find($foreign->id));
		$this->assertSame(1, TenantProbe::query()->count());
	}

	/**
	 * Sans utilisateur authentifié, aucun objet métier n'est visible : le filtre
	 * échoue fermé.
	 *
	 * @return void
	 */
	public function test_without_a_user_nothing_is_visible(): void
	{
		$this->probe($this->alpha, 'à moi');

		$this->assertSame(0, TenantProbe::query()->count());
		$this->assertSame(1, TenantProbe::withoutTenantRestriction(fn () => TenantProbe::query()->count()));
	}

	/**
	 * Un objet métier ne peut être créé ni déplacé hors des organisations de
	 * l'utilisateur.
	 *
	 * @return void
	 */
	public function test_writing_outside_ones_organisations_is_refused(): void
	{
		Auth::setUser($this->member($this->alpha, TenantRole::ProjectManager));

		$own = TenantProbe::create(['tenant_id' => $this->alpha->id, 'label' => 'à moi']);
		$this->assertSame($this->alpha->id, $own->tenant_id);

		try {
			TenantProbe::create(['tenant_id' => $this->beta->id, 'label' => 'intrus']);
			$this->fail('La création hors organisation aurait dû être refusée.');
		} catch (TenantMismatch) {
			// Refus attendu.
		}

		$this->expectException(TenantMismatch::class);
		$own->update(['tenant_id' => $this->beta->id]);
	}

	/**
	 * Le rôle d'un membre est unique par organisation.
	 *
	 * @return void
	 */
	public function test_a_member_has_one_role_per_organisation(): void
	{
		$user = $this->member($this->alpha, TenantRole::Tester);

		$this->expectException(UniqueConstraintViolationException::class);
		$user->tenants()->attach($this->alpha->id, ['role' => TenantRole::ProjectManager->value]);
	}
}
