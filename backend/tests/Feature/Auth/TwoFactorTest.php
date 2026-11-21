<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Double authentification TOTP (identite-3, 11.06) : enrôlement compatible
 * Google Authenticator, codes de secours à usage unique, connexion en deux
 * temps, activation obligatoire par politique d'instance.
 */
class TwoFactorTest extends TestCase
{
	use RefreshDatabase;

	private string $auth;

	private User $user;

	private Google2FA $totp;

	protected function setUp(): void
	{
		parent::setUp();

		$this->withHeader('Origin', 'http://localhost');
		config(['api.rate_limits.auth.per_minute' => 1000]);

		$this->totp = new Google2FA();
		$this->auth = base64_encode(random_bytes(32));
		$this->user = User::factory()->create(['email' => 'alice@example.org', 'password' => $this->auth]);

		// Route métier d'essai, soumise à la politique de double authentification.
		Route::middleware(['api', 'auth:sanctum', 'two-factor'])->get('api/v1/_essai/metier', fn () => ['ok' => true]);
	}

	/**
	 * Connexion complète sans double authentification (premier facteur seul).
	 */
	private function login(): void
	{
		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth])->assertOk();
	}

	/**
	 * Enrôle l'utilisateur connecté et renvoie [secret, codes de secours].
	 *
	 * @return array{0: string, 1: list<string>}
	 */
	private function enroll(): array
	{
		$secret = $this->postJson('/api/v1/user/two-factor')->assertOk()->json('secret');
		$codes = $this->postJson('/api/v1/user/two-factor/confirm', ['code' => $this->otp($secret)])
			->assertOk()
			->json('recovery_codes');

		return [$secret, $codes];
	}

	/**
	 * Code TOTP courant, selon l'horloge de Laravel (que travel() déplace), comme le service.
	 */
	private function otp(string $secret): string
	{
		return $this->totp->oathTotp($secret, intdiv(now()->getTimestamp(), 30));
	}

	/**
	 * Termine la session et oublie les gardes, comme un nouveau navigateur ; avance
	 * l'horloge d'un pas TOTP, car un code déjà accepté ne sert pas deux fois.
	 */
	private function freshSession(): void
	{
		$this->travel(31)->seconds();
		$this->postJson('/api/v1/auth/logout');
		$this->app['auth']->forgetGuards();
		$this->flushSession();
	}

	public function test_enrollment_returns_a_google_authenticator_secret(): void
	{
		$this->login();

		$response = $this->postJson('/api/v1/user/two-factor')->assertOk();

		$this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $response->json('secret'));
		$this->assertStringStartsWith('otpauth://totp/', $response->json('otpauth_url'));
		$this->assertStringContainsString('alice%40example.org', $response->json('otpauth_url'));
	}

	public function test_confirmation_with_a_valid_code_returns_ten_recovery_codes_once(): void
	{
		$this->login();

		[, $codes] = $this->enroll();

		$this->assertCount(10, $codes);
		$this->assertNotNull($this->user->fresh()->two_factor_confirmed_at);
		$this->getJson('/api/v1/user')->assertJsonPath('two_factor_enabled', true);
	}

	public function test_confirmation_with_a_wrong_code_is_refused(): void
	{
		$this->login();
		$this->postJson('/api/v1/user/two-factor');

		$this->postJson('/api/v1/user/two-factor/confirm', ['code' => '000000'])->assertStatus(422);
		$this->assertNull($this->user->fresh()->two_factor_confirmed_at);
	}

	public function test_the_secret_is_encrypted_and_recovery_codes_are_hashed_at_rest(): void
	{
		$this->login();
		[$secret, $codes] = $this->enroll();

		$row = (array) DB::table('users')->where('id', $this->user->id)->first();
		$this->assertStringNotContainsString($secret, (string) $row['two_factor_secret']);
		$this->assertStringNotContainsString($codes[0], (string) $row['two_factor_recovery_codes']);
	}

	public function test_login_with_two_factor_requires_the_code(): void
	{
		$this->login();
		[$secret] = $this->enroll();
		$this->freshSession();

		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth])
			->assertOk()
			->assertExactJson(['two_factor_required' => true]);
		$this->assertGuest('web');

		$this->postJson('/api/v1/auth/two-factor', ['code' => $this->otp($secret)])
			->assertOk()
			->assertJsonPath('email', 'alice@example.org');
		$this->assertAuthenticatedAs($this->user, 'web');
	}

	public function test_a_wrong_code_is_refused_and_counted_as_a_failure(): void
	{
		$this->login();
		$this->enroll();
		$this->freshSession();

		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth]);
		$this->postJson('/api/v1/auth/two-factor', ['code' => '000000'])->assertStatus(422);

		$this->assertGuest('web');
		$this->assertSame(1, $this->user->fresh()->failed_login_count);
	}

	public function test_logging_in_again_does_not_reset_code_failures(): void
	{
		$this->login();
		$this->enroll();
		$this->freshSession();

		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth]);
		$this->postJson('/api/v1/auth/two-factor', ['code' => '000000'])->assertStatus(422);
		$this->postJson('/api/v1/auth/two-factor', ['code' => '000001'])->assertStatus(422);

		// Le premier facteur, que détiendrait l'attaquant, ne remet pas le compteur à zéro.
		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth])->assertOk();
		$this->assertSame(2, $this->user->fresh()->failed_login_count);
	}

	public function test_a_wrong_code_on_a_management_route_counts_as_a_failure(): void
	{
		$this->login();
		$this->enroll();

		$this->postJson('/api/v1/user/two-factor/recovery-codes', ['code' => '000000'])->assertStatus(422);

		$this->assertSame(1, $this->user->fresh()->failed_login_count);
	}

	public function test_a_code_cannot_be_used_twice(): void
	{
		$this->login();
		[$secret] = $this->enroll();
		$this->freshSession();

		$code = $this->otp($secret);
		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth]);
		$this->postJson('/api/v1/auth/two-factor', ['code' => $code])->assertOk();
		$this->freshSession();

		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth]);
		$this->postJson('/api/v1/auth/two-factor', ['code' => $code])->assertStatus(422);
	}

	public function test_the_second_step_requires_a_successful_first_step(): void
	{
		$this->login();
		[$secret] = $this->enroll();
		$this->freshSession();

		$this->postJson('/api/v1/auth/two-factor', ['code' => $this->otp($secret)])->assertStatus(422);
		$this->assertGuest('web');
	}

	public function test_the_pending_login_expires_after_five_minutes(): void
	{
		$this->login();
		[$secret] = $this->enroll();
		$this->freshSession();

		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth]);
		$this->travel(6)->minutes();

		$this->postJson('/api/v1/auth/two-factor', ['code' => $this->otp($secret)])->assertStatus(422);
	}

	public function test_a_recovery_code_works_once(): void
	{
		$this->login();
		[, $codes] = $this->enroll();
		$this->freshSession();

		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth]);
		$this->postJson('/api/v1/auth/two-factor', ['recovery_code' => strtolower($codes[0])])->assertOk();
		$this->freshSession();

		$this->postJson('/api/v1/auth/login', ['email' => 'alice@example.org', 'auth' => $this->auth]);
		$this->postJson('/api/v1/auth/two-factor', ['recovery_code' => $codes[0]])->assertStatus(422);
	}

	public function test_recovery_codes_can_be_regenerated_with_a_valid_code(): void
	{
		$this->login();
		[$secret, $old] = $this->enroll();
		$this->travel(31)->seconds();

		$new = $this->postJson('/api/v1/user/two-factor/recovery-codes', ['code' => $this->otp($secret)])
			->assertOk()
			->json('recovery_codes');

		$this->assertCount(10, $new);
		$this->assertEmpty(array_intersect($old, $new));
	}

	public function test_disabling_requires_a_valid_code(): void
	{
		$this->login();
		[$secret] = $this->enroll();
		$this->travel(31)->seconds();

		$this->deleteJson('/api/v1/user/two-factor', ['code' => '000000'])->assertStatus(422);
		$this->deleteJson('/api/v1/user/two-factor', ['code' => $this->otp($secret)])->assertNoContent();

		$this->assertNull($this->user->fresh()->two_factor_confirmed_at);
	}

	public function test_the_instance_policy_blocks_business_routes_until_enrollment(): void
	{
		config(['suivi.authentication.two_factor_required' => true]);
		$this->login();

		$this->getJson('/api/v1/user')->assertJsonPath('two_factor_setup_required', true);
		$this->getJson('/api/v1/_essai/metier')->assertForbidden()->assertJsonPath('code', 'forbidden');

		$this->enroll();

		$this->getJson('/api/v1/user')->assertJsonPath('two_factor_setup_required', false);
		$this->getJson('/api/v1/_essai/metier')->assertOk();
	}

	public function test_the_instance_policy_forbids_disabling(): void
	{
		config(['suivi.authentication.two_factor_required' => true]);
		$this->login();
		[$secret] = $this->enroll();
		$this->travel(31)->seconds();

		$this->deleteJson('/api/v1/user/two-factor', ['code' => $this->otp($secret)])->assertForbidden();
	}

	public function test_the_policy_is_off_by_default(): void
	{
		$this->assertFalse(config('suivi.authentication.two_factor_required'));
	}
}
