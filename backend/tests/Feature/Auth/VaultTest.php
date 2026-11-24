<?php

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsVaults;
use Tests\TestCase;

/**
 * Coffre de clés (ged-chiffree-1, 13.02, 13.03) : enrôlement avec les deux
 * copies, livraison de la copie 1 après l'authentification complète,
 * changement de mot de passe sans toucher à la clé publique ni à la copie 2.
 */
class VaultTest extends TestCase
{
	use BuildsVaults;
	use RefreshDatabase;

	/**
	 * Prépare les requêtes de la SPA (session Sanctum) sans limite de débit gênante.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->withHeader('Origin', 'http://localhost');
		config(['api.rate_limits.auth.per_minute' => 1000]);
	}

	/**
	 * Crée un compte par la commande d'installation et l'active avec un coffre.
	 *
	 * @param string $auth Valeur d'authentification choisie.
	 * @return array{user: User, vault: array{public_key: string, key_fingerprint: string, password_copy: string, recovery_copy: string}}
	 */
	private function enrolled(string $auth): array
	{
		Artisan::call('suivi:create-admin', ['email' => 'root@example.org']);
		preg_match('#/activation/([A-Za-z0-9]{64})#', Artisan::output(), $matches);
		$vault = $this->vaultPayload();
		$this->postJson('/api/v1/auth/activate', ['token' => $matches[1], 'auth' => $auth] + $vault)->assertNoContent();

		return ['user' => User::query()->where('email', 'root@example.org')->sole(), 'vault' => $vault];
	}

	/**
	 * Ouvre une session par la connexion habituelle.
	 *
	 * @param string $auth Valeur d'authentification.
	 * @return void
	 */
	private function login(string $auth): void
	{
		$this->postJson('/api/v1/auth/login', ['email' => 'root@example.org', 'auth' => $auth])->assertOk();
	}

	/**
	 * L'activation enregistre la clé publique, l'empreinte et les deux copies ;
	 * l'empreinte est consignée dans le journal d'audit.
	 *
	 * @return void
	 */
	public function test_activation_stores_the_vault(): void
	{
		['user' => $user, 'vault' => $vault] = $this->enrolled(base64_encode(random_bytes(32)));

		$this->assertSame($vault['public_key'], $user->public_key);
		$this->assertSame($vault['key_fingerprint'], $user->key_fingerprint);
		$this->assertSame($vault['password_copy'], $user->private_key_password_copy);
		$this->assertSame($vault['recovery_copy'], $user->private_key_recovery_copy);
		$this->assertSame(1, $user->key_version);
		$event = AuditEvent::query()->where('action', AuditAction::AccountActivated)->sole();
		$this->assertSame(['key_fingerprint' => $vault['key_fingerprint']], $event->after);
	}

	/**
	 * L'activation exige un coffre bien formé : sans lui, le compte reste en attente.
	 *
	 * @return void
	 */
	public function test_activation_requires_a_well_formed_vault(): void
	{
		Artisan::call('suivi:create-admin', ['email' => 'root@example.org']);
		preg_match('#/activation/([A-Za-z0-9]{64})#', Artisan::output(), $matches);
		$auth = base64_encode(random_bytes(32));

		$this->postJson('/api/v1/auth/activate', ['token' => $matches[1], 'auth' => $auth])
			->assertUnprocessable()
			->assertJsonValidationErrors(['public_key', 'key_fingerprint', 'password_copy', 'recovery_copy'], 'details');
		$this->postJson('/api/v1/auth/activate', ['token' => $matches[1], 'auth' => $auth, 'key_fingerprint' => 'ABC', 'password_copy' => $this->armored('PUBLIC KEY')] + $this->vaultPayload())
			->assertUnprocessable();

		$this->assertNull(User::query()->sole()->getAttributes()['password']);
	}

	/**
	 * La copie 1 n'est livrée qu'à l'utilisateur connecté ; la copie 2 jamais.
	 *
	 * @return void
	 */
	public function test_the_password_copy_is_served_after_login_but_never_the_recovery_copy(): void
	{
		$auth = base64_encode(random_bytes(32));
		['vault' => $vault] = $this->enrolled($auth);

		$this->getJson('/api/v1/user/vault')->assertUnauthorized();

		$this->login($auth);
		$this->getJson('/api/v1/user')->assertJsonPath('key_fingerprint', $vault['key_fingerprint']);
		$response = $this->getJson('/api/v1/user/vault')
			->assertOk()
			->assertExactJson([
				'public_key' => $vault['public_key'],
				'key_fingerprint' => $vault['key_fingerprint'],
				'password_copy' => $vault['password_copy'],
				'version' => 1,
			]);
		$this->assertStringNotContainsString($vault['recovery_copy'], (string) $response->getContent());
	}

