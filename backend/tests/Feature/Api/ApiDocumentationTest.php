<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Documentation de l'API (socle-6, 11.03) : fichier OpenAPI 3.1 généré depuis
 * le code et Swagger UI, réservés aux administrateurs d'instance ; l'interface
 * est désactivable et reçoit sa propre CSP à nonce (13.04).
 */
class ApiDocumentationTest extends TestCase
{
	use RefreshDatabase;

	/** Crée un administrateur d'instance. */
	private function admin(): User
	{
		$user = User::factory()->create();
		$user->forceFill(['is_instance_admin' => true])->save();

		return $user;
	}

	public function test_the_openapi_file_is_reserved_to_instance_administrators(): void
	{
		$this->getJson('/api/openapi.json')->assertUnauthorized();

		Sanctum::actingAs(User::factory()->create());
		$this->getJson('/api/openapi.json')->assertForbidden();
	}

	public function test_the_openapi_file_describes_the_versioned_api_in_openapi_3_1(): void
	{
		Sanctum::actingAs($this->admin());

		$response = $this->getJson('/api/openapi.json')->assertOk();

		$this->assertStringStartsWith('3.1.', (string) $response->json('openapi'));
		$this->assertArrayHasKey('/v1/health', $response->json('paths'));
		$this->assertArrayHasKey('/v1/auth/login', $response->json('paths'));
	}

	public function test_swagger_ui_is_served_with_its_own_nonce_policy(): void
	{
		Sanctum::actingAs($this->admin());

		$response = $this->get('/api/documentation')->assertOk();
		$policy = (string) $response->headers->get('Content-Security-Policy');

		$this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9+\\/=]{22,})'/", $policy);
		preg_match("/'nonce-([^']+)'/", $policy, $matches);
		$this->assertStringContainsString('nonce="'.$matches[1].'"', (string) $response->getContent());
		$this->assertStringContainsString('/swagger-ui/swagger-ui-bundle.js', (string) $response->getContent());
		$this->assertStringContainsString("frame-ancestors 'none'", $policy);
	}

	public function test_swagger_ui_is_reserved_to_instance_administrators(): void
	{
		Sanctum::actingAs(User::factory()->create());

		$this->get('/api/documentation')->assertForbidden();
	}

	public function test_swagger_ui_can_be_disabled_while_the_file_stays_available(): void
	{
		config(['api.documentation.ui_enabled' => false]);
		Sanctum::actingAs($this->admin());

		$this->get('/api/documentation')->assertNotFound();
		$this->getJson('/api/openapi.json')->assertOk();
	}

	public function test_the_default_scramble_routes_are_not_exposed(): void
	{
		$this->get('/docs/api')->assertNotFound();
		$this->get('/docs/api.json')->assertNotFound();
	}
}
