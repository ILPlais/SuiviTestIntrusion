<?php

namespace Tests\Feature\Accounts;

use App\Auth\TwoFactor;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\BuildsVaults;
use Tests\TestCase;

/**
 * Mot de passe oublié (identite-7, 13.03) : le lien ne livre que la copie 2,
 * après le second facteur s'il est actif ; le remplacement garde la paire de
 * clés, change `auth` et les deux copies, révoque les accès et invalide le lien.
 */
class PasswordResetTest extends TestCase
{
	use BuildsVaults;
	use RefreshDatabase;

	/**
	 * Valeur d'authentification initiale du compte.
	 *
	 * @var string
	 */
	private string $oldAuth;

	/**
	 * Compte à réinitialiser.
	 *
	 * @var User
	 */
	private User $user;

	/**
	 * Prépare un compte actif avec coffre et les requêtes de la SPA.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->withHeader('Origin', 'http://localhost');
		config(['api.rate_limits.auth.per_minute' => 1000]);
		$this->oldAuth = base64_encode(random_bytes(32));
		$vault = $this->vaultPayload();
		$this->user = User::factory()->create([
			'email' => 'jo@example.org',
			'password' => $this->oldAuth,
			'public_key' => $vault['public_key'],
			'key_fingerprint' => $vault['key_fingerprint'],
			'private_key_password_copy' => $vault['password_copy'],
			'private_key_recovery_copy' => $vault['recovery_copy'],
			'key_version' => 1,
		]);
	}

	/**
	 * Émet le lien par un administrateur et renvoie son jeton.
	 *
	 * @return string
	 */
	private function issueToken(): string
	{
		Sanctum::actingAs(User::factory()->create(['is_instance_admin' => true]));
		$link = (string) $this->postJson("/api/v1/users/{$this->user->id}/password-reset")->assertOk()->json('link');
		$this->app['auth']->forgetGuards();

		return substr($link, -64);
	}

	/**
	 * Corps du remplacement : nouvel `auth`, nouvelles copies, même empreinte.
	 *
	 * @param string $token Jeton du lien.
	 * @param string $auth Nouvelle valeur d'authentification.
	 * @return array<string, mixed>
	 */
	private function resetPayload(string $token, string $auth): array
	{
		$vault = $this->vaultPayload();

		return [
			'token' => $token,
			'auth' => $auth,
			'key_fingerprint' => $this->user->key_fingerprint,
			'password_copy' => $vault['password_copy'],
			'recovery_copy' => $vault['recovery_copy'],
			'version' => 1,
		];
	}

	/**
	 * Parcours complet : copie 2 livrée, remplacement atomique, ancien mot de
	 * passe refusé, nouveau accepté, lien à usage unique, accès révoqués.
	 *
	 * @return void
	 */
	public function test_full_reset_with_the_recovery_key(): void
	{
		$token = $this->issueToken();
		$this->user->createToken('cli');
		$recoveryCopy = $this->user->private_key_recovery_copy;

		$this->getJson("/api/v1/auth/password-reset/{$token}")->assertOk()
			->assertJsonPath('email', 'jo@example.org')
			->assertJsonPath('two_factor_required', false);
		$this->postJson("/api/v1/auth/password-reset/{$token}/open")->assertOk()
			->assertJsonPath('recovery_copy', $recoveryCopy)
			->assertJsonPath('key_fingerprint', $this->user->key_fingerprint)
			->assertJsonPath('version', 1)
			->assertJsonMissingPath('password_copy');

		$newAuth = base64_encode(random_bytes(32));
		$payload = $this->resetPayload($token, $newAuth);
		$this->postJson('/api/v1/auth/password-reset', $payload)->assertNoContent();

		$this->user->refresh();
		$this->assertTrue(Hash::check($newAuth, (string) $this->user->getAuthPassword()));
		$this->assertSame($payload['password_copy'], $this->user->private_key_password_copy);
		$this->assertSame($payload['recovery_copy'], $this->user->private_key_recovery_copy);
		$this->assertSame(2, $this->user->key_version);
		$this->assertNull($this->user->reset_token_hash);
		$this->assertSame(0, $this->user->tokens()->count());
		$this->assertSame(1, AuditEvent::query()->where('action', AuditAction::PasswordReset)->count());

		// Usage unique : le lien ne vaut plus.
		$this->getJson("/api/v1/auth/password-reset/{$token}")->assertNotFound();
		$this->postJson('/api/v1/auth/login', ['email' => 'jo@example.org', 'auth' => $this->oldAuth])->assertUnprocessable();
		$this->postJson('/api/v1/auth/login', ['email' => 'jo@example.org', 'auth' => $newAuth])->assertOk();
	}

	/**
	 * Sans la copie 2 livrée à cette session, le remplacement est refusé.
	 *
	 * @return void
	 */
	public function test_store_requires_the_open_step(): void
	{
		$token = $this->issueToken();

		$this->postJson('/api/v1/auth/password-reset', $this->resetPayload($token, base64_encode(random_bytes(32))))
			->assertUnprocessable()
			->assertJsonValidationErrors(['token'], 'details');
	}

	/**
	 * Une autre paire de clés est refusée : c'est une récupération, pas un nouveau coffre.
	 *
	 * @return void
	 */
	public function test_fingerprint_must_match(): void
	{
		$token = $this->issueToken();
		$this->postJson("/api/v1/auth/password-reset/{$token}/open")->assertOk();

		$payload = ['key_fingerprint' => bin2hex(random_bytes(32))] + $this->resetPayload($token, base64_encode(random_bytes(32)));
		$this->postJson('/api/v1/auth/password-reset', $payload)
			->assertUnprocessable()
			->assertJsonValidationErrors(['key_fingerprint'], 'details');
	}

	/**
	 * Avec un second facteur, la copie 2 n'est livrée qu'avec un code valide ;
	 * un code faux compte comme un échec de connexion.
	 *
	 * @return void
	 */
	public function test_two_factor_is_required_before_the_copy(): void
	{
		$twoFactor = app(TwoFactor::class);
		$secret = $twoFactor->generateSecret();
		$this->user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
		$token = $this->issueToken();

		$this->getJson("/api/v1/auth/password-reset/{$token}")->assertJsonPath('two_factor_required', true);
		$this->postJson("/api/v1/auth/password-reset/{$token}/open", ['code' => '000000'])
			->assertUnprocessable()
			->assertJsonMissingPath('recovery_copy');
		$this->assertSame(1, $this->user->refresh()->failed_login_count);

		$code = (new Google2FA())->getCurrentOtp($secret);
		$this->postJson("/api/v1/auth/password-reset/{$token}/open", ['code' => $code])->assertOk()->assertJsonStructure(['recovery_copy']);
	}

	/**
	 * Un lien échu ou inconnu ne livre rien.
	 *
	 * @return void
	 */
	public function test_expired_link_is_refused(): void
	{
		$token = $this->issueToken();
		$this->travel(25)->hours();

		$this->getJson("/api/v1/auth/password-reset/{$token}")->assertNotFound();
		$this->postJson("/api/v1/auth/password-reset/{$token}/open")->assertNotFound();
		$this->getJson('/api/v1/auth/password-reset/'.str_repeat('a', 64))->assertNotFound();
	}
}