	/**
	 * Un compte enrôlé avant le coffre n'en a pas : 404.
	 *
	 * @return void
	 */
	public function test_an_account_without_vault_has_no_vault(): void
	{
		$auth = base64_encode(random_bytes(32));
		$user = User::factory()->create(['email' => 'root@example.org', 'password' => $auth]);
		$this->login($auth);

		$this->getJson('/api/v1/user/vault')->assertNotFound();
		$this->getJson('/api/v1/user')->assertJsonPath('key_fingerprint', null);
		$this->assertFalse($user->hasVault());
	}

	/**
	 * Le changement de mot de passe remplace hachage et copie 1, sans toucher à
	 * la clé publique ni à la copie 2 ; l'ancien mot de passe ne vaut plus.
	 *
	 * @return void
	 */
	public function test_changing_the_password_replaces_only_the_password_copy(): void
	{
		$auth = base64_encode(random_bytes(32));
		['vault' => $vault] = $this->enrolled($auth);
		$this->login($auth);
		$newAuth = base64_encode(random_bytes(32));
		$newCopy = $this->armored('PRIVATE KEY');

		$this->putJson('/api/v1/user/password', ['current_auth' => $auth, 'auth' => $newAuth, 'password_copy' => $newCopy, 'version' => 1])
			->assertNoContent();

		$user = User::query()->sole();
		$this->assertTrue(Hash::check($newAuth, (string) $user->getAuthPassword()));
		$this->assertSame($newCopy, $user->private_key_password_copy);
		$this->assertSame(2, $user->key_version);
		$this->assertSame($vault['public_key'], $user->public_key);
		$this->assertSame($vault['recovery_copy'], $user->private_key_recovery_copy);
		$this->assertSame($vault['key_fingerprint'], $user->key_fingerprint);
		$this->assertSame(1, AuditEvent::query()->where('action', AuditAction::PasswordChanged)->count());

		// La session courante reste ouverte ; l'ancien mot de passe est refusé.
		$this->getJson('/api/v1/user/vault')->assertOk()->assertJsonPath('password_copy', $newCopy);
		$this->postJson('/api/v1/auth/logout')->assertNoContent();
		$this->postJson('/api/v1/auth/login', ['email' => 'root@example.org', 'auth' => $auth])->assertUnprocessable();
		$this->login($newAuth);
	}

	/**
	 * Une rotation faite entre-temps ailleurs (version changée) est refusée : 409.
	 *
	 * @return void
	 */
	public function test_a_stale_version_is_refused(): void
	{
		$auth = base64_encode(random_bytes(32));
		['vault' => $vault] = $this->enrolled($auth);
		$this->login($auth);
		User::query()->update(['key_version' => 2]);

		$this->putJson('/api/v1/user/password', ['current_auth' => $auth, 'auth' => base64_encode(random_bytes(32)), 'password_copy' => $this->armored('PRIVATE KEY'), 'version' => 1])
			->assertStatus(409);

		$user = User::query()->sole();
		$this->assertTrue(Hash::check($auth, (string) $user->getAuthPassword()));
		$this->assertSame($vault['password_copy'], $user->private_key_password_copy);
	}

	/**
	 * Un mot de passe actuel faux est refusé et compte comme un échec de connexion ;
	 * un nouveau mot de passe identique à l'actuel aussi est refusé.
	 *
	 * @return void
	 */
	public function test_a_wrong_current_password_is_refused_and_counted(): void
	{
		$auth = base64_encode(random_bytes(32));
		$this->enrolled($auth);
		$this->login($auth);

		$this->putJson('/api/v1/user/password', ['current_auth' => base64_encode(random_bytes(32)), 'auth' => base64_encode(random_bytes(32)), 'password_copy' => $this->armored('PRIVATE KEY'), 'version' => 1])
			->assertUnprocessable()
			->assertJsonValidationErrors(['current_auth'], 'details');
		$this->assertSame(1, User::query()->sole()->failed_login_count);

		$this->putJson('/api/v1/user/password', ['current_auth' => $auth, 'auth' => $auth, 'password_copy' => $this->armored('PRIVATE KEY'), 'version' => 1])
			->assertUnprocessable()
			->assertJsonValidationErrors(['auth'], 'details');
	}

	/**
	 * Sans coffre, le mot de passe change sans copie ; une copie envoyée est refusée.
	 *
	 * @return void
	 */
	public function test_an_account_without_vault_changes_only_its_password(): void
	{
		$auth = base64_encode(random_bytes(32));
		User::factory()->create(['email' => 'root@example.org', 'password' => $auth]);
		$this->login($auth);
		$newAuth = base64_encode(random_bytes(32));

		$this->putJson('/api/v1/user/password', ['current_auth' => $auth, 'auth' => $newAuth, 'password_copy' => $this->armored('PRIVATE KEY'), 'version' => 1])
			->assertUnprocessable();
		$this->putJson('/api/v1/user/password', ['current_auth' => $auth, 'auth' => $newAuth])->assertNoContent();

		$this->assertTrue(Hash::check($newAuth, (string) User::query()->sole()->getAuthPassword()));
	}
}
