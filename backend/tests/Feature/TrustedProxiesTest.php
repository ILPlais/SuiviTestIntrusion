<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Proxys de confiance (13.06, Sécurité HTTP) : seul le proxy inverse de l'exploitant,
 * déclaré dans TRUSTED_PROXIES, peut imposer les en-têtes X-Forwarded-* ; jamais « * ».
 */
class TrustedProxiesTest extends TestCase
{
	/** Adresse du proxy inverse déclarée dans les tests. */
	private const PROXY = '10.20.30.40';

	/**
	 * Déclare une route de test qui renvoie l'adresse, le schéma et l'URL vus par l'application.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		// Route de test (sous /api, hors de la route de la SPA) qui renvoie ce que
		// Laravel déduit de la requête.
		Route::get('/api/_test/requete', fn (Request $request) => [
			'secure' => $request->secure(),
			'ip' => $request->ip(),
			'url' => url('/chemin'),
		]);
	}

	/**
	 * Envoie une requête relayée : adresse du pair et en-têtes X-Forwarded-*.
	 *
	 * @param string $peer Adresse du pair TCP (REMOTE_ADDR).
	 * @return array<string, mixed>
	 */
	private function relayedRequest(string $peer): array
	{
		return $this->withServerVariables(['REMOTE_ADDR' => $peer])
			->withHeaders([
				'X-Forwarded-For' => '203.0.113.7',
				'X-Forwarded-Proto' => 'https',
				'X-Forwarded-Host' => 'pentest.example.org',
				'X-Forwarded-Port' => '443',
			])
			->getJson('/api/_test/requete')
			->assertOk()
			->json();
	}

	/**
	 * Le proxy déclaré est approuvé pour les en-têtes transmis.
	 *
	 * @return void
	 */
	public function test_the_declared_proxy_is_trusted_for_forwarded_headers(): void
	{
		config(['trustedproxy.proxies' => [self::PROXY]]);

		$result = $this->relayedRequest(self::PROXY);

		$this->assertTrue($result['secure']);
		$this->assertSame('203.0.113.7', $result['ip']);
		$this->assertSame('https://pentest.example.org/chemin', $result['url']);
	}

	/**
	 * Les en-têtes transmis depuis une autre adresse sont ignorés.
	 *
	 * @return void
	 */
	public function test_forwarded_headers_from_another_address_are_ignored(): void
	{
		config(['trustedproxy.proxies' => [self::PROXY]]);

		$result = $this->relayedRequest('10.20.30.99');

		$this->assertFalse($result['secure']);
		$this->assertSame('10.20.30.99', $result['ip']);
	}

	/**
	 * Aucun proxy n'est approuvé par défaut.
	 *
	 * @return void
	 */
	public function test_no_proxy_is_trusted_by_default(): void
	{
		config(['trustedproxy.proxies' => []]);

		$result = $this->relayedRequest(self::PROXY);

		$this->assertFalse($result['secure']);
		$this->assertSame(self::PROXY, $result['ip']);
	}

	/**
	 * Un joker refusé est signalé dans le journal.
	 *
	 * @return void
	 */
	public function test_a_rejected_wildcard_is_reported_in_the_log(): void
	{
		config(['trustedproxy.wildcard_rejected' => true]);
		$log = Log::spy();

		(new AppServiceProvider($this->app))->boot();

		$log->shouldHaveReceived('warning')->once();
	}

	/**
	 * L'environnement d'exemple documente les proxys approuvés.
	 *
	 * @return void
	 */
	public function test_the_example_environment_documents_trusted_proxies(): void
	{
		$this->assertStringContainsString('TRUSTED_PROXIES=', file_get_contents(base_path('.env.example')));
	}
}
