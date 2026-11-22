<?php

namespace Tests\Feature\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/**
 * Toute erreur de l'API a la forme {code, message, details} et le statut HTTP
 * de la table de 11.03 ; le message vient des fichiers de langue (français en V1).
 */
class ErrorEnvelopeTest extends TestCase
{
	/**
	 * Déclare des routes de test qui provoquent chaque erreur de l'enveloppe.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		// Routes de test qui lèvent chaque type d'erreur, sous le préfixe de l'API.
		Route::middleware('api')->prefix('api/v1/_essai')->group(function () {
			Route::get('interdit', fn () => throw new AuthorizationException('détail interne'));
			Route::get('conflit', fn () => throw new ConflictHttpException('détail interne'));
			Route::get('trop-gros', fn () => throw new PostTooLargeException());
			Route::get('trop-vite', fn () => throw new ThrottleRequestsException('détail interne', null, ['Retry-After' => '30']));
			Route::get('invalide', fn () => throw ValidationException::withMessages(['titre' => ['Le titre est obligatoire.']]));
			Route::get('panne', fn () => throw new RuntimeException('SQLSTATE secret dans /var/www/app.php'));
		});
	}

	/**
	 * Vérifie le statut, le code, le message traduit et la forme d'une réponse d'erreur.
	 *
	 * @param string $uri Adresse appelée.
	 * @param int $status Statut HTTP attendu.
	 * @param string $code Code d'erreur attendu.
	 * @param string $method Verbe HTTP de l'appel.
	 * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
	 */
	private function assertEnvelope(string $uri, int $status, string $code, string $method = 'GET'): TestResponse
	{
		$response = $this->json($method, $uri);

		$response->assertStatus($status)
			->assertJsonPath('code', $code)
			->assertJsonPath('message', __("errors.{$code}"));
		$this->assertSame(['code', 'message', 'details'], array_keys($response->json()));
		$this->assertNotSame("errors.{$code}", __("errors.{$code}"), 'message absent du fichier de langue');

		return $response;
	}

	/**
	 * Une route inconnue donne 404 not_found.
	 *
	 * @return void
	 */
	public function test_route_inconnue_donne_404_not_found(): void
	{
		$this->assertEnvelope('/api/v1/inexistant', 404, 'not_found');
	}

	/**
	 * Une méthode non prévue donne 404 not_found.
	 *
	 * @return void
	 */
	public function test_methode_non_prevue_donne_404_not_found(): void
	{
		$this->assertEnvelope('/api/v1/health', 404, 'not_found', 'DELETE');
	}

	/**
	 * L'absence de session donne 401 unauthenticated.
	 *
	 * @return void
	 */
	public function test_absence_de_session_donne_401_unauthenticated(): void
	{
		$this->assertEnvelope('/api/v1/user', 401, 'unauthenticated');
	}

	/**
	 * Un refus de droit donne 403 forbidden.
	 *
	 * @return void
	 */
	public function test_refus_de_droit_donne_403_forbidden(): void
	{
		$this->assertEnvelope('/api/v1/_essai/interdit', 403, 'forbidden');
	}

	/**
	 * Une modification concurrente donne 409 conflict.
	 *
	 * @return void
	 */
	public function test_modification_concurrente_donne_409_conflict(): void
	{
		$this->assertEnvelope('/api/v1/_essai/conflit', 409, 'conflict');
	}

	/**
	 * Une requête trop grosse donne 413 payload_too_large.
	 *
	 * @return void
	 */
	public function test_requete_trop_grosse_donne_413_payload_too_large(): void
	{
		$this->assertEnvelope('/api/v1/_essai/trop-gros', 413, 'payload_too_large');
	}

	/**
	 * Trop d'appels donnent 429 avec l'en-tête Retry-After.
	 *
	 * @return void
	 */
	public function test_trop_d_appels_donne_429_avec_retry_after(): void
	{
		$this->assertEnvelope('/api/v1/_essai/trop-vite', 429, 'rate_limited')
			->assertHeader('Retry-After', '30');
	}

	/**
	 * Une donnée refusée donne 422 avec le détail par champ.
	 *
	 * @return void
	 */
	public function test_donnee_refusee_donne_422_avec_le_detail_par_champ(): void
	{
		$this->assertEnvelope('/api/v1/_essai/invalide', 422, 'validation_failed')
			->assertJsonPath('details.titre.0', 'Le titre est obligatoire.');
	}

	/**
	 * Une erreur serveur ne révèle aucun détail technique, même en mode debug.
	 *
	 * @return void
	 */
	public function test_erreur_serveur_ne_revele_aucun_detail_technique_meme_en_debug(): void
	{
		config(['app.debug' => true]);

		$response = $this->assertEnvelope('/api/v1/_essai/panne', 500, 'internal_error');

		$this->assertSame([], (array) $response->json('details'));
		$this->assertStringNotContainsString('SQLSTATE', $response->getContent());
		$this->assertStringNotContainsString('/var/www', $response->getContent());
		$this->assertStringNotContainsString('trace', $response->getContent());
	}

	/**
	 * Les messages d'erreur sont en français.
	 *
	 * @return void
	 */
	public function test_les_messages_sont_en_francais(): void
	{
		$this->getJson('/api/v1/inexistant')
			->assertJsonPath('message', 'La ressource demandée est introuvable.');
	}
}
