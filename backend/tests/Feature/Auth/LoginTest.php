<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Connexion et déconnexion par session Sanctum (identite-1, 13.03).
 *
 * Le navigateur n'envoie jamais le mot de passe : il envoie `auth`, la valeur
 * d'authentification dérivée par Argon2id (32 octets en Base64), que le serveur
 * hache à son tour comme un mot de passe.
 */
class LoginTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * Valeur d'authentification de l'utilisateur d'essai.
	 *
	 * @var string
	 */
	private string $auth;

	/**
	 * Compte de test, activé.
	 *
	 * @var User
	 */
	private User $user;

	/**
	 * Crée un compte activé et prépare la session SPA.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		// Requêtes venant de la SPA : Sanctum ouvre alors une session (statefulApi).
		$this->withHeader('Origin', 'http://localhost');

		$this->auth = base64_encode(random_bytes(32));
		$this->user = User::factory()->create(['email' => 'alice@example.org', 'password' => $this->auth]);
	}

	/**
	 * Une valeur d'authentification valide ouvre une session.
	 *
	 * @return void
	 */
	public function test_a_valid_authentication_value_opens_a_session(): void
	{
		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth])
			->assertOk()
			->assertJsonPath('id', $this->user->id)
			->assertJsonPath('email', 'alice@example.org')
			->assertJsonMissingPath('password');

		$this->assertAuthenticatedAs($this->user, 'web');
	}

	/**
	 * L'adresse est comparée sans tenir compte de la casse ni des espaces autour.
	 *
	 * @return void
	 */
	public function test_the_email_is_compared_without_case_or_surrounding_spaces(): void
	{
		$this->postJson('/api/v1/auth/login', ['email' => '  Alice@Example.ORG ', 'auth' => $this->auth])->assertOk();
	}

	/**
	 * Une valeur d'authentification fausse est refusée avec un message générique.
	 *
	 * @return void
	 */
	public function test_a_wrong_authentication_value_is_refused_with_a_generic_message(): void
	{
		$response = $this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => base64_encode(random_bytes(32))]);

		$response->assertStatus(422)->assertJsonPath('code', 'validation_failed');
		$this->assertGuest('web');

		// Même réponse pour une adresse inconnue : rien ne révèle l'existence d'un compte.
		$unknown = $this->postJson('/api/v1/auth/login', ['email' => 'inconnu@example.org', 'auth' => $this->auth]);
		$unknown->assertStatus(422);
		$this->assertSame($response->json('details'), $unknown->json('details'));
	}

	/**
	 * Un compte pas encore activé ne peut pas se connecter.
	 *
	 * @return void
	 */
	public function test_an_account_not_yet_activated_cannot_log_in(): void
	{
		User::factory()->create(['email' => 'bob@example.org', 'password' => null]);

		$this->postJson('/api/v1/auth/login', ['email' => 'bob@example.org', 'auth' => $this->auth])->assertStatus(422);
	}

	/**
	 * Une valeur d'authentification mal formée est rejetée.
	 *
	 * @return void
	 */
	public function test_a_malformed_authentication_value_is_rejected(): void
	{
		// Un mot de passe en clair envoyé par erreur n'est pas accepté comme valeur d'authentification.
		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => 'mot-de-passe-en-clair'])
			->assertStatus(422)
			->assertJsonStructure(['details' => ['auth']]);
	}

	/**
	 * Une requête sans session SPA ne peut pas se connecter.
	 *
	 * @return void
	 */
	public function test_a_request_without_spa_session_cannot_log_in(): void
	{
		// Sans origine reconnue par Sanctum, aucune session n'est ouverte : la connexion est refusée.
		$this->flushHeaders();

		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth])->assertForbidden();
	}

	/**
	 * Une valeur base64 non canonique est rejetée.
	 *
	 * @return void
	 */
	public function test_a_non_canonical_base64_value_is_rejected(): void
	{
		// Même décodage, autre chaîne (bits de remplissage non nuls) : refusée, car la chaîne est hachée.
		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => str_repeat('A', 42).'B='])
			->assertStatus(422)
			->assertJsonStructure(['details' => ['auth']]);
	}

	/**
	 * La déconnexion ferme la session.
	 *
	 * @return void
	 */
	public function test_logout_closes_the_session(): void
	{
		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth])->assertOk();

		$this->postJson('/api/v1/auth/logout')->assertNoContent();

		$this->assertGuest('web');
		// Les gardes gardent l'utilisateur en mémoire d'une requête de test à l'autre.
		$this->app['auth']->forgetGuards();
		$this->getJson('/api/v1/user')->assertStatus(401);
	}

	/**
	 * L'utilisateur courant n'expose que ses champs publics.
	 *
	 * @return void
	 */
	public function test_the_current_user_exposes_only_public_fields(): void
	{
		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth]);

		$this->getJson('/api/v1/user')
			->assertOk()
			->assertJsonStructure(['id', 'name', 'email', 'is_instance_admin'])
			->assertJsonMissingPath('password')
			->assertJsonMissingPath('failed_login_count');
	}

	/**
	 * La connexion utilise la classe de limitation auth.
	 *
	 * @return void
	 */
	public function test_login_uses_the_auth_rate_limit_class(): void
	{
		config(['api.rate_limits.auth.per_minute' => 2]);
		$wrong = ['email' => 'alice@example.org', 'auth' => base64_encode(random_bytes(32))];

		$this->postJson('/api/v1/auth/login', $wrong)->assertStatus(422);
		$this->postJson('/api/v1/auth/login', $wrong)->assertStatus(422);
		$this->postJson('/api/v1/auth/login', $wrong)->assertStatus(429)->assertJsonPath('code', 'rate_limited');
	}

	/**
	 * Les comptes locaux sont hachés avec Argon2id.
	 *
	 * @return void
	 */
	public function test_local_accounts_are_hashed_with_argon2id(): void
	{
		$this->assertStringStartsWith('$argon2id$', $this->user->fresh()->getAuthPassword());
	}
}
