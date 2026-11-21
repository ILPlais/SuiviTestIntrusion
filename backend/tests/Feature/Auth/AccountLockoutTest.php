<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verrouillage de compte (13.03) : échecs consécutifs comptés par compte,
 * ralentissement à partir de 5, verrouillage de 15 minutes à 10, remise à zéro
 * après une connexion réussie.
 */
class AccountLockoutTest extends TestCase
{
	use RefreshDatabase;

	private string $auth;

	private User $user;

	protected function setUp(): void
	{
		parent::setUp();

		$this->withHeader('Origin', 'http://localhost');
		// La limite par adresse IP (classe auth) est testée ailleurs ; ici, seul le compte compte.
		config(['api.rate_limits.auth.per_minute' => 1000]);

		$this->auth = base64_encode(random_bytes(32));
		$this->user = User::factory()->create(['email' => 'alice@example.org', 'password' => $this->auth]);
	}

	/**
	 * Tente une connexion avec la bonne ou une mauvaise valeur d'authentification.
	 */
	private function attempt(bool $correct): int
	{
		return $this->postJson('/api/v1/auth/login', [
			'email' => 'alice@example.org',
			'auth' => $correct ? $this->auth : base64_encode(random_bytes(32)),
		])->getStatusCode();
	}

	/**
	 * Enchaîne des échecs en laissant passer le délai de ralentissement entre chacun.
	 */
	private function failTimes(int $times): void
	{
		for ($i = 0; $i < $times; $i++) {
			$this->assertSame(422, $this->attempt(false));
			$this->travel(5)->minutes();
		}
	}

	public function test_failures_are_counted_and_reset_by_a_successful_login(): void
	{
		$this->failTimes(3);
		$this->assertSame(3, $this->user->fresh()->failed_login_count);

		$this->assertSame(200, $this->attempt(true));
		$this->assertSame(0, $this->user->fresh()->failed_login_count);
	}

	public function test_an_immediate_attempt_after_five_failures_is_refused(): void
	{
		for ($i = 0; $i < 5; $i++) {
			$this->assertSame(422, $this->attempt(false));
		}

		// Tentative immédiate refusée, même avec la bonne valeur, sans compter un échec de plus.
		$this->assertSame(422, $this->attempt(true));
		$this->assertSame(5, $this->user->fresh()->failed_login_count);

		$this->travel(5)->minutes();
		$this->assertSame(200, $this->attempt(true));
	}

	public function test_ten_failures_lock_the_account_for_fifteen_minutes(): void
	{
		$this->failTimes(10);

		$this->assertNotNull($this->user->fresh()->locked_until);
		$this->assertSame(422, $this->attempt(true));

		$this->travel(16)->minutes();
		$this->assertSame(200, $this->attempt(true));
		$this->assertNull($this->user->fresh()->locked_until);
	}

	public function test_lockout_settings_follow_the_decision(): void
	{
		$this->assertSame(10, config('suivi.authentication.lockout_failures'));
		$this->assertSame(15, config('suivi.authentication.lockout_minutes'));
		$this->assertSame(5, config('suivi.authentication.slowdown_after'));
	}
}
