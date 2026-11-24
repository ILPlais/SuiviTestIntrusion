<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\BuildsVaults;
use Tests\TestCase;

/**
 * Premier administrateur et activation d'un compte (identite-1).
 *
 * Aucun e-mail n'est envoyé en V1 (13.06) : la commande d'installation crée le
 * compte et affiche un lien d'activation à usage unique, valable 24 heures. La
 * SPA y calcule `auth` à partir du mot de passe choisi, qui ne quitte jamais le
 * navigateur (13.03).
 */
class ActivationTest extends TestCase
{
	use BuildsVaults;
	use RefreshDatabase;

	/**
	 * Fait venir les requêtes de la SPA, pour que Sanctum ouvre une session.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->withHeader('Origin', 'http://localhost');
	}

	/**
	 * Crée un administrateur par la commande et renvoie le jeton de son lien.
	 *
	 * @param string $email Adresse du compte à créer.
	 * @return string
	 */
	private function createAdmin(string $email = 'root@example.org'): string
	{
		$this->assertSame(0, Artisan::call('suivi:create-admin', ['email' => $email, '--name' => 'Root']));
		// La sortie de la commande ne peut être lue qu'une fois.
		$output = Artisan::output();
		$this->assertMatchesRegularExpression('#/activation/([A-Za-z0-9]{64})#', $output);
		preg_match('#/activation/([A-Za-z0-9]{64})#', $output, $matches);

		return $matches[1];
	}

	/**
	 * La commande crée un administrateur d'instance sans mot de passe.
	 *
	 * @return void
	 */
	public function test_the_command_creates_an_instance_administrator_without_password(): void
	{
		$this->createAdmin('Root@Example.org');

		$user = User::query()->where('email', 'root@example.org')->firstOrFail();
		$this->assertTrue($user->is_instance_admin);
		$this->assertNull($user->getAttributes()['password']);
	}

	/**
	 * La commande refuse un compte déjà actif.
	 *
	 * @return void
	 */
	public function test_the_command_refuses_an_active_account(): void
	{
		$token = $this->createAdmin();
		$this->postJson('/api/v1/auth/activate', ['token' => $token, 'auth' => base64_encode(random_bytes(32))] + $this->vaultPayload())->assertNoContent();

		$this->assertSame(1, Artisan::call('suivi:create-admin', ['email' => 'root@example.org']));
	}

	/**
	 * La commande renouvelle le lien d'un compte jamais activé.
	 *
	 * @return void
	 */
	public function test_the_command_renews_the_link_of_an_account_never_activated(): void
	{
		$old = $this->createAdmin();
		$this->travel(25)->hours();

		$new = $this->createAdmin();

		$this->assertNotSame($old, $new);
		$this->getJson("/api/v1/auth/activation/{$old}")->assertNotFound();
		$this->getJson("/api/v1/auth/activation/{$new}")->assertOk();
		$this->assertSame(1, User::query()->where('email', 'root@example.org')->count());
	}

	/**
	 * Le lien d'activation révèle l'adresse nécessaire pour dériver la valeur d'authentification.
	 *
	 * @return void
	 */
	public function test_the_activation_link_reveals_the_address_needed_to_derive_auth(): void
	{
		$token = $this->createAdmin();

		$this->getJson("/api/v1/auth/activation/{$token}")
			->assertOk()
			->assertExactJson(['email' => 'root@example.org', 'name' => 'Root']);
	}

	/**
	 * L'activation enregistre la valeur d'authentification, puis la connexion fonctionne.
	 *
	 * @return void
	 */
	public function test_activation_sets_the_authentication_value_then_login_works(): void
	{
		$token = $this->createAdmin();
		$auth = base64_encode(random_bytes(32));

		$this->postJson('/api/v1/auth/activate', ['token' => $token, 'auth' => $auth] + $this->vaultPayload())->assertNoContent();

		$this->postJson('/api/v1/auth/login', ['email' => 'root@example.org', 'auth' => $auth])->assertOk();
	}

	/**
	 * Un lien d'activation ne sert qu'une fois.
	 *
	 * @return void
	 */
	public function test_an_activation_link_works_only_once(): void
	{
		$token = $this->createAdmin();
		$this->postJson('/api/v1/auth/activate', ['token' => $token, 'auth' => base64_encode(random_bytes(32))] + $this->vaultPayload())->assertNoContent();

		$this->getJson("/api/v1/auth/activation/{$token}")->assertNotFound();
		$this->postJson('/api/v1/auth/activate', ['token' => $token, 'auth' => base64_encode(random_bytes(32))] + $this->vaultPayload())->assertNotFound();
	}

	/**
	 * Un lien d'activation expire après 72 heures (11.06, AUTH_ACTIVATION_HOURS).
	 *
	 * @return void
	 */
	public function test_an_activation_link_expires_after_72_hours(): void
	{
		$token = $this->createAdmin();

		$this->travel(71)->hours();
		$this->getJson("/api/v1/auth/activation/{$token}")->assertOk();
		$this->travel(2)->hours();

		$this->getJson("/api/v1/auth/activation/{$token}")->assertNotFound();
	}

	/**
	 * Un jeton inconnu ne révèle rien.
	 *
	 * @return void
	 */
	public function test_an_unknown_token_reveals_nothing(): void
	{
		$this->getJson('/api/v1/auth/activation/'.str_repeat('a', 64))->assertNotFound()->assertJsonPath('code', 'not_found');
	}

	/**
	 * Le jeton n'est jamais stocké en clair.
	 *
	 * @return void
	 */
	public function test_the_token_is_never_stored_in_clear(): void
	{
		$token = $this->createAdmin();

		$this->assertDatabaseMissing('users', ['activation_token_hash' => $token]);
		$this->assertDatabaseHas('users', ['activation_token_hash' => hash('sha256', $token)]);
	}

	/**
	 * L'activation utilise la classe de limitation auth.
	 *
	 * @return void
	 */
	public function test_activation_uses_the_auth_rate_limit_class(): void
	{
		config(['api.rate_limits.auth.per_minute' => 1]);

		$this->getJson('/api/v1/auth/activation/'.str_repeat('a', 64))->assertNotFound();
		$this->getJson('/api/v1/auth/activation/'.str_repeat('a', 64))->assertStatus(429);
	}

	/**
	 * L'identifiant de l'instance est public et stable.
	 *
	 * @return void
	 */
	public function test_the_instance_identifier_is_public_and_stable(): void
	{
		$first = $this->getJson('/api/v1/instance')->assertOk()->json('instance_id');

		$this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $first);
		$this->assertSame($first, $this->getJson('/api/v1/instance')->json('instance_id'));
	}

	/**
	 * Le délai d'inactivité du coffre est publié, jamais en dessous d'une minute (ged-chiffree-2).
	 *
	 * @return void
	 */
	public function test_the_vault_idle_delay_is_published(): void
	{
		$this->getJson('/api/v1/instance')->assertJsonPath('vault_idle_minutes', 30);

		config(['suivi.vault.idle_minutes' => 0]);
		$this->getJson('/api/v1/instance')->assertJsonPath('vault_idle_minutes', 1);
	}
}
