<?php

namespace Tests\Feature\Api;

use App\Http\Api\ApiListQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Pagination, tri et filtres des listes de l'API (11.03), éprouvés sur une
 * liste de test des utilisateurs : tri sur name ou created_at, filtre sur name.
 */
class PaginationTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * Déclare une route de liste de test, avec tri et filtres en liste blanche.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		Route::middleware('api')->get('api/v1/_essai/utilisateurs', function (Request $request) {
			return ApiListQuery::make(
				request: $request,
				sortable: ['name', 'created_at'],
				filterable: ['name'],
				defaultSort: 'created_at',
			)->respond(User::query(), fn (User $user) => ['id' => $user->id, 'name' => $user->name]);
		});
	}

	/**
	 * Par défaut, la liste est paginée par curseur, par tranches de 50.
	 *
	 * @return void
	 */
	public function test_par_defaut_la_liste_est_paginee_par_curseur_par_tranches_de_50(): void
	{
		User::factory()->count(60)->create();

		$first = $this->getJson('/api/v1/_essai/utilisateurs')->assertOk();

		$this->assertCount(50, $first->json('data'));
		$first->assertJsonPath('meta.limit', 50)
			->assertJsonPath('meta.count', 50)
			->assertJsonPath('meta.prev_cursor', null);
		$this->assertIsString($first->json('meta.next_cursor'));
		$this->assertArrayNotHasKey('total', $first->json('meta'), 'pas de total en mode curseur');

		$second = $this->getJson('/api/v1/_essai/utilisateurs?cursor='.$first->json('meta.next_cursor'))->assertOk();

		$this->assertCount(10, $second->json('data'));
		$second->assertJsonPath('meta.next_cursor', null);
		$ids = array_merge(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id'));
		$this->assertCount(60, array_unique($ids), 'aucun élément répété ni sauté');
	}

	/**
	 * Un curseur qui ne correspond pas au tri est refusé en 422.
	 *
	 * @return void
	 */
	public function test_un_curseur_qui_ne_correspond_pas_au_tri_est_refuse_en_422(): void
	{
		User::factory()->count(2)->create();
		$cursor = $this->getJson('/api/v1/_essai/utilisateurs?sort=name&limit=1')->json('meta.next_cursor');

		$this->getJson("/api/v1/_essai/utilisateurs?sort=created_at&limit=1&cursor={$cursor}")
			->assertStatus(422)
			->assertJsonPath('code', 'validation_failed')
			->assertJsonPath('details.cursor.0', __('validation.custom.cursor.invalid'));
	}

	/**
	 * Le mode page donne le total et la dernière page.
	 *
	 * @return void
	 */
	public function test_le_mode_page_donne_le_total_et_la_derniere_page(): void
	{
		User::factory()->count(45)->create();

		$this->getJson('/api/v1/_essai/utilisateurs?page=2&limit=20')
			->assertOk()
			->assertJsonCount(20, 'data')
			->assertJsonPath('meta.page', 2)
			->assertJsonPath('meta.last_page', 3)
			->assertJsonPath('meta.total', 45)
			->assertJsonPath('meta.limit', 20);
	}

	/**
	 * Le mode de pagination par défaut se règle pour l'instance.
	 *
	 * @return void
	 */
	public function test_le_mode_par_defaut_se_regle_pour_l_instance(): void
	{
		config(['api.pagination' => 'page']);
		User::factory()->count(3)->create();

		$this->getJson('/api/v1/_essai/utilisateurs')->assertJsonPath('meta.total', 3);
	}

	/**
	 * Le paramètre limit est borné à 200.
	 *
	 * @return void
	 */
	public function test_limit_est_borne_a_200(): void
	{
		$this->getJson('/api/v1/_essai/utilisateurs?limit=201')
			->assertStatus(422)
			->assertJsonPath('code', 'validation_failed')
			->assertJsonPath('details.limit.0', 'Le champ limit ne peut pas être supérieur à 200.');

		$this->getJson('/api/v1/_essai/utilisateurs?limit=0')->assertStatus(422);
		$this->getJson('/api/v1/_essai/utilisateurs?limit=200')->assertOk();
	}

	/**
	 * Le tri est limité à la liste blanche.
	 *
	 * @return void
	 */
	public function test_le_tri_est_limite_a_la_liste_blanche(): void
	{
		User::factory()->create(['name' => 'Bruno']);
		User::factory()->create(['name' => 'Alice']);

		$this->getJson('/api/v1/_essai/utilisateurs?sort=name&order=asc')
			->assertOk()
			->assertJsonPath('data.0.name', 'Alice');

		$this->getJson('/api/v1/_essai/utilisateurs?sort=password')
			->assertStatus(422)
			->assertJsonStructure(['details' => ['sort']]);

		$this->getJson('/api/v1/_essai/utilisateurs?sort=name&order=au-hasard')
			->assertStatus(422)
			->assertJsonStructure(['details' => ['order']]);
	}

	/**
	 * Les filtres sont limités à la liste blanche.
	 *
	 * @return void
	 */
	public function test_les_filtres_sont_limites_a_la_liste_blanche(): void
	{
		User::factory()->create(['name' => 'Alice']);
		User::factory()->create(['name' => 'Bruno']);

		$this->getJson('/api/v1/_essai/utilisateurs?filter[name]=Alice')
			->assertOk()
			->assertJsonCount(1, 'data')
			->assertJsonPath('data.0.name', 'Alice');

		$this->getJson('/api/v1/_essai/utilisateurs?filter[password]=x')
			->assertStatus(422)
			->assertJsonPath('code', 'validation_failed')
			->assertJsonStructure(['details' => ['filter']]);
	}
}
