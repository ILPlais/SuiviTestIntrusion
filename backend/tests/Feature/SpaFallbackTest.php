<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Service de la SPA par Laravel (socle-2) : dans l'image Docker comme en installation
 * manuelle, la SPA compilée et l'API partagent l'origine (13.06). Toute URL hors de
 * /api reçoit index.html, pour que le routeur de la SPA gère les liens profonds.
 */
class SpaFallbackTest extends TestCase
{
	/**
	 * Fichier index.html temporaire utilisé à la place de la SPA compilée.
	 *
	 * @var string
	 */
	private string $index;

	/**
	 * Crée une SPA compilée factice pour les tests.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->index = tempnam(sys_get_temp_dir(), 'spa').'.html';
		file_put_contents($this->index, '<!doctype html><div id="app"></div>');
		config(['app.spa_index' => $this->index]);
	}

	/**
	 * Supprime la SPA compilée factice.
	 *
	 * @return void
	 */
	protected function tearDown(): void
	{
		@unlink($this->index);

		parent::tearDown();
	}

	/**
	 * La racine sert la SPA compilée.
	 *
	 * @return void
	 */
	public function test_the_root_serves_the_compiled_spa(): void
	{
		$response = $this->get('/');

		$response->assertOk();
		$this->assertStringContainsString('<div id="app"></div>', $response->streamedContent());
		$this->assertStringStartsWith('text/html', $response->headers->get('Content-Type'));
	}

	/**
	 * Les liens profonds servent la SPA.
	 *
	 * @return void
	 */
	public function test_deep_links_serve_the_spa(): void
	{
		$response = $this->get('/engagements/12/vulnerabilites');

		$response->assertOk();
		$this->assertStringContainsString('<div id="app"></div>', $response->streamedContent());
	}

	/**
	 * Le point d'entrée de la SPA n'est jamais mis en cache.
	 *
	 * @return void
	 */
	public function test_the_spa_entry_point_is_never_cached(): void
	{
		// Les ressources compilées portent une empreinte ; seul index.html doit être relu.
		$this->assertStringContainsString('no-cache', $this->get('/')->headers->get('Cache-Control'));
	}

	/**
	 * Servir la SPA ne crée ni session ni cookie.
	 *
	 * @return void
	 */
	public function test_serving_the_spa_creates_no_session_nor_cookie(): void
	{
		$response = $this->get('/engagements');

		$response->assertOk();
		$this->assertSame([], $response->headers->getCookies());
	}

	/**
	 * Les routes d'API inconnues gardent l'enveloppe d'erreur JSON.
	 *
	 * @return void
	 */
	public function test_unknown_api_routes_keep_the_json_error_envelope(): void
	{
		$this->getJson('/api/v1/inexistant')
			->assertNotFound()
			->assertJsonPath('code', 'not_found');

		$this->get('/api/inexistant')->assertNotFound()->assertJsonPath('code', 'not_found');
	}

	/**
	 * La SPA n'est pas servie tant qu'elle n'a pas été compilée.
	 *
	 * @return void
	 */
	public function test_the_spa_is_not_served_when_it_has_not_been_built(): void
	{
		config(['app.spa_index' => '/inexistant/index.html']);

		$this->get('/')->assertNotFound();
	}

	/**
	 * La route du cookie CSRF de Sanctum n'est pas prise par la SPA.
	 *
	 * @return void
	 */
	public function test_the_sanctum_csrf_cookie_route_is_not_taken_by_the_spa(): void
	{
		$this->get('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
	}

	/**
	 * Le contrôle de santé du conteneur reste disponible.
	 *
	 * @return void
	 */
	public function test_the_container_health_check_stays_available(): void
	{
		$this->get('/up')->assertOk();
	}
}
