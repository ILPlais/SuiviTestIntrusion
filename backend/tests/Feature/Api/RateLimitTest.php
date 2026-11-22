<?php

namespace Tests\Feature\Api;

use App\Events\RateLimitExceeded;
use App\Http\Api\ConcurrencyLimit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Limites de débit par classe d'appel (11.03, socle-5) : Standard, Écriture lourde,
 * Export et Auth, plafonds réglables, réponse 429 « rate_limited » avec en-têtes
 * X-RateLimit-* et Retry-After, événement émis pour l'audit.
 */
class RateLimitTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * Déclare une route de test par classe de limitation et de concurrence.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		// Routes d'essai, une par classe, avec le groupe « api » comme les vraies routes.
		Route::middleware('api')->prefix('api/v1/_debit')->group(function () {
			Route::get('standard', fn () => ['ok' => true])->middleware('throttle:standard');
			Route::get('connecte', fn () => ['ok' => true])->middleware(['auth:sanctum', 'throttle:standard']);
			Route::post('lourd', fn () => ['ok' => true])->middleware('throttle:heavy');
			Route::post('export', fn () => ['ok' => true])->middleware('throttle:export');
			Route::post('connexion', fn () => ['ok' => true])->middleware('throttle:auth');
			Route::post('televersement', fn () => ['ok' => true])->middleware('concurrent:heavy');
			// Export en flux : le corps indique si le créneau est encore pris pendant l'envoi.
			Route::post('export-flux', fn () => response()->stream(function () {
				echo Cache::lock(ConcurrencyLimit::slotKey('export', 'ip:127.0.0.1', 0), 1)->get() ? 'libre' : 'pris';
			}))->middleware('concurrent:export');
		});
	}

	/**
	 * Les plafonds par défaut suivent la spécification.
	 *
	 * @return void
	 */
	public function test_default_ceilings_follow_the_specification(): void
	{
		$this->assertSame(60, config('api.rate_limits.standard.per_minute'));
		$this->assertSame(20, config('api.rate_limits.heavy.per_minute'));
		$this->assertSame(1, config('api.rate_limits.heavy.concurrent'));
		$this->assertSame(5, config('api.rate_limits.export.per_minute'));
		$this->assertSame(2, config('api.rate_limits.export.concurrent'));
		$this->assertSame(10, config('api.rate_limits.auth.per_minute'));
	}

	/**
	 * Les plafonds sont documentés dans l'environnement d'exemple.
	 *
	 * @return void
	 */
	public function test_ceilings_are_documented_in_the_example_environment(): void
	{
		$example = file_get_contents(base_path('.env.example'));

		foreach (['API_RATE_STANDARD', 'API_RATE_HEAVY', 'API_RATE_EXPORT', 'API_RATE_AUTH', 'API_CONCURRENT_HEAVY', 'API_CONCURRENT_EXPORT', 'API_MAX_JSON_SIZE'] as $variable) {
			$this->assertStringContainsString("{$variable}=", $example);
		}
	}

	/**
	 * Les réponses réussies annoncent le plafond.
	 *
	 * @return void
	 */
	public function test_successful_responses_announce_the_ceiling(): void
	{
		config(['api.rate_limits.standard.per_minute' => 3]);

		$this->getJson('/api/v1/_debit/standard')
			->assertOk()
			->assertHeader('X-RateLimit-Limit', '3')
			->assertHeader('X-RateLimit-Remaining', '2');
	}

	/**
	 * Le dépassement du plafond renvoie 429 avec les en-têtes attendus.
	 *
	 * @return void
	 */
	public function test_exceeding_the_ceiling_returns_429_with_headers(): void
	{
		config(['api.rate_limits.standard.per_minute' => 2]);

		$this->getJson('/api/v1/_debit/standard')->assertOk();
		$this->getJson('/api/v1/_debit/standard')->assertOk();

		$this->getJson('/api/v1/_debit/standard')
			->assertStatus(429)
			->assertJsonPath('code', 'rate_limited')
			->assertHeader('X-RateLimit-Limit', '2')
			->assertHeader('X-RateLimit-Remaining', '0')
			->assertHeader('Retry-After', '60');
	}

	/**
	 * L'API versionnée utilise la classe standard.
	 *
	 * @return void
	 */
	public function test_the_versioned_api_uses_the_standard_class(): void
	{
		config(['api.rate_limits.standard.per_minute' => 1]);

		$this->getJson('/api/v1/health')->assertOk()->assertHeader('X-RateLimit-Limit', '1');
		$this->getJson('/api/v1/health')->assertStatus(429);
	}

	/**
	 * Chaque utilisateur a son propre compteur.
	 *
	 * @return void
	 */
	public function test_each_user_has_their_own_counter(): void
	{
		config(['api.rate_limits.standard.per_minute' => 1]);
		[$alice, $bob] = User::factory()->count(2)->create()->all();

		Sanctum::actingAs($alice);
		$this->getJson('/api/v1/_debit/connecte')->assertOk();
		$this->getJson('/api/v1/_debit/connecte')->assertStatus(429);

		Sanctum::actingAs($bob);
		$this->getJson('/api/v1/_debit/connecte')->assertOk();
	}

	/**
	 * Des utilisateurs à jetons réels derrière une même adresse ont chacun leur compteur.
	 *
	 * @return void
	 */
	public function test_users_with_real_tokens_behind_one_address_have_their_own_counter(): void
	{
		// Vrai jeton Bearer, sans Sanctum::actingAs (qui change le garde par défaut) :
		// le limiteur du groupe v1 s'exécute avant auth:sanctum, posé sur la route.
		config(['api.rate_limits.standard.per_minute' => 1]);
		[$alice, $bob] = User::factory()->count(2)->create()->all();
		$tokenAlice = $alice->createToken('essai')->plainTextToken;
		$tokenBob = $bob->createToken('essai')->plainTextToken;

		$this->withToken($tokenAlice)->getJson('/api/v1/user')->assertOk();
		$this->app['auth']->forgetGuards();
		$this->withToken($tokenAlice)->getJson('/api/v1/user')->assertStatus(429);
		$this->app['auth']->forgetGuards();

		$this->withToken($tokenBob)->getJson('/api/v1/user')->assertOk();
	}

	/**
	 * Les classes écriture lourde et export ont leurs propres plafonds.
	 *
	 * @return void
	 */
	public function test_heavy_write_and_export_classes_have_their_own_ceilings(): void
	{
		config(['api.rate_limits.heavy.per_minute' => 1, 'api.rate_limits.export.per_minute' => 1]);

		$this->postJson('/api/v1/_debit/lourd')->assertOk()->assertHeader('X-RateLimit-Limit', '1');
		$this->postJson('/api/v1/_debit/lourd')->assertStatus(429);

		// Le plafond de l'écriture lourde n'entame pas celui de l'export.
		$this->postJson('/api/v1/_debit/export')->assertOk();
		$this->postJson('/api/v1/_debit/export')->assertStatus(429);
	}

	/**
	 * La classe auth compte par adresse, même pour des utilisateurs différents.
	 *
	 * @return void
	 */
	public function test_auth_class_counts_per_address_even_for_different_users(): void
	{
		config(['api.rate_limits.auth.per_minute' => 1]);

		$this->postJson('/api/v1/_debit/connexion', ['email' => 'alice@example.org'])->assertOk();
		$this->postJson('/api/v1/_debit/connexion', ['email' => 'bob@example.org'])->assertStatus(429);

		// Une autre adresse garde son propre compteur.
		$this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
			->postJson('/api/v1/_debit/connexion')
			->assertOk();
	}

	/**
	 * La classe auth regroupe les adresses IPv6 par préfixe /64.
	 *
	 * @return void
	 */
	public function test_auth_class_groups_ipv6_addresses_by_64_bit_prefix(): void
	{
		// Un client disposant d'un /64 ne multiplie pas ses compteurs en changeant d'adresse.
		config(['api.rate_limits.auth.per_minute' => 1]);

		$this->withServerVariables(['REMOTE_ADDR' => '2001:db8:0:1::1'])->postJson('/api/v1/_debit/connexion')->assertOk();
		$this->withServerVariables(['REMOTE_ADDR' => '2001:db8:0:1::2'])->postJson('/api/v1/_debit/connexion')->assertStatus(429);
		$this->withServerVariables(['REMOTE_ADDR' => '2001:db8:0:2::1'])->postJson('/api/v1/_debit/connexion')->assertOk();
	}

	/**
	 * Les blocages de la classe auth s'allongent à chaque fois.
	 *
	 * @return void
	 */
	public function test_auth_lockouts_get_longer_each_time(): void
	{
		config(['api.rate_limits.auth.per_minute' => 1]);

		$this->postJson('/api/v1/_debit/connexion')->assertOk();
		$this->postJson('/api/v1/_debit/connexion')->assertStatus(429)->assertHeader('Retry-After', '60');

		// Après la première attente, un nouveau dépassement double l'attente.
		$this->travel(61)->seconds();
		$this->postJson('/api/v1/_debit/connexion')->assertOk();
		$this->postJson('/api/v1/_debit/connexion')->assertStatus(429)->assertHeader('Retry-After', '120');
	}

	/**
	 * Un blocage de la classe auth ne dépasse jamais le maximum.
	 *
	 * @return void
	 */
	public function test_auth_lockout_never_exceeds_the_maximum(): void
	{
		config(['api.rate_limits.auth.per_minute' => 1, 'api.rate_limits.auth.max_lockout_seconds' => 90]);

		$this->postJson('/api/v1/_debit/connexion')->assertOk();
		$this->postJson('/api/v1/_debit/connexion')->assertStatus(429);
		$this->travel(61)->seconds();
		$this->postJson('/api/v1/_debit/connexion')->assertOk();

		$this->postJson('/api/v1/_debit/connexion')->assertStatus(429)->assertHeader('Retry-After', '90');
	}

	/**
	 * Un dépassement de plafond émet un événement d'audit.
	 *
	 * @return void
	 */
	public function test_exceeding_a_ceiling_emits_an_audit_event(): void
	{
		Event::fake([RateLimitExceeded::class]);
		config(['api.rate_limits.standard.per_minute' => 1]);

		$this->getJson('/api/v1/_debit/standard');
		$this->getJson('/api/v1/_debit/standard')->assertStatus(429);

		Event::assertDispatched(RateLimitExceeded::class, fn (RateLimitExceeded $event) => $event->limitClass === 'standard'
			&& $event->ipAddress === '127.0.0.1'
			&& $event->userId === null
			&& $event->path === 'api/v1/_debit/standard');
	}

	/**
	 * L'événement d'audit ne porte jamais le corps de la requête.
	 *
	 * @return void
	 */
	public function test_the_audit_event_never_carries_the_request_body(): void
	{
		Event::fake([RateLimitExceeded::class]);
		config(['api.rate_limits.auth.per_minute' => 1]);

		$this->postJson('/api/v1/_debit/connexion', ['password' => 'secret-de-test']);
		$this->postJson('/api/v1/_debit/connexion', ['password' => 'secret-de-test'])->assertStatus(429);

		Event::assertDispatched(RateLimitExceeded::class, fn (RateLimitExceeded $event) => ! str_contains(serialize($event), 'secret-de-test'));
	}

	/**
	 * Un dépassement de plafond est écrit dans le journal.
	 *
	 * @return void
	 */
	public function test_exceeding_a_ceiling_is_written_to_the_log(): void
	{
		config(['api.rate_limits.standard.per_minute' => 1]);
		$log = Log::spy();

		$this->getJson('/api/v1/_debit/standard');
		$this->getJson('/api/v1/_debit/standard')->assertStatus(429);

		$log->shouldHaveReceived('warning')
			->withArgs(fn (string $message, array $context) => $context['classe'] === 'standard'
				&& $context['requete'] === 'GET /api/v1/_debit/standard')
			->once();
	}

	/**
	 * Les écritures lourdes simultanées sont limitées par utilisateur.
	 *
	 * @return void
	 */
	public function test_concurrent_heavy_writes_are_limited_per_user(): void
	{
		$user = User::factory()->create();
		Sanctum::actingAs($user);

		// Un téléversement est déjà en cours pour cet utilisateur : son créneau est pris.
		$slot = Cache::lock(ConcurrencyLimit::slotKey('heavy', 'user:'.$user->id, 0), 60);
		$this->assertTrue($slot->get());

		$this->postJson('/api/v1/_debit/televersement')
			->assertStatus(429)
			->assertJsonPath('code', 'rate_limited')
			->assertHeader('Retry-After')
			->assertHeader('X-RateLimit-Limit', '1')
			->assertHeader('X-RateLimit-Remaining', '0');

		$slot->release();
		$this->postJson('/api/v1/_debit/televersement')->assertOk();
	}

	/**
	 * Une place de concurrence est libérée après la réponse.
	 *
	 * @return void
	 */
	public function test_a_concurrency_slot_is_released_after_the_response(): void
	{
		$this->postJson('/api/v1/_debit/televersement')->assertOk();
		$this->postJson('/api/v1/_debit/televersement')->assertOk();
	}

	/**
	 * Une réponse en flux garde sa place jusqu'à la fin de la requête.
	 *
	 * @return void
	 */
	public function test_a_streamed_response_keeps_its_slot_until_the_request_ends(): void
	{
		config(['api.rate_limits.export.concurrent' => 1]);
		$slotKey = ConcurrencyLimit::slotKey('export', 'ip:127.0.0.1', 0);

		// Le corps d'une réponse en flux est produit après le retour du middleware :
		// le créneau doit rester pris jusqu'à la fin de la requête (terminate).
		$response = $this->postJson('/api/v1/_debit/export-flux');
		$response->assertOk();
		$this->assertSame('pris', $response->streamedContent());

		// Après la fin de la requête, le créneau est rendu.
		$this->assertTrue(Cache::lock($slotKey, 1)->get());
	}
}
